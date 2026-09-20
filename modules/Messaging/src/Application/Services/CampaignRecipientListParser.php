<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * قراءة قائمة المستلمين من ملف مرفوع أو من نص ملصوق.
 *
 * الملف عمودان لا أكثر: اسم ورقم. ترتيب العمودين لا يُفرض على المرسِل — يُقرأ
 * كل صف ويُختار منه ما يشبه الرقم رقمًا والباقي اسمًا، فملف بعمود الرقم أولًا
 * يُقرأ كما يُقرأ العكس. صف العناوين يُكتشف ويُتخطى.
 */
final class CampaignRecipientListParser
{
    /**
     * كلمات صف العناوين. وجودها في صف يعني أنه وصف للأعمدة لا بيانات شخص.
     *
     * @var list<string>
     */
    private const HEADER_WORDS = [
        'الاسم', 'اسم', 'الرقم', 'رقم', 'الهاتف', 'هاتف', 'الجوال', 'جوال',
        'الموبايل', 'موبايل', 'واتس', 'name', 'phone', 'mobile', 'number', 'whatsapp',
    ];

    /**
     * @return list<array{name: string|null, phone_input: string}>
     */
    public function fromText(string $text): array
    {
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            // فاصلة أو فاصلة منقوطة أو جدولة أو شرطة رأسية بين الاسم والرقم.
            $cells = preg_split('/[,;\t|]+/', $line) ?: [$line];

            /*
             * سطر بلا فاصل قد يكون «أحمد 201012345678»: آخر قطعة هي الرقم وما
             * قبلها الاسم. تقسيم المسافات لا يصلح للأرقام المكتوبة بمسافات
             * (+20 10 1234 5678)، فلا يُطبَّق إلا إذا كانت آخر قطعة وحدها رقمًا
             * كاملًا مقبولًا.
             */
            if (count($cells) === 1) {
                $cells = $this->splitNameFromTrailingNumber($line);
            }

            $row = $this->rowFromCells($cells);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{name: string|null, phone_input: string}>
     */
    public function fromFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls', 'ods'], true)) {
            return $this->fromSpreadsheet($file->getRealPath());
        }

        $contents = (string) file_get_contents((string) $file->getRealPath());

        // BOM من Excel يلتصق بأول خلية فيفسد أول اسم أو أول رقم.
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;

        return $this->fromText($contents);
    }

    /**
     * @return list<array{name: string|null, phone_input: string}>
     */
    private function fromSpreadsheet(string $path): array
    {
        try {
            $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable) {
            return [];
        }

        $rows = [];

        foreach ($sheet as $cells) {
            if (!is_array($cells)) {
                continue;
            }

            $row = $this->rowFromCells(array_map(
                static fn (mixed $cell): string => is_scalar($cell) ? trim((string) $cell) : '',
                array_values($cells),
            ));

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $cells
     * @return array{name: string|null, phone_input: string}|null
     */
    private function rowFromCells(array $cells): ?array
    {
        $cells = array_values(array_filter(
            array_map(trim(...), $cells),
            static fn (string $cell): bool => $cell !== '',
        ));

        if ($cells === []) {
            return null;
        }

        if ($this->looksLikeHeader($cells)) {
            return null;
        }

        $phoneIndex = null;

        foreach ($cells as $index => $cell) {
            if ($this->looksLikePhone($cell)) {
                $phoneIndex = $index;
                break;
            }
        }

        /*
         * صف بلا خلية تشبه الرقم يُسجَّل بأول خلية رقمًا مُدخلًا، فيسقط لاحقًا
         * في التطبيع برسالة واضحة. تجاهله صامتًا كان سيخفي سطرًا عن المرسِل.
         */
        if ($phoneIndex === null) {
            $phoneIndex = count($cells) - 1;
        }

        $phone = $cells[$phoneIndex];
        unset($cells[$phoneIndex]);

        $name = trim(implode(' ', $cells));

        return [
            'name' => $name === '' ? null : mb_substr($name, 0, 255),
            'phone_input' => mb_substr($phone, 0, 64),
        ];
    }

    /**
     * @param list<string> $cells
     */
    private function looksLikeHeader(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($this->looksLikePhone($cell)) {
                return false;
            }
        }

        foreach ($cells as $cell) {
            $normalized = mb_strtolower($cell);

            foreach (self::HEADER_WORDS as $word) {
                if (str_contains($normalized, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function looksLikePhone(string $value): bool
    {
        $digits = preg_replace('/\D/u', '', strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ])) ?? '';

        if (strlen($digits) < 7) {
            return false;
        }

        // خلية فيها حروف كثيرة ليست رقمًا مهما حملت من أرقام.
        return preg_match('/^[\+\d\s().\-_\x{00a0}٠-٩]+$/u', trim($value)) === 1;
    }

    /**
     * @return list<string>
     */
    private function splitNameFromTrailingNumber(string $line): array
    {
        $parts = preg_split('/\s+/u', $line) ?: [$line];

        if (count($parts) < 2) {
            return [$line];
        }

        $last = (string) end($parts);

        if (!$this->looksLikePhone($last)) {
            return [$line];
        }

        array_pop($parts);

        return [implode(' ', $parts), $last];
    }
}
