<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Services\HourLogService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class SubmitHoursPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-clock-plus';

    protected string $view = 'filament.pages.submit-hours';

    protected static ?string $title = 'Submit Hours';

    protected static ?string $slug = 'volunteer/submit-hours';

    protected static ?string $navigationLabel = 'Submit Hours';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 6;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('volunteer.hours.submit') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('position_id')
                    ->label('Position')
                    ->options(Position::orderBy('title')->pluck('title', 'id'))
                    ->searchable()
                    ->required(),

                DateTimePicker::make('started_at')
                    ->label('Start')
                    ->required()
                    ->native(true)
                    ->seconds(false)
                    ->maxDate(now()),

                DateTimePicker::make('ended_at')
                    ->label('End')
                    ->required()
                    ->native(true)
                    ->seconds(false)
                    ->maxDate(now())
                    ->after('started_at'),

                Textarea::make('notes')
                    ->label('Notes (optional)')
                    ->placeholder('Describe the work you did...')
                    ->rows(3),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $user = User::me();

        try {
            app(HourLogService::class)->submitHours($user, $data);

            $this->form->fill();

            Notification::make()
                ->title('Hours submitted for review')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Could not submit hours')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Current user's submission history.
     */
    public function getSubmissions(): Collection
    {
        $user = User::me();

        return HourLog::where('user_id', $user->id)
            ->whereNotNull('position_id')
            ->whereNull('shift_id')
            ->with(['position', 'reviewer'])
            ->latest()
            ->limit(20)
            ->get();
    }
}
