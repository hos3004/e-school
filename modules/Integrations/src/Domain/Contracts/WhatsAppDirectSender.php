<?php

declare(strict_types=1);

namespace Modules\Integrations\Domain\Contracts;

use Modules\Integrations\Domain\ValueObjects\GatewayResult;

/**
 * إرسال واتساب إلى رقم مباشرةً، بلا مستخدم في المنصة.
 *
 * ChannelGateway يخدم صندوق الصادر: رسالته تحمل لغة صفٍّ وعنوانًا ومتنًا بخريطة
 * لغات، وتفترض مستلمًا له حساب. الحملة الموجَّهة إلى أرقام خارجية لا تملك شيئًا
 * من ذلك، ولا تملك مرفقات في ذلك العقد أصلًا — فهذا عقد ثانٍ ضيّق بجانبه، لا
 * توسيعٌ للأول يُجبر كل بوابة على دعم ما لا يخصها.
 */
interface WhatsAppDirectSender
{
    public function sendText(string $organizationId, string $phone, string $text): GatewayResult;

    /**
     * يرفع الملف إلى المزوّد رفعًا مباشرًا — لا رابطًا عامًّا للملف.
     */
    public function sendFile(
        string $organizationId,
        string $phone,
        string $absolutePath,
        string $fileName,
        ?string $caption = null,
    ): GatewayResult;
}
