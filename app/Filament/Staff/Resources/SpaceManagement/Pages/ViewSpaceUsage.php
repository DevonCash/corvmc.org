<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Staff\Resources\Charges\ChargeResource;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Staff\Resources\Users\UserResource;
use App\Models\User;
use CorvMC\Finance\Actions\Payments\MarkReservationAsComped;
use CorvMC\Finance\Actions\Payments\MarkReservationAsPaid;
use CorvMC\Finance\Enums\ChargeStatus;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Actions\Reservations\ConfirmReservation;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconSize;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

class ViewSpaceUsage extends ViewRecord
{
    protected static string $resource = SpaceManagementResource::class;

    public function getTitle(): string
    {
        $name = $this->record->reservable instanceof User
            ? $this->record->reservable->name
            : $this->record->getDisplayTitle();
        $date = $this->record->reserved_at->format('M j, Y');

        return "{$name} · {$date}";
    }

    protected function formatHours(float $hours): string
    {
        return fmod($hours, 1) === 0.0 ? intval($hours) . ' hrs' : number_format($hours, 1) . ' hrs';
    }

    protected function formatCostBreakdown(RehearsalReservation $record): string
    {
        $parts = [];
        if ($record->free_hours_used > 0) {
            $parts[] = $this->formatHours($record->free_hours_used) . ' free';
        }
        $paidHours = $record->hours_used - $record->free_hours_used;
        if ($paidHours > 0) {
            $freeLabel = fmod($paidHours, 1) === 0.0 ? intval($paidHours) : number_format($paidHours, 1);
            $parts[] = $freeLabel . ' hrs × $15';
        }

        return implode(' + ', $parts);
    }

    public function infolist(Schema $schema): Schema
    {
        $hasNotes = ! empty($this->record->notes);

        return $schema
            ->columns(3)
            ->components([
                // Hero: Status at a glance - full width, ordered most to least important
                // Stacks on mobile, horizontal on md+
                Flex::make([
                    // When (most important for scheduling)
                    TextEntry::make('reserved_at')
                        ->hiddenLabel()
                        ->icon('tabler-calendar')
                        ->date('l, M j, Y'),
                    TextEntry::make('time_slot')
                        ->hiddenLabel()
                        ->icon('tabler-clock')
                        ->state(fn (Model $record): string => $record->reserved_at->format('g:i A').' – '.$record->reserved_until->format('g:i A').' ('.$this->formatHours($record->hours_used).')'),
                    // Status badges grouped together
                    Flex::make([
                        TextEntry::make('status')
                            ->hiddenLabel()
                            ->badge()
                            ->size(TextSize::Large),
                        TextEntry::make('charge.status')
                            ->hiddenLabel()
                            ->badge()
                            ->size(TextSize::Large)
                            ->visible(fn (?Model $record): bool => $record instanceof RehearsalReservation && $record->charge),
                        TextEntry::make('first_reservation_badge')
                            ->hiddenLabel()
                            ->badge()
                            ->color('info')
                            ->icon('tabler-sparkles')
                            ->state('First booking')
                            ->visible(fn (?Model $record): bool => $record?->reservable instanceof User
                                && RehearsalReservation::where('reservable_type', 'user')
                                    ->where('reservable_id', $record->reservable->getKey())
                                    ->count() === 1),
                    ]),
                ])->from('md')->columnSpanFull()->verticallyAlignCenter(),

                // Main content: Member + Payment (equal width)
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        // Member card
                        Section::make('Member')
                            ->icon('tabler-user')
                            ->compact()
                            ->headerActions([
                                Action::make('view_member')
                                    ->label('View')
                                    ->icon('tabler-external-link')
                                    ->iconButton()
                                    ->color('gray')
                                    ->url(fn(?Model $record): ?string => $record?->reservable instanceof User
                                        ? UserResource::getUrl('edit', ['record' => $record->reservable->getKey()])
                                        : null)
                                    ->openUrlInNewTab(),
                            ])
                            ->schema([
                                Flex::make([
                                    ImageEntry::make('avatar')
                                        ->hiddenLabel()
                                        ->circular()
                                        ->imageSize(48)
                                        ->state(fn(?Model $record): ?string => $record?->reservable instanceof User
                                            ? $record->reservable->getFilamentAvatarUrl()
                                            : null)
                                        ->visible(fn(?Model $record): bool => $record?->reservable instanceof User)
                                        ->grow(false),
                                    Grid::make(1)
                                        ->gap(0)
                                        ->schema([
                                            Flex::make([
                                                TextEntry::make('reservable.name')
                                                    ->hiddenLabel()
                                                    ->weight(FontWeight::SemiBold)
                                                    ->size(TextSize::Large)
                                                    ->state(fn(?Model $record): string => $record?->reservable instanceof User
                                                        ? $record->reservable->name
                                                        : ($record?->getDisplayTitle() ?? 'Unknown')),
                                                TextEntry::make('sustaining_badge')
                                                    ->hiddenLabel()
                                                    ->badge()
                                                    ->color('success')
                                                    ->icon('tabler-star')
                                                    ->state('Sustaining')
                                                    ->visible(fn(?Model $record): bool => $record?->reservable instanceof User
                                                        && $record->reservable->isSustainingMember()),
                                            ])->verticallyAlignCenter(),
                                            TextEntry::make('reservable.email')
                                                ->hiddenLabel()
                                                ->icon('tabler-mail')
                                                ->iconColor('gray')
                                                ->copyable()
                                                ->visible(fn(?Model $record): bool => $record?->reservable instanceof User),
                                            TextEntry::make('reservable.phone')
                                                ->hiddenLabel()
                                                ->icon('tabler-phone')
                                                ->iconColor('gray')
                                                ->copyable()
                                                ->visible(fn(?Model $record): bool => $record?->reservable instanceof User && $record->reservable->phone),
                                        ]),
                                ])->verticallyAlignCenter(),
                            ])
                            ->collapsible(),

                        // Payment
                        Section::make('Payment')
                            ->icon('tabler-receipt')
                            ->compact()
                            ->headerActions([
                                Action::make('view_charge')
                                    ->label('View')
                                    ->icon('tabler-external-link')
                                    ->iconButton()
                                    ->color('gray')
                                    ->url(fn(?Model $record): ?string => $record?->charge
                                        ? ChargeResource::getUrl('view', ['record' => $record->charge->getKey()])
                                        : null)
                                    ->openUrlInNewTab(),
                            ])
                            ->schema([
                                Flex::make([
                                    IconEntry::make('charge.status')
                                        ->hiddenLabel()
                                        ->size(IconSize::TwoExtraLarge)
                                        ->grow(false),
                                    Grid::make(1)
                                        ->gap(0)
                                        ->schema([
                                            TextEntry::make('charge.net_amount')
                                                ->hiddenLabel()
                                                ->weight(FontWeight::Bold)
                                                ->size(TextSize::Large)
                                                ->state(fn(?Model $record): string => (string) $record?->charge?->net_amount),
                                            TextEntry::make('cost_breakdown')
                                                ->hiddenLabel()
                                                ->color('gray')
                                                ->state(fn(?Model $record): ?string => $record instanceof RehearsalReservation
                                                    ? $this->formatCostBreakdown($record)
                                                    : null),
                                            TextEntry::make('charge.payment_method')
                                                ->hiddenLabel()
                                                ->icon('tabler-credit-card')
                                                ->iconColor('gray')
                                                ->visible(fn(?Model $record): bool => $record?->charge?->payment_method !== null),
                                        ]),
                                ])->verticallyAlignCenter(),
                            ])
                            ->collapsible()
                            ->visible(fn(?Model $record): bool => $record instanceof RehearsalReservation),


                        // Reservation Details - duplicates hero info with labels for learning
                        Section::make('Reservation Details')
                            ->icon('tabler-calendar-event')
                            ->compact()
                            ->collapsible()
                            ->columnSpanFull()
                            ->schema([
                                Grid::make(['default' => 2, 'lg' => 4])->schema([
                                    TextEntry::make('status')
                                        ->label('Status')
                                        ->badge(),
                                    TextEntry::make('reserved_at')
                                        ->label('Date')
                                        ->date('l, M j, Y'),
                                    TextEntry::make('time_range')
                                        ->label('Time')
                                        ->state(fn(Model $record): string => $record->reserved_at->format('g:i A') . ' – ' . $record->reserved_until->format('g:i A')),
                                    TextEntry::make('hours_used')
                                        ->label('Duration')
                                        ->state(fn(Model $record): string => $this->formatHours($record->hours_used)),
                                ]),
                            ]),

                        // Notes - only if present
                        Section::make('Notes')
                            ->icon('tabler-notes')
                            ->compact()
                            ->schema([
                                TextEntry::make('notes')
                                    ->hiddenLabel()
                                    ->markdown(),
                            ])
                            ->collapsible()
                            ->visible(fn(?Model $record): bool => ! empty($record?->notes)),

                    ])->columnSpan(2),

                // Activity History - span full width if no notes
                Section::make('Activity')
                    ->icon('tabler-history')
                    ->compact()
                    ->contained(false)

                    ->schema([
                        ViewEntry::make('activity_log')
                            ->hiddenLabel()
                            ->view('filament.staff.components.reservation-activity-log')
                            ->state(fn() => Activity::forSubject($this->record)
                                ->with('causer')
                                ->latest()
                                ->get()),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ConfirmReservation::filamentAction()
                ->record($this->record),

            MarkReservationAsPaid::filamentAction()
                ->record($this->record),

            MarkReservationAsComped::filamentAction()
                ->record($this->record),

            CancelReservation::filamentAction()
                ->record($this->record),
        ];
    }
}
