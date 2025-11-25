<?php

namespace App\Filament\Kiosk\Pages;

use App\Actions\CheckIns\CheckOutUser;
use App\Models\CheckIn;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\{Size, TextSize};
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class CheckOutMember extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-left-circle';

    protected string $view = 'filament.kiosk.pages.check-out-member';

    protected static ?string $title = 'Check Out Member';

    protected static ?string $navigationLabel = 'Check Out';

    protected static ?int $navigationSort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                CheckIn::query()
                    ->currentlyCheckedIn()
                    ->with(['user', 'checkable'])
                    ->orderBy('checked_in_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable()
                    ->size(TextSize::Large),

                Tables\Columns\TextColumn::make('checked_in_at')
                    ->label('Checked In')
                    ->since()
                    ->sortable()
                    ->size(TextSize::Medium),

                Tables\Columns\TextColumn::make('checkable_type')
                    ->label('Activity')
                    ->formatStateUsing(function ($state, CheckIn $record) {
                        return match ($state) {
                            'App\\Models\\Reservation' => 'Practice • ' . $record->checkable->time_range,
                            'App\\Models\\VolunteerShift' => 'Volunteering',
                            'App\\Models\\Event' => 'Event • ' . $record->checkable->title,
                            default => class_basename($state),
                        };
                    }),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('checkout')
                    ->label('Check Out')
                    ->icon('heroicon-o-arrow-left-circle')
                    ->color('warning')
                    ->size(Size::Large)
                    ->requiresConfirmation()
                    ->action(function (CheckIn $record) {
                        try {
                            CheckOutUser::run($record);

                            Notification::make()
                                ->success()
                                ->title('Checked Out')
                                ->body("{$record->user->name} has been checked out.")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Check-Out Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No one is currently checked in')
            ->emptyStateDescription('When members check in, they will appear here.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->poll('30s');
    }
}
