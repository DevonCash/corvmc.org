<?php

namespace CorvMC\SpaceManagement\Data;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\Production;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Attributes\Validation\After;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class CreateReservationData extends Data
{
    public function __construct(
        #[Required]
        public Model $reserver, // User, Band, or Production
        
        #[Required]
        #[WithCast(DateTimeInterfaceCast::class)]
        #[After('now')]
        public Carbon $startTime,
        
        #[Required]
        #[WithCast(DateTimeInterfaceCast::class)]
        public Carbon $endTime,
        
        public ?string $notes = null,
        public bool $skipConflictCheck = false,
        public bool $isRecurring = false,
        public ?int $recurringSeriesId = null,
    ) {}

    public function getDurationInHours(): float
    {
        return $this->startTime->diffInMinutes($this->endTime) / 60;
    }

    public function getDurationInBlocks(): int
    {
        $minutesPerBlock = config('space-management.minutes_per_block', 30);
        return (int) ceil($this->startTime->diffInMinutes($this->endTime) / $minutesPerBlock);
    }

    public function getResponsibleUser(): ?User
    {
        if ($this->reserver instanceof User) {
            return $this->reserver;
        }

        // For bands, get the owner
        if (method_exists($this->reserver, 'owner')) {
            return $this->reserver->owner;
        }

        // For productions, get the organizer
        if ($this->reserver instanceof Production) {
            return $this->reserver->organizer;
        }

        return null;
    }

    public static function rules(): array
    {
        return [
            'startTime' => ['required', 'date', 'after:now'],
            'endTime' => ['required', 'date', 'after:startTime'],
            'notes' => ['nullable', 'string', 'max:500'],
            'skipConflictCheck' => ['boolean'],
            'isRecurring' => ['boolean'],
            'recurringSeriesId' => ['nullable', 'integer', 'exists:recurring_reservation_series,id'],
        ];
    }
}