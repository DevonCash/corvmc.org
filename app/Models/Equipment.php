<?php

namespace App\Models;

use App\Data\ContactData;
use App\States\Equipment\EquipmentState;
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

// use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;

/**
 * Represents equipment in the CMC gear lending library.
 *
 * Tracks both donated and loaned equipment with detailed acquisition information
 * and current lending status to members.
 *
 * @property-read int|null $loans_count Aggregate count from withCount('loans')
 */
class Equipment extends Model implements HasMedia
{
    use HasFactory, HasStates, InteractsWithMedia, LogsActivity, SoftDeletes; // , HasRecursiveRelationships;

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
}
