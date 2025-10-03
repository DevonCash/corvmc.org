# Multi-Band Member UX Design

## The Scenario

**Marcus** is a CMC member who is actively involved in 3 bands:
1. **Marcus & The Groove** (lead vocalist, primary band)
2. **Jazz Fusion Collective** (bass player)
3. **Weekend Warriors** (occasional fill-in guitarist)

Each band has different needs:
- Different merchandise catalogs
- Separate EPKs and booking inquiries
- Individual analytics and payouts
- Distinct social media and branding

## The Challenge

How do we let Marcus manage all three bands without:
- ❌ Creating 3 separate accounts
- ❌ Constant re-authentication
- ❌ Confusing navigation
- ❌ Data leakage between bands
- ❌ Permission conflicts

## Recommended Solution: Filament Multi-Tenancy with Band Tenants

### Design Pattern: Native Filament Tenant Switching

**Use Filament v3+'s built-in multi-tenancy system** to handle band context switching. Bands become "tenants" that users can switch between.

---

## Implementation Approach

### 1. Configure Band Model as Tenant

```php
// app/Models/Band.php
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;

class Band extends Model implements HasTenants
{
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'band_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function canAccessTenant(Model $tenant, Panel $panel): bool
    {
        return $this->members->contains(auth()->user());
    }
}
```

### 2. Configure User Model for Multi-Tenancy

```php
// app/Models/User.php
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements HasTenants
{
    public function bands(): BelongsToMany
    {
        return $this->belongsToMany(Band::class, 'band_members')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->bands;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->bands->contains($tenant);
    }
}
```

### 3. Configure Panel with Tenancy

```php
// app/Providers/Filament/MemberPanelProvider.php
use App\Models\Band;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        ->id('member')
        ->path('member')
        ->tenant(Band::class)
        ->tenantProfile(BandProfilePage::class)
        ->tenantRegistration(RegisterBandPage::class)  // Optional
        ->tenantRoutePrefix('band')
        ->colors(['primary' => Color::Amber])
        // ... rest of config
}
```

### 4. Automatic Tenant Switcher (Built-in)

Filament automatically provides a **tenant switcher** in the navbar:

```
┌─────────────────────────────────────────────┐
│ 🎵 CMC   [Marcus & The Groove ▼]    🔔 👤  │ ← Filament's tenant switcher
│─────────────────────────────────────────────│
│                                              │
│  Dashboard                                   │
│  Practice Space                              │
│  Events & Community                          │
│  Equipment                                   │
│  Directory                                   │
│                                              │
│  My Band                                     │
│  ├── Band Profile                            │
│  ├── EPK Manager                             │
│  ├── Merchandise                             │
│  │   ├── Products                            │
│  │   ├── Sales                               │
│  │   └── Payouts                             │
│  └── Analytics                               │
│                                              │
│  My Account                                  │
└─────────────────────────────────────────────┘
```

**Clicking the switcher opens**:
```
┌─────────────────────────────────┐
│ Switch Band                     │
├─────────────────────────────────┤
│ ✓ Marcus & The Groove           │ ← Current tenant
│                                 │
│   Jazz Fusion Collective        │
│                                 │
│   Weekend Warriors              │
└─────────────────────────────────┘
```

### 5. Automatic Resource Scoping

Filament automatically scopes resources to the current tenant:

```php
// app/Filament/Resources/MerchandiseProductResource.php
use Filament\Resources\Resource;

class MerchandiseProductResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'band';

    // Filament automatically:
    // - Filters queries to current tenant (band)
    // - Sets band_id when creating records
    // - Prevents cross-tenant data access
}
```

### 6. Handling Personal vs Band Resources

**Option A: Separate resources for personal/band items**
```php
// Personal reservations (no tenant)
class ReservationResource extends Resource
{
    protected static bool $isScopedToTenant = false;  // Personal resource
}

// Band-specific merchandise (tenant-scoped)
class MerchandiseProductResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'band';
}
```

**Option B: Personal view as null tenant**
```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->tenant(Band::class, nullable: true)  // Allow null tenant for personal view
        // ...
}
```

With nullable tenants:
- No tenant selected = Personal view (reservations, account, etc.)
- Tenant selected = Band context (merchandise, EPK, etc.)

---

## URL Structure with Tenancy

Filament automatically handles tenant-aware URLs:

```
# Personal view (no tenant)
/member                              → Personal dashboard
/member/reservations                 → Personal reservations

# Band context (tenant selected)
/member/band/marcus-and-the-groove              → Band dashboard
/member/band/marcus-and-the-groove/merchandise  → Band merchandise
/member/band/jazz-fusion-collective/epk         → Different band's EPK
```

**Benefits:**
- Clean, RESTful URLs
- Tenant isolation built-in
- Easy to bookmark specific band views
- Automatic tenant switching from URL

---

## Merchandise Management (Multi-Band)

### With Filament Tenancy: Automatic Isolation

**When visiting `/member/band/marcus-and-the-groove/merchandise`**:
- Automatically filtered to Marcus & The Groove only
- Can't see or access other bands' merchandise
- All queries auto-scoped by Filament
- No manual filtering needed

**When visiting `/member/band/jazz-fusion-collective/merchandise`**:
- Automatically switches to Jazz Fusion Collective
- Completely separate catalog
- Different sales data and payouts
- Perfect data isolation

**Personal view (`/member` - no tenant)**:
- Could show overview of all bands
- Or redirect to "select a band" page
- Or show personal (non-band) resources only

---

## EPK & Band Profiles (Multi-Band)

### Tenant-Scoped EPK Management

Each band has completely isolated EPK data:

```php
// app/Filament/Resources/BandEpkResource.php
class BandEpkResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'band';

    // Filament automatically scopes all queries to current tenant
    // No manual filtering needed!
}
```

**Result:**
- `/member/band/marcus-and-the-groove/epk` → Only M&TG's EPK
- `/member/band/jazz-fusion-collective/epk` → Only JFC's EPK
- Booking inquiries automatically filtered to current band
- Can't accidentally access/edit wrong band's EPK

---

## Dashboard Widgets (Multi-Band Context)

### Tenant-Aware Dashboards

```php
// app/Filament/Pages/Dashboard.php
class Dashboard extends Page
{
    protected function getHeaderWidgets(): array
    {
        // Check if we're in a tenant context
        $tenant = Filament::getTenant();

        if ($tenant) {
            // Band-specific dashboard
            return [
                BandUpcomingShowsWidget::class,
                BandMerchandiseSalesWidget::class,
                BandBookingInquiriesWidget::class,
            ];
        }

        // Personal dashboard (no tenant)
        return [
            PersonalReservationsWidget::class,
            UpcomingEventsWidget::class,
            AllBandsOverviewWidget::class,
        ];
    }
}
```

Filament's `Filament::getTenant()` helper automatically returns the current band (or null).

---

## Permission Handling (Multi-Band)

### Tenant-Aware Policies

Filament integrates seamlessly with Laravel policies for tenant permissions:

```php
// app/Policies/MerchandiseProductPolicy.php
class MerchandiseProductPolicy
{
    public function viewAny(User $user): bool
    {
        $tenant = Filament::getTenant();

        // Can view if they're a member of this band
        return $tenant && $user->bands->contains($tenant);
    }

    public function create(User $user): bool
    {
        $tenant = Filament::getTenant();

        if (!$tenant) {
            return false;
        }

        // Check role in THIS band
        $membership = $user->bands()
            ->where('bands.id', $tenant->id)
            ->first();

        return $membership && in_array($membership->pivot->role, ['owner', 'admin']);
    }

    public function update(User $user, MerchandiseProduct $product): bool
    {
        $tenant = Filament::getTenant();

        // Must belong to current tenant AND have permission
        return $product->band_id === $tenant?->id
            && $this->create($user);  // Reuse create permission logic
    }
}
```

### Displaying Role Badges

```php
// Show user's role in current band
use Filament\Facades\Filament;

$tenant = Filament::getTenant();
$membership = auth()->user()->bands()
    ->where('bands.id', $tenant?->id)
    ->first();

$role = $membership?->pivot->role ?? 'none';  // owner, admin, member, or none
```

---

## Benefits of Filament Multi-Tenancy

### ✅ Built-in Features
- **Automatic tenant switcher** in navbar (no custom component needed)
- **URL-based tenant routing** (`/member/band/{slug}`)
- **Query scoping** handled automatically
- **Middleware** for tenant validation (built-in)
- **Tenant ownership** on resource creation

### ✅ Security
- Can't access other tenants' data (enforced at framework level)
- Policies work seamlessly with tenants
- Cross-tenant data leakage prevented by design

### ✅ Developer Experience
- Less custom code to maintain
- Standard Laravel patterns
- Well-documented Filament feature
- Easier onboarding for new developers

### ✅ Edge Cases Handled
- **User leaves band**: Filament's `canAccessTenant()` prevents access
- **Band deleted**: Automatic redirect to tenant selection
- **Invalid tenant in URL**: 404 or redirect to valid tenant

---

## Recommended Implementation

### Phase 1: Configure Tenancy (2-4 hours)
1. Add `HasTenants` interface to User and Band models
2. Implement `getTenants()` and `canAccessTenant()` methods
3. Configure panel with `->tenant(Band::class)`
4. Set tenant ownership on band-scoped resources

### Phase 2: Resource Configuration (2-3 hours)
1. Add `$tenantOwnershipRelationshipName = 'band'` to band resources
2. Set `$isScopedToTenant = false` on personal resources
3. Test automatic query scoping
4. Verify tenant isolation

### Phase 3: Policies & Permissions (3-4 hours)
1. Update policies to use `Filament::getTenant()`
2. Implement role-based permissions per band
3. Add policy checks to resources
4. Test cross-tenant access prevention

### Phase 4: Custom Pages (2-3 hours)
1. Create tenant-aware dashboard widgets
2. Build band profile page
3. Add "all bands" overview for personal view
4. Customize tenant switcher styling (optional)

**Total: 1-2 days** (vs. 5-10 days for custom implementation)

---

## Comparison to Custom Implementation

### ❌ Custom Session-Based Switcher
- Manual query scoping required
- Custom middleware for validation
- Custom switcher component
- More code to maintain
- Potential security gaps

### ✅ Filament Multi-Tenancy (Recommended)
- Built-in query scoping
- Automatic access validation
- Native tenant switcher
- Less code, more maintainable
- Framework-level security

**Recommendation**: Use Filament's native multi-tenancy. It's battle-tested, well-documented, and requires minimal custom code.

---

## Summary

**For Marcus with 3 bands**:

1. **Filament tenant switcher** in navbar (built-in)
2. **URL-based routing**: `/member/band/marcus-and-the-groove`
3. **Automatic data scoping** by tenant (no manual filtering)
4. **Policy-based permissions** per band role
5. **Personal view** at `/member` (no tenant selected)
6. **Clean, RESTful URLs** with tenant slugs
7. **Framework-level security** against cross-tenant access

**Implementation**: ~1-2 days total
**Complexity**: Low (leverages Filament features)
**User Experience**: Excellent (familiar pattern)
**Scalability**: Handles 1 to 100+ bands per user
**Maintainability**: High (less custom code)

This approach uses Filament's built-in multi-tenancy to handle multi-band members with minimal custom code and maximum security.
