# Current Features: Panel Mapping Recommendation

## Current State Analysis

Based on the existing codebase, you currently have a **single `/member` panel** with the following features:

### Existing Models & Resources
- User & Authentication
- MemberProfile (member directory)
- Band & BandMember
- Production (events/shows)
- CommunityEvent
- Reservation (practice space)
- RecurringReservation
- Equipment, EquipmentLoan, EquipmentDamageReport
- Subscription (Stripe/Cashier)
- UserCredit, CreditTransaction, CreditAllocation
- PromoCode, PromoCodeRedemption
- Sponsor
- Transaction (implied from CLAUDE.md)
- Trust system (UserTrustBalance, TrustTransaction, TrustAchievement)
- Activity logging & Revisions
- Reports (content reporting)
- StaffProfile
- Invitation system

### Current Services (Already Built)
- UserSubscriptionService
- UserInvitationService
- ReservationService
- RecurringReservationService
- ProductionService
- CreditService
- MemberBenefitsService
- BandService
- MemberProfileService
- CacheService
- PaymentService
- CalendarService
- EquipmentService
- TrustService

---

## Recommended Panel Structure for Current Features

### Keep: Single `/member` Panel (For Now)
**Recommendation**: Don't split yet. Your current features work well in a unified member panel.

**Why?**
1. ✅ All features serve CMC members
2. ✅ No external users yet (no production clients, no sponsors as separate users)
3. ✅ Cohesive user experience
4. ✅ Less complexity to maintain
5. ✅ Faster development without panel overhead

### Current `/member` Panel Organization

```
Dashboard
├── Overview Widget
├── Upcoming Reservations Widget
├── My Credits Widget
└── Community Activity Widget

Practice Space
├── Reservations
├── Recurring Reservations
└── Calendar

Events & Community
├── Productions (Shows)
├── Community Events
└── Event Calendar

Equipment
├── Browse Equipment
├── My Loans
├── Damage Reports

Directory
├── Member Profiles
├── Band Directory
└── Staff Directory

My Account
├── Profile
├── Membership & Subscription
├── Credits & Transactions
├── Billing
└── Settings

Admin Section (role-based visibility)
├── Users
├── Subscriptions
├── All Reservations
├── Equipment Management
├── Sponsors
├── Reports & Moderation
├── Activity Log
└── System Settings
```

---

## When to Split Into Multiple Panels

### Trigger Point 1: External Clients (Future)
**When you implement**: Production Services Module
**Create**: `/business` panel for external production clients

**Why Split Then?**:
- External users who aren't CMC members
- Different workflow (B2B vs. community)
- Limited feature access (their projects only)
- Professional branding needed

### Trigger Point 2: Content Creators (Future)
**When you implement**: Publications Module
**Create**: `/create` panel for writers/designers

**Why Split Then?**:
- Editorial workflow is distinct
- May include non-member contributors
- Different mental model (submission → review → publish)
- Portfolio and earnings focus

### Trigger Point 3: Heavy Staff Usage (Maybe)
**When you implement**: Multiple complex admin modules
**Consider**: `/staff` panel for operations

**Why Maybe?**:
- If admin section in `/member` becomes cluttered
- If staff need different dashboards/workflows
- If you add staff who aren't members
- Currently: Staff ARE members, so `/member` works fine

---

## Recommended Navigation Structure (Current Features)

### For Regular Members
```php
// Simple, focused navigation
Navigation::make([
    NavigationGroup::make('Dashboard')
        ->items([...]),

    NavigationGroup::make('Practice Space')
        ->items([
            NavigationItem::make('Book Practice Room')
                ->url(ReservationResource::getUrl('create')),
            NavigationItem::make('My Reservations')
                ->url(ReservationResource::getUrl()),
            NavigationItem::make('Recurring Bookings')
                ->url(RecurringReservationResource::getUrl()),
        ]),

    NavigationGroup::make('Events')
        ->items([
            NavigationItem::make('Shows & Concerts')
                ->url(ProductionResource::getUrl()),
            NavigationItem::make('Community Events')
                ->url(CommunityEventResource::getUrl()),
            NavigationItem::make('Calendar')
                ->url('/calendar'),
        ]),

    NavigationGroup::make('Equipment')
        ->items([
            NavigationItem::make('Browse')
                ->url(EquipmentResource::getUrl()),
            NavigationItem::make('My Loans')
                ->url(EquipmentLoanResource::getUrl()),
        ]),

    NavigationGroup::make('Community')
        ->items([
            NavigationItem::make('Members')
                ->url(MemberProfileResource::getUrl()),
            NavigationItem::make('Bands')
                ->url(BandResource::getUrl()),
        ]),
]);
```

### For Band Members (Additional)
```php
// Add band section if user is in a band
if (auth()->user()->bands->count() > 0) {
    NavigationGroup::make('My Band')
        ->items([
            NavigationItem::make('Band Profile')
                ->url('/bands/' . auth()->user()->bands->first()->slug),
            NavigationItem::make('Band Members')
                ->url('/bands/' . auth()->user()->bands->first()->slug . '/members'),
        ])
        ->visible(fn() => auth()->user()->isBandMember());
}
```

### For Staff/Admin (Additional)
```php
// Add admin section for staff
if (auth()->user()->hasRole(['admin', 'staff'])) {
    NavigationGroup::make('Administration')
        ->items([
            NavigationItem::make('Users')
                ->url(UserResource::getUrl()),
            NavigationItem::make('All Reservations')
                ->url(ReservationResource::getUrl() . '?all=true'),
            NavigationItem::make('Equipment Management')
                ->url(EquipmentResource::getUrl() . '/manage'),
            NavigationItem::make('Subscriptions')
                ->url('/admin/subscriptions'),
            NavigationItem::make('Sponsors')
                ->url(SponsorResource::getUrl()),
            NavigationItem::make('Reports')
                ->url(ReportResource::getUrl()),
            NavigationItem::make('Activity Log')
                ->url(ActivityLogResource::getUrl()),
        ])
        ->collapsed()
        ->visible(fn() => auth()->user()->isStaff());
}
```

---

## Optimization: Smart Dashboard Widgets

Instead of splitting panels, use **contextual widgets** to personalize the experience:

```php
class MemberDashboard extends Page
{
    public function getWidgets(): array
    {
        $widgets = [
            // Everyone gets these
            UpcomingReservationsWidget::class,
            CommunityEventsWidget::class,
            MyCreditBalanceWidget::class,
        ];

        // Band members get band stats
        if (auth()->user()->isBandMember()) {
            $widgets[] = BandUpcomingShowsWidget::class;
            // Future: BandMerchandiseSalesWidget::class
            // Future: BandBookingInquiriesWidget::class
        }

        // Sustaining members get special widgets
        if (auth()->user()->isSustainingMember()) {
            $widgets[] = MemberBenefitsWidget::class;
        }

        // Staff get admin widgets
        if (auth()->user()->isStaff()) {
            $widgets[] = PendingApprovalsWidget::class;
            $widgets[] = SystemHealthWidget::class;
        }

        // Active volunteers get volunteer widgets
        if (auth()->user()->isActiveVolunteer()) {
            // Future: UpcomingVolunteerShiftsWidget::class
        }

        return $widgets;
    }
}
```

---

## Resource Organization (Current Panel)

### Group by Audience Access Level

#### Public/Member Resources (All members can access)
```
app/Filament/Resources/
├── Bands/
│   └── BandResource.php
├── CommunityEvents/
│   └── CommunityEventResource.php
├── Equipment/
│   ├── EquipmentResource.php (browse only)
│   └── EquipmentLoans/
│       └── EquipmentLoanResource.php (my loans)
├── MemberProfiles/
│   └── MemberProfileResource.php
├── Productions/
│   └── ProductionResource.php
└── Reservations/
    ├── ReservationResource.php
    └── RecurringReservations/
        └── RecurringReservationResource.php
```

#### Admin Resources (Staff/admin only)
```
app/Filament/Resources/
├── Users/
│   └── UserResource.php
├── Equipment/
│   ├── EquipmentResource.php (full management)
│   └── EquipmentDamageReports/
│       └── EquipmentDamageReportResource.php
├── Sponsors/
│   └── SponsorResource.php
├── Reports/
│   └── ReportResource.php
├── ActivityLog/
│   └── ActivityLogResource.php
└── Revisions/
    └── RevisionResource.php
```

---

## Access Control Strategy

### Use Policies, Not Separate Panels

```php
// app/Policies/ReservationPolicy.php
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All members can view their reservations
    }

    public function viewAll(User $user): bool
    {
        return $user->hasRole(['admin', 'staff']); // Only staff see all
    }

    public function create(User $user): bool
    {
        return $user->isMember(); // Must be active member
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        // Own reservations or staff
        return $user->id === $reservation->user_id || $user->isStaff();
    }
}
```

### Resource-Level Visibility

```php
// In Resource class
public static function shouldRegisterNavigation(): bool
{
    // Only show to staff
    return auth()->user()?->isStaff();
}

public static function getNavigationGroup(): ?string
{
    // Group admin resources
    return auth()->user()?->isStaff() ? 'Administration' : null;
}
```

---

## Recommended Next Steps for Current App

### 1. Organize Current Resources (1-2 days)
**Action**: Group resources into logical navigation sections
- Practice Space group
- Events group
- Equipment group
- Community group
- Administration group (staff only)

**Benefit**: Clearer navigation without multi-panel complexity

### 2. Implement Smart Dashboard (2-3 days)
**Action**: Create contextual dashboard with role-based widgets
- Base widgets for all members
- Additional widgets based on roles/status
- Staff widgets for admin overview

**Benefit**: Personalized experience in single panel

### 3. Refine Access Control (1-2 days)
**Action**: Audit and implement proper policies
- Member vs. staff access levels
- Resource-specific permissions
- Admin section visibility

**Benefit**: Secure, appropriate access without panel split

### 4. Add Missing Core Features (As Designed)
**Priority Order**:
1. **Credits System** (already have models) - 1-2 weeks
2. **Community Programs** - 2-3 weeks
3. **RSVP System** - 1-2 weeks
4. **Volunteer Management** - 2-3 weeks

**Benefit**: Complete core member experience before adding panels

### 5. When Ready, Add First Separate Panel
**First Panel to Split**: `/create` or `/business` (when you implement those modules)
**Timeline**: 6-12 months from now
**Why Wait**: Build stable core first, then expand

---

## Quick Wins (This Week)

### 1. Navigation Grouping
```php
// app/Providers/Filament/MemberPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('member')
        ->path('member')
        ->colors(['primary' => Color::Amber])
        ->navigationGroups([
            'Practice Space',
            'Events & Community',
            'Equipment',
            'Directory',
            'My Account',
            'Administration', // Staff only, collapsed by default
        ]);
}
```

### 2. Contextual User Menu
```php
->userMenuItems([
    'profile' => MenuItem::make()
        ->label('My Profile')
        ->url(fn() => '/member/profile/' . auth()->user()->id)
        ->icon('heroicon-o-user'),

    'subscription' => MenuItem::make()
        ->label('Membership')
        ->url('/member/subscription')
        ->icon('heroicon-o-credit-card'),

    'credits' => MenuItem::make()
        ->label(fn() => 'Credits: ' . auth()->user()->totalCredits())
        ->url('/member/credits')
        ->icon('heroicon-o-star'),

    // Staff-only items
    'admin' => MenuItem::make()
        ->label('Admin Panel')
        ->url('/member#admin')
        ->icon('heroicon-o-cog')
        ->visible(fn() => auth()->user()->isStaff()),
])
```

### 3. Dashboard Personalization
```php
// app/Filament/Pages/Dashboard.php
protected function getHeaderWidgets(): array
{
    $widgets = [];

    // Everyone
    $widgets[] = WelcomeWidget::class;

    // Based on context
    if (auth()->user()->hasUpcomingReservations()) {
        $widgets[] = UpcomingReservationsWidget::class;
    }

    if (auth()->user()->isBandMember()) {
        $widgets[] = BandShowsWidget::class;
    }

    if (auth()->user()->isStaff()) {
        $widgets[] = AdminOverviewWidget::class;
    }

    return $widgets;
}
```

---

## Summary: Current Recommendation

### ✅ Keep Single `/member` Panel
**Why**: All current features serve the same audience (CMC members)

### ✅ Use Smart Navigation
**How**: Contextual groups, role-based visibility, collapsed admin section

### ✅ Implement Contextual Dashboards
**How**: Different widgets based on user roles and status

### ✅ Focus on Core Features First
**Priority**:
1. Credits system (foundation)
2. Community programs (engagement)
3. RSVP system (participation)
4. Volunteer management (operations)

### ⏳ Plan for Future Panels
**When to Split**:
- `/business` - When you add Production Services (external clients)
- `/create` - When you add Publications (editorial workflow)
- `/staff` - If admin section becomes overwhelming (maybe never needed)

### 📊 Timeline
- **Now-6 months**: Build core features in single panel
- **6-12 months**: Add first separate panel (business or create)
- **12-18 months**: Full multi-panel architecture if needed

**Bottom line**: Your current single-panel approach is correct. Don't over-engineer yet. Split when you have external users or distinctly different workflows.
