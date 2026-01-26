<?php

namespace CorvMC\Equipment\Models;

use CorvMC\Membership\Data\ContactData;
use App\Models\User;
use CorvMC\Equipment\States\Equipment\EquipmentState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\ModelStates\HasStates;

/**
 * Represents equipment in the CMC gear lending library.
 *
 * Tracks both donated and loaned equipment with detailed acquisition information
 * and current lending status to members.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string|null $description
 * @property string $condition
 * @property string $acquisition_type
 * @property int|null $provider_id
 * @property \Spatie\LaravelData\Contracts\BaseData|\Spatie\LaravelData\Contracts\TransformableData|null $provider_contact
 * @property \Illuminate\Support\Carbon $acquisition_date
 * @property \Illuminate\Support\Carbon|null $return_due_date
 * @property string|null $acquisition_notes
 * @property string $ownership_status
 * @property string $status
 * @property numeric|null $estimated_value
 * @property string|null $location
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $parent_equipment_id
 * @property bool $can_lend_separately
 * @property bool $is_kit
 * @property int $sort_order
 * @property EquipmentState $state
 * @property bool $loanable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Equipment> $children
 * @property-read int|null $children_count
 * @property-read EquipmentLoan|null $currentLoan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EquipmentDamageReport> $damageReports
 * @property-read int|null $damage_reports_count
 * @property-read bool $is_available
 * @property-read bool $is_checked_out
 * @property-read bool $is_overdue
 * @property-read string $provider_display
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EquipmentLoan> $loans
 * @property-read int|null $loans_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, EquipmentDamageReport> $openDamageReports
 * @property-read int|null $open_damage_reports_count
 * @property-read Equipment|null $parent
 * @property-read User|null $provider
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment available()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment components()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment donated()
 * @method static \CorvMC\Equipment\Database\Factories\EquipmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment kits()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment ofType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment onLoanToCmc()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment orWhereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment orWhereState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment popular()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment standalone()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereAcquisitionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereAcquisitionNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereAcquisitionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereCanLendSeparately($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereCondition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereEstimatedValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereIsKit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereLoanable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereNotState(string $column, $states)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereOwnershipStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereParentEquipmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereProviderContact($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereReturnDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereSerialNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Equipment withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Equipment extends Model implements HasMedia
{
    use HasFactory, HasStates, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'brand',
        'model',
        'serial_number',
        'description',
        'condition',
        'acquisition_type',
        'provider_id',
        'provider_contact',
        'acquisition_date',
        'return_due_date',
        'acquisition_notes',
        'ownership_status',
        'status',
        'estimated_value',
        'location',
        'notes',
        'parent_equipment_id',
        'can_lend_separately',
        'is_kit',
        'loanable',
        'sort_order',
        'state',
    ];

    protected $casts = [
        'provider_contact' => ContactData::class,
        'acquisition_date' => 'date',
        'return_due_date' => 'date',
        'estimated_value' => 'decimal:2',
        'can_lend_separately' => 'boolean',
        'is_kit' => 'boolean',
        'loanable' => 'boolean',
        'state' => EquipmentState::class,
    ];

    protected $attributes = [
        'condition' => 'good',
        'acquisition_type' => 'donated',
        'ownership_status' => 'cmc_owned',
        'status' => 'available',
        'can_lend_separately' => true,
        'is_kit' => false,
        'loanable' => true,
        'sort_order' => 0,
    ];

    /**
     * Get the user who provided this equipment (donor/lender).
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Get the parent equipment (for kit components).
     */
    public function parent()
    {
        return $this->belongsTo(Equipment::class, 'parent_equipment_id');
    }

    /**
     * Get all components of this equipment (for kits).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Equipment::class, 'parent_equipment_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Alias for children relationship.
     */
    public function components()
    {
        return $this->children();
    }

    /**
     * Get all loans for this equipment.
     */
    public function loans()
    {
        return $this->hasMany(EquipmentLoan::class);
    }

    /**
     * Get all damage reports for this equipment.
     */
    public function damageReports()
    {
        return $this->hasMany(EquipmentDamageReport::class);
    }

    /**
     * Get open damage reports for this equipment.
     */
    public function openDamageReports()
    {
        return $this->hasMany(EquipmentDamageReport::class)->open();
    }

    /**
     * Get the current active loan for this equipment.
     */
    public function currentLoan(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EquipmentLoan::class)
            ->active()
            ->latest('reserved_from');
    }

    /**
     * Check if equipment is available for checkout.
     * Equipment must be loanable (setting), have available status, and not have active loan.
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->loanable &&
            $this->status === 'available' &&
            ! $this->currentLoan;
    }

    /**
     * Check if equipment is currently checked out.
     */
    public function getIsCheckedOutAttribute(): bool
    {
        return $this->status === 'checked_out' || $this->currentLoan;
    }

    /**
     * Check if equipment is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->currentLoan && $this->currentLoan->is_overdue;
    }

    /**
     * Get display name for the equipment provider.
     */
    public function getProviderDisplayAttribute(): string
    {
        if ($this->provider) {
            return $this->provider->name;
        }

        if ($this->provider_contact && $this->provider_contact->email) {
            return $this->provider_contact->email;
        }

        return 'Unknown';
    }

    /**
     * Check if equipment is donated.
     */
    public function isDonated(): bool
    {
        return $this->acquisition_type === 'donated';
    }

    /**
     * Check if equipment is on loan to CMC.
     */
    public function isOnLoanToCmc(): bool
    {
        return $this->acquisition_type === 'loaned_to_us';
    }

    /**
     * Check if equipment was purchased.
     */
    public function isPurchased(): bool
    {
        return $this->acquisition_type === 'purchased';
    }

    /**
     * Check if equipment needs to be returned to owner.
     */
    public function needsReturn(): bool
    {
        return $this->isOnLoanToCmc() &&
            $this->ownership_status === 'on_loan_to_cmc' &&
            $this->return_due_date?->isPast();
    }

    public function scopePopular($query)
    {
        return $query->withCount('loans')
            ->orderByDesc('loans_count');
    }

    /**
     * Scope for available equipment.
     * Equipment that is marked as loanable and currently available.
     */
    public function scopeAvailable($query)
    {
        return $query->where('loanable', true)
            ->where('status', 'available');
    }

    /**
     * Scope for equipment by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for donated equipment.
     */
    public function scopeDonated($query)
    {
        return $query->where('acquisition_type', 'donated');
    }

    /**
     * Scope for equipment on loan to CMC.
     */
    public function scopeOnLoanToCmc($query)
    {
        return $query->where('acquisition_type', 'loaned_to_us');
    }

    /**
     * Scope for parent equipment (kits).
     */
    public function scopeKits($query)
    {
        return $query->whereNull('parent_equipment_id')->where('is_kit', true);
    }

    /**
     * Scope for standalone equipment (not part of a kit).
     */
    public function scopeStandalone($query)
    {
        return $query->whereNull('parent_equipment_id')->where('is_kit', false);
    }

    /**
     * Scope for components (part of a kit).
     */
    public function scopeComponents($query)
    {
        return $query->whereNotNull('parent_equipment_id');
    }

    /**
     * Check if this equipment is part of a kit.
     */
    public function isComponent(): bool
    {
        return ! is_null($this->parent_equipment_id);
    }

    /**
     * Check if all required components of a kit are available.
     */
    public function areAllComponentsAvailable(): bool
    {
        if (! $this->is_kit) {
            return $this->is_available;
        }

        return $this->children
            ->where('can_lend_separately', false) // Required components
            ->every(fn ($component) => $component->is_available);
    }

    /**
     * Get available components of a kit.
     */
    public function getAvailableComponents()
    {
        if (! $this->is_kit) {
            return collect();
        }

        return $this->children->filter(fn ($component) => $component->is_available);
    }

    /**
     * Get checked out components of a kit.
     */
    public function getCheckedOutComponents()
    {
        if (! $this->is_kit) {
            return collect();
        }

        return $this->children->filter(fn ($component) => $component->is_checked_out);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'condition', 'location', 'ownership_status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "Equipment {$eventName}");
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \CorvMC\Equipment\Database\Factories\EquipmentFactory
    {
        return \CorvMC\Equipment\Database\Factories\EquipmentFactory::new();
    }
}
