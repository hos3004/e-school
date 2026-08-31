<?php

declare(strict_types=1);

namespace Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Modules\Notifications\Application\Services\PopupPageRegistry;
use Modules\Notifications\Domain\Enums\PopupAudience;
use Modules\Notifications\Domain\Models\PopupCampaign;
use Modules\Notifications\Domain\Models\PopupCampaignUserState;
use Modules\Notifications\Presentation\Filament\Resources\PopupCampaignResource;

final class ViewPopupCampaign extends ViewRecord
{
    protected static string $resource = PopupCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            ...ListPopupCampaigns::recordActionsForTable(),
        ];
    }

    /**
     * معاينة حقيقية بنفس المكوّن — بلا أي تسجيل إحصاءات.
     */
    private function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('notifications::popups.preview.action'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalContent(fn (): View => view('notifications::popups.preview', [
                'record' => $this->record,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make(__('notifications::navigation.popup.plural'))
                ->persistTabInQueryString('popup-view')
                ->tabs([
                    Tab::make(__('notifications::popups.view.overview'))
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            TextEntry::make('internal_name')->label(__('notifications::popups.fields.internal_name')),
                            TextEntry::make('title.ar')->label(__('notifications::popups.fields.title_ar')),
                            TextEntry::make('type')
                                ->label(__('notifications::popups.fields.type'))
                                ->badge()
                                ->formatStateUsing(static fn ($state): string => $state->label()),
                            TextEntry::make('audiences')
                                ->label(__('notifications::popups.fields.audiences'))
                                ->badge()
                                ->formatStateUsing(static fn ($state): string => PopupAudience::tryFrom((string) $state)?->label() ?? (string) $state),
                            TextEntry::make('placement')
                                ->label(__('notifications::popups.fields.placement'))
                                ->formatStateUsing(static fn ($state): string => $state->label()),
                            TextEntry::make('page_key')
                                ->label(__('notifications::popups.fields.page_key'))
                                ->formatStateUsing(static fn (?string $state): string => $state === null
                                    ? '—'
                                    : (string) (PopupPageRegistry::options()[$state] ?? $state)),
                            TextEntry::make('frequency')
                                ->label(__('notifications::popups.fields.frequency'))
                                ->badge()
                                ->formatStateUsing(static fn ($state): string => $state->label()),
                            TextEntry::make('priority')->label(__('notifications::popups.fields.priority'))->numeric(),
                            TextEntry::make('starts_at')
                                ->label(__('notifications::popups.fields.starts_at'))
                                ->dateTime()
                                ->timezone(config('app.timezone')),
                            TextEntry::make('ends_at')
                                ->label(__('notifications::popups.fields.ends_at'))
                                ->dateTime()
                                ->timezone(config('app.timezone')),
                            TextEntry::make('body.ar')
                                ->label(__('notifications::popups.fields.body_ar'))
                                ->columnSpanFull(),
                        ])->columns(3),

                    Tab::make(__('notifications::popups.view.analytics'))
                        ->icon('heroicon-o-chart-bar')
                        ->visible(static fn (): bool => auth()->user()?->can('viewAnalytics', PopupCampaign::class) ?? false)
                        ->schema([
                            ...self::analyticsEntries(),
                        ])->columns(3),

                    Tab::make(__('notifications::popups.view.audit_note'))
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            TextEntry::make('created_by')->label(__('notifications::popups.view.created_by')),
                            TextEntry::make('updated_by')->label(__('notifications::popups.view.updated_by')),
                            TextEntry::make('published_by')->label(__('notifications::popups.view.published_by')),
                            TextEntry::make('published_at')->label(__('notifications::popups.view.published_at'))->dateTime()->timezone(config('app.timezone')),
                            TextEntry::make('created_at')->label(__('notifications::popups.view.created_at'))->dateTime()->timezone(config('app.timezone')),
                            TextEntry::make('updated_at')->label(__('notifications::popups.view.updated_at'))->dateTime()->timezone(config('app.timezone')),
                        ])->columns(3),
                ])->columnSpanFull(),
        ]);
    }

    /**
     * @return list<Component>
     */
    private static function analyticsEntries(): array
    {
        /** @var PopupCampaign $campaign */
        $campaign = request()?->route('record') ?? null;

        if (!$campaign instanceof PopupCampaign) {
            return [];
        }

        // تجميع واحد على جدول الحالة — لا قوائم مستخدمين فردية هنا.
        $stats = PopupCampaignUserState::query()
            ->where('campaign_id', $campaign->getKey())
            ->selectRaw(implode(', ', [
                'count(*) as seen_users',
                'coalesce(sum(impressions_count), 0) as impressions',
                'count(*) filter (where acknowledged_at is not null) as acknowledgements',
                'count(*) filter (where dismissed_at is not null) as dismissals',
                'count(*) filter (where clicked_at is not null) as clicks',
            ]))
            ->first();

        $impressions = (int) ($stats?->impressions ?? 0);
        $clicks = (int) ($stats?->clicks ?? 0);
        $ctr = $impressions > 0 ? round(100 * $clicks / $impressions, 1).'%' : '—';

        return [
            TextEntry::make('stat_seen_users')->label(__('notifications::popups.analytics.seen_users'))->state((string) ($stats?->seen_users ?? 0)),
            TextEntry::make('stat_impressions')->label(__('notifications::popups.analytics.impressions'))->state((string) $impressions),
            TextEntry::make('stat_acknowledgements')->label(__('notifications::popups.analytics.acknowledgements'))->state((string) ($stats?->acknowledgements ?? 0)),
            TextEntry::make('stat_dismissals')->label(__('notifications::popups.analytics.dismissals'))->state((string) ($stats?->dismissals ?? 0)),
            TextEntry::make('stat_clicks')->label(__('notifications::popups.analytics.clicks'))->state((string) $clicks),
            TextEntry::make('stat_ctr')->label(__('notifications::popups.analytics.ctr'))->state($ctr),
        ];
    }
}
