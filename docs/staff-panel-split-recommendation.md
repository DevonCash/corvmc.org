# Staff Panel Split: Timing & Implementation

## Current State Analysis

### Existing Navigation Groups in `/member` Panel

Based on codebase analysis, the member panel currently has these navigation groups:

#### 1. **Admin** (2 resources)
- UserResource - User management
- ActivityLogResource - System activity logs
- **Access**: `can('view users')` permission

#### 2. **Operations** (3 resources)
- ProductionResource - Event production management
- EquipmentLoanResource - Equipment loan tracking
- EquipmentDamageReportResource - Damage reports
- **Access**: Staff/admin visibility

#### 3. **Moderation** (2 resources)
- RevisionResource - Content revision tracking
- ReportResource - Content/user reports
- **Access**: Admin only

#### 4. **Reservations** (1 resource)
- RecurringReservationResource - Recurring reservation management
- **Access**: Admin only for viewing all

#### 5. **Content** (2 resources)
- BylawsResource - Organization bylaws
- SponsorResource - Sponsor management

#### 6. **Member Resources** (6 resources, no explicit group)
- ReservationResource - Practice space bookings
- MemberProfileResource - Member directory
- BandResource - Band profiles
- EquipmentResource - Equipment browsing
- CommunityEventResource - Community events
- ProductionResource - Event listings (public view)

### Current Mixed-Access Pattern

**Problem**: The panel currently mixes member and staff functionality:
- Members see: Reservations, Bands, Events, Equipment
- Staff additionally see: Admin, Operations, Moderation navigation groups
- Uses `shouldRegisterNavigation()` and permission checks to hide staff resources

---

## Why Staff Panel Split Should Happen Now

### 1. **Navigation Clutter**
Current member navigation includes 8+ staff-only resources that are hidden via permissions. This:
- Clutters the navigation configuration
- Makes codebase harder to understand
- Requires complex visibility logic in each resource

### 2. **Different Mental Models**
- **Members** think about: "What can I do for myself/my band?"
  - Book practice space
  - Manage my band profile
  - View community events
  - Borrow equipment

- **Staff** think about: "What needs my attention?"
  - Pending approvals
  - System moderation
  - User management
  - Operations oversight

### 3. **Workflow Conflicts**
Staff performing operations tasks (approving damage reports, managing equipment) are currently mixed with personal tasks (their own reservations, their band profile).

Example: A staff member wants to:
- Check damage reports (staff task)
- Book practice space for their band (member task)

Currently these are mixed in the same navigation, causing cognitive load.

### 4. **Future Module Growth**
Upcoming features will add MORE staff-only resources:
- **Volunteer Management**: Shift approval, hour tracking
- **Merchandise Consignment**: Approval workflow, payout processing
- **Publication System**: Editorial review, content approval
- **Production Services**: Client management, staff scheduling

Without a split, the member panel will become increasingly cluttered.

### 5. **Role-Based Dashboards**
Staff need different dashboards than members:
- **Member Dashboard**: My reservations, my bands, upcoming events
- **Staff Dashboard**: Pending approvals, system health, operations metrics

Currently impossible to optimize without separate panels.

---

## Recommended Split Strategy

### Option A: Two-Panel Split (Recommended)

#### `/member` Panel - Member Experience
**Audience**: All CMC members (including staff when acting as members)

**Resources**:
- Dashboard (personal)
- Reservations (practice space)
- RecurringReservations (own bookings)
- Bands (directory + own band)
- MemberProfiles (directory)
- Equipment (browse + own loans)
- CommunityEvents (public events)
- Productions (public shows)

**Navigation**:
```php
Practice Space
├── Book Practice Room
├── My Reservations
└── Recurring Bookings

Events & Shows
├── Productions
└── Community Events

Equipment
├── Browse Equipment
└── My Loans

Community
├── Member Directory
├── Band Directory

My Account
├── Profile
├── Membership
├── Credits
└── Settings
```

#### `/staff` Panel - Operations Hub
**Audience**: CMC staff (admin, operations, moderators)

**Resources**:
- Dashboard (operations metrics)
- Users (management)
- Reservations (all, approval)
- RecurringReservations (all, management)
- Equipment (full management)
- EquipmentLoans (oversight)
- EquipmentDamageReports (review)
- Productions (management)
- Reports (moderation)
- Revisions (content review)
- ActivityLog (system audit)
- Sponsors (management)
- Bylaws (management)

**Navigation**:
```php
Dashboard
├── Pending Approvals Widget
├── System Health Widget
└── Today's Operations Widget

User Management
├── Users
├── Invitations
└── Roles & Permissions

Operations
├── All Reservations
├── Recurring Reservations
├── Equipment Management
├── Equipment Loans
├── Damage Reports

Content & Moderation
├── Production Management
├── Reports & Moderation
├── Revisions
├── Sponsors
├── Bylaws

System
├── Activity Log
├── Settings
└── Analytics
```

**Access Control**:
```php
// app/Providers/Filament/StaffPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('staff')
        ->path('staff')
        ->authGuard('web')
        ->authMiddleware([
            Authenticate::class,
            EnsureUserIsStaff::class,  // Custom middleware
        ])
        ->colors(['primary' => Color::Slate])
        ->discoverResources(in: app_path('Filament/Staff/Resources'), for: 'App\Filament\Staff\Resources');
}

// app/Http/Middleware/EnsureUserIsStaff.php
public function handle($request, Closure $next)
{
    if (!auth()->user()?->hasAnyRole(['admin', 'staff', 'moderator'])) {
        abort(403, 'Access denied. Staff only.');
    }

    return $next($request);
}
```

### Panel Switcher for Staff

Staff members need easy access to both panels:

```php
// In StaffPanelProvider
->renderHook(
    PanelsRenderHook::USER_MENU_BEFORE,
    fn(): string => view('filament.components.panel-switcher', [
        'panels' => [
            ['name' => 'Member Panel', 'url' => '/member', 'icon' => 'tabler-users'],
            ['name' => 'Staff Panel', 'url' => '/staff', 'icon' => 'tabler-shield', 'current' => true],
        ]
    ])->render()
)
```

Staff can quickly switch between:
- `/member` - Their personal member experience
- `/staff` - Their work/operations tasks

---

## Migration Plan

### Phase 1: Create Staff Panel (1-2 days)
1. Create `app/Providers/Filament/StaffPanelProvider.php`
2. Create `app/Http/Middleware/EnsureUserIsStaff.php`
3. Register panel in `config/app.php`
4. Create basic staff dashboard
5. Add panel switcher component

### Phase 2: Move Admin Resources (2-3 days)
1. Move `UserResource` → `app/Filament/Staff/Resources/Users/`
2. Move `ActivityLogResource` → `app/Filament/Staff/Resources/ActivityLog/`
3. Update namespaces and imports
4. Test access control

### Phase 3: Move Operations Resources (2-3 days)
1. Move equipment management resources
2. Move production management (staff view)
3. Update reservations to have separate member/staff views
4. Test workflows

### Phase 4: Move Moderation Resources (1-2 days)
1. Move `ReportResource`
2. Move `RevisionResource`
3. Test moderation workflows

### Phase 5: Clean Up Member Panel (1 day)
1. Remove `shouldRegisterNavigation()` checks
2. Remove navigation group visibility logic
3. Simplify member navigation
4. Update documentation

**Total: 1.5-2 weeks**

---

## Alternative: Keep Single Panel (Not Recommended)

### Why This Won't Scale

**Current approach**:
```php
public static function shouldRegisterNavigation(): bool
{
    return Auth::user()?->can('view users') ?? false;
}
```

**Problems**:
- Every resource needs this logic
- Navigation config becomes complex
- Can't optimize dashboards per role
- 20+ resources in one panel as features grow
- Confusing for both members and staff

**Future pain points**:
- Volunteer system adds 5+ staff resources
- Merchandise adds 3+ staff resources
- Publications adds 4+ staff resources
- Production services adds 6+ staff resources

That's **33+ resources in one panel** within 12 months.

---

## Comparison: Staff Panel Now vs. Later

### Split Now ✅
- Clean member panel from day one
- Staff workflows optimized early
- Easier to add future features
- Clear mental model for users
- Less technical debt

### Split Later ❌
- Member panel becomes cluttered
- Harder migration (more resources to move)
- User confusion during transition
- Complex refactoring of navigation logic
- Features built with wrong architecture

---

## User Impact

### For Members (No Impact)
- Navigation stays clean and focused
- No change to their experience
- Don't see staff functionality they can't use

### For Staff (Positive Impact)
- Clear separation of work vs. personal tasks
- Optimized staff dashboard
- Faster access to operations tools
- Panel switcher for easy navigation

### For Admins (Much Better)
- Dedicated operations hub
- Better system oversight
- Cleaner navigation
- Room to add advanced tools

---

## Technical Implementation

### Directory Structure
```
app/Filament/
├── Resources/              # Member panel resources
│   ├── Bands/
│   ├── Reservations/
│   ├── MemberProfiles/
│   └── ...
├── Staff/                  # NEW: Staff panel
│   └── Resources/
│       ├── Users/
│       ├── Equipment/
│       ├── Moderation/
│       └── Operations/
└── Pages/
```

### Panel Registration
```php
// config/app.php
'providers' => [
    // ...
    App\Providers\Filament\MemberPanelProvider::class,
    App\Providers\Filament\StaffPanelProvider::class,  // NEW
],
```

### Shared Models & Services
- Models stay in `app/Models/`
- Services stay in `app/Services/`
- Only Resources/Pages move to separate directories

This keeps business logic centralized while separating UI/UX by audience.

---

## Recommendation: Split Now

### Why Now Is The Right Time

1. **Currently manageable**: 8 staff resources to move (not 30+)
2. **Clean slate**: Can architect staff panel properly from start
3. **Future-proof**: Room for 20+ more staff features
4. **User clarity**: Members see member tools, staff see staff tools
5. **Development velocity**: Easier to build new features in correct panel

### Timeline
- **Week 1-2**: Implement staff panel split
- **Week 3**: Add first new feature (Volunteer Management) to staff panel
- **Future**: All new staff features go directly to `/staff`

### Success Metrics
- Member navigation has ≤8 items (currently 15+ with hidden items)
- Staff can switch panels in <2 clicks
- New features take 0 time to "decide which panel"
- Zero permission-based navigation hiding logic

---

## Next Steps

1. **Get approval** for staff panel split
2. **Create StaffPanelProvider** (2 hours)
3. **Move first resource** (UserResource) as proof of concept (3 hours)
4. **Iterate** on remaining resources (1-2 weeks)
5. **Document** panel architecture for future developers

**Estimated total effort**: 1.5-2 weeks
**Long-term benefit**: Scalable architecture for 50+ future resources
