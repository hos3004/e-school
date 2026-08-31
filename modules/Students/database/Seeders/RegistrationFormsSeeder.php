<?php

declare(strict_types=1);

namespace Modules\Students\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Students\Domain\Enums\RegistrationQuestionType;
use Modules\Students\Domain\Models\RegistrationForm;
use Modules\Students\Domain\Models\RegistrationQuestion;

/** قوالب البداية المستخلصة من نماذج التسجيل الثلاثة الحالية للأكاديمية. */
final class RegistrationFormsSeeder extends Seeder
{
    public function run(): void
    {
        $organizationId = (string) config('app.default_organization_id');
        if ($organizationId === '') {
            $organizationId = (string) DB::table('organizations')->oldest('created_at')->value('id');
        }

        if ($organizationId === '') {
            return;
        }

        foreach ($this->forms() as $definition) {
            $questions = $definition['questions'];
            unset($definition['questions']);

            $form = RegistrationForm::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'organization_id' => $organizationId],
            );

            foreach ($questions as $sortOrder => $question) {
                RegistrationQuestion::query()->updateOrCreate(
                    [
                        'registration_form_id' => (string) $form->getKey(),
                        'sort_order' => $sortOrder,
                    ],
                    [
                        ...$question,
                        'organization_id' => $organizationId,
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ],
                );
            }
        }
    }

    /**
     * الحقول الأساسية (اسم الطالب، الميلاد، الجنس، الدولة، المنطقة، وسيلة التواصل)
     * يضيفها النظام تلقائيًا؛ هنا فقط الأسئلة الخاصة بكل برنامج.
     *
     * @return list<array<string, mixed>>
     */
    private function forms(): array
    {
        return [
            [
                'slug' => 'free-online-classes',
                'title' => [
                    'ar' => 'التسجيل في الفصول الإلكترونية المجانية',
                    'en' => 'Free online classes registration',
                    'fr' => 'Inscription aux cours en ligne gratuits',
                ],
                'description' => [
                    'ar' => 'رحلة تفاعلية لتعلم القرآن الكريم والتربية الإسلامية واللغة العربية عن بُعد.',
                    'en' => 'Interactive online Quran, Islamic studies, and Arabic classes.',
                    'fr' => 'Cours interactifs en ligne de Coran, d’études islamiques et d’arabe.',
                ],
                'is_active' => true,
                'questions' => [
                    $this->text('اسم ولي الأمر', 'Guardian name', true),
                    $this->radio('مقدار حفظ الطالب الحالي للقرآن الكريم', 'Current Quran memorization level', [
                        'قصار السور / جزء عم', 'من جزء إلى 3 أجزاء', 'أكثر من 3 أجزاء',
                    ]),
                    $this->radio('ما مستوى الطالب الحالي في اللغة العربية؟', 'Current Arabic level', [
                        'مبتدئ', 'متوسط', 'متقدم', 'لم يبدأ بعد',
                    ], true, true),
                    $this->text('ما الوقت الأنسب لحضور الفصول؟ (بتوقيت مكة)', 'Preferred class time (Makkah time)', true),
                    $this->radio('من أين سمعت عن فصولنا؟', 'How did you hear about us?', [
                        'إعلان فيسبوك / إنستجرام', 'مجموعات التواصل', 'توصية من صديق', 'مصدر آخر',
                    ], true, true),
                ],
            ],
            [
                'slug' => 'quran-sessions',
                'title' => [
                    'ar' => 'التسجيل على جلسات تحفيظ القرآن',
                    'en' => 'Quran memorization sessions',
                    'fr' => 'Sessions de mémorisation du Coran',
                ],
                'description' => [
                    'ar' => 'جلسات إلكترونية فردية مع نخبة من الشيوخ والقراء، تسبقها مقابلة مبدئية.',
                    'en' => 'One-to-one online sessions with qualified Quran teachers, preceded by an assessment.',
                    'fr' => 'Sessions individuelles en ligne avec des enseignants qualifiés, précédées d’une évaluation.',
                ],
                'is_active' => true,
                'questions' => [
                    $this->text('الجنسية', 'Nationality', true),
                    $this->radio('ما مستواك الحالي في الحفظ؟', 'Current memorization level', [
                        'مبتدئ وأرغب بالبدء من الصفر', 'بدأت الحفظ وأريد الاستكمال', 'حافظ وأرغب في الإجازة',
                    ], true, true),
                    $this->radio('ما مستواك في أحكام التجويد؟', 'Current Tajweed level', [
                        'أحتاج إلى التأسيس', 'أعرف الأساسيات وأحتاج إلى الممارسة', 'متقن نظريًا وعمليًا',
                    ]),
                    $this->radio('كم عدد اللقاءات الأسبوعية التي يمكنك الالتزام بها؟', 'Weekly sessions you can attend', [
                        '3 لقاءات', '4 لقاءات', '5 لقاءات',
                    ], true, true),
                    $this->checkbox('ما الأوقات الأنسب لجلسات الحفظ؟ (بتوقيت مكة)', 'Preferred session times (Makkah time)', [
                        'الصباح', 'العصر', 'المساء', 'بعد العشاء',
                    ], true),
                ],
            ],
            [
                'slug' => 'kids-coding-ai',
                'title' => [
                    'ar' => 'كورس البرمجة والذكاء الاصطناعي للأطفال',
                    'en' => 'Coding and AI course for children',
                    'fr' => 'Cours de programmation et d’IA pour enfants',
                ],
                'description' => [
                    'ar' => 'كورس Scratch والذكاء الاصطناعي للأطفال من 10 إلى 14 سنة، أونلاين عبر Zoom في 8 جلسات تطبيقية.',
                    'en' => 'An eight-session live online Scratch and AI course for children aged 10–14.',
                    'fr' => 'Cours en direct de Scratch et d’IA en huit séances pour les enfants de 10 à 14 ans.',
                ],
                'is_active' => true,
                'questions' => [
                    $this->text('اسم ولي الأمر', 'Guardian name', true),
                    $this->radio('هل يشارك الطفل معنا في برامج أخرى؟', 'Does the child attend another program with us?', [
                        'نعم يشارك', 'لم يشارك من قبل',
                    ], true, true),
                    $this->radio('هل يتوفر حاسوب واتصال جيد بالإنترنت؟', 'Is a computer and reliable internet available?', [
                        'نعم يتوفر', 'لا يتوفر',
                    ], true, true),
                    $this->radio('هل يمتلك الطالب خبرة سابقة في البرمجة؟', 'Does the student have prior coding experience?', [
                        'نعم، لديه خلفية بسيطة', 'هذه تجربته الأولى',
                    ], true, true),
                    $this->radio('هل تؤكد الالتزام بحضور الجلسات الثماني؟', 'Do you confirm attendance for all eight sessions?', [
                        'نعم أؤكد',
                    ], true),
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function text(string $ar, string $en, bool $required = false): array
    {
        return [
            'question' => ['ar' => $ar, 'en' => $en],
            'type' => RegistrationQuestionType::Text,
            'options' => null,
            'is_required' => $required,
            'is_filterable' => false,
        ];
    }

    /**
     * @param list<string> $options
     * @return array<string, mixed>
     */
    private function radio(string $ar, string $en, array $options, bool $required = false, bool $filterable = false): array
    {
        return [
            'question' => ['ar' => $ar, 'en' => $en],
            'type' => RegistrationQuestionType::Radio,
            'options' => $options,
            'is_required' => $required,
            'is_filterable' => $filterable,
        ];
    }

    /**
     * @param list<string> $options
     * @return array<string, mixed>
     */
    private function checkbox(string $ar, string $en, array $options, bool $required = false): array
    {
        return [
            'question' => ['ar' => $ar, 'en' => $en],
            'type' => RegistrationQuestionType::Checkbox,
            'options' => $options,
            'is_required' => $required,
            'is_filterable' => false,
        ];
    }
}
