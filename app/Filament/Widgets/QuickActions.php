<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

/**
 * أزرار الإجراءات الأكثر تكرارًا في لوحة الإدارة.
 *
 * كل وجهة هنا مسار مُتحقَّق منه في route:list؛ الحصص لا تملك صفحة إنشاء
 * بعد، فيُفتح فهرسها حتى تتوفر.
 */
final class QuickActions extends Widget
{
    protected string $view = 'filament.widgets.quick-actions';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'title' => __('dashboard.quick_actions.title'),
            'actions' => [
                [
                    'label' => __('dashboard.quick_actions.new_student'),
                    'href' => '/admin/students/create',
                    'icon' => 'heroicon-o-user-plus',
                ],
                [
                    'label' => __('dashboard.quick_actions.new_program'),
                    'href' => '/admin/program-filaments/create',
                    'icon' => 'heroicon-o-book-open',
                ],
                [
                    'label' => __('dashboard.quick_actions.new_group'),
                    'href' => '/admin/groups/create',
                    'icon' => 'heroicon-o-user-group',
                ],
                [
                    'label' => __('dashboard.quick_actions.sessions'),
                    'href' => '/admin/sessions',
                    'icon' => 'heroicon-o-calendar-days',
                ],
            ],
        ];
    }
}
