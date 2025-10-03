# CMC Platform: Audience-Based Architecture

## Overview

An alternative architectural approach that organizes the platform around distinct user audiences rather than functional modules. This creates purpose-built experiences for each user type while maintaining shared infrastructure.

## Audience Analysis

### Primary Audiences

#### 1. **General Members** (Largest Group)
**Who**: Individual CMC members who participate in community activities
**Primary Needs**:
- Access practice space
- Attend events and programs
- Connect with other musicians
- Track their membership and credits
- Discover opportunities

**Current Pain Points**:
- Need simple, focused interface
- Don't need band/business features
- Want quick access to reservations and events
- Need clear membership status

---

#### 2. **Band Members** (Active Musicians)
**Who**: Members actively in bands, seeking professional tools
**Primary Needs**:
- Manage band presence
- Sell merchandise
- Create EPKs for booking
- Track band finances
- Promote shows

**Current Pain Points**:
- Need professional tools
- Want revenue tracking
- Require public-facing features
- Need booking tools

---

#### 3. **Staff & Volunteers** (Operations)
**Who**: CMC staff, volunteer coordinators, production crew
**Primary Needs**:
- Manage operations
- Coordinate volunteers
- Track equipment
- Handle member services
- Monitor community health

**Current Pain Points**:
- Need operational dashboards
- Require approval workflows
- Want oversight capabilities
- Need scheduling tools

---

#### 4. **Business & Partners** (External)
**Who**: External clients, sponsors, partner organizations
**Primary Needs**:
- Book production services
- Sponsor publications/events
- Partner on programs
- Access professional services

**Current Pain Points**:
- Don't need member features
- Want professional interface
- Require invoicing/contracts
- Need client portal

---

#### 5. **Content Creators** (Contributors)
**Who**: Writers, designers, photographers, artists
**Primary Needs**:
- Submit and manage content
- Track contributions and payments
- Access creative tools
- Collaborate with others

**Current Pain Points**:
- Need specialized workflows
- Want portfolio tracking
- Require editorial tools
- Need payment tracking

---

## Proposed Panel Structure

### Panel 1: `/member` - Member Portal
**Color**: Amber (warm, welcoming)
**Audience**: General Members + Band Members
**Tag Line**: "Your Music Community Home"

#### Navigation for General Members
```
Dashboard
├── My Overview
├── Upcoming Events
└── Recent Activity

Practice Space
├── Book Practice Room
├── My Reservations
└── Recurring Bookings

Community
├── Events Calendar
├── Programs & Meetups
├── Member Directory
└── Volunteer Opportunities

My Account
├── Profile
├── Membership & Billing
├── Credits & Payments
└── Settings
```

#### Additional Navigation for Band Members
```
My Band (Contextual - appears if user is in a band)
├── Band Profile
├── EPK Manager
├── Merchandise
│   ├── Products
│   ├── Sales
│   └── Payouts
├── Booking Inquiries
└── Analytics
```

**Why Combined?**:
- Band members ARE members first
- Contextual navigation (band section only appears if relevant)
- Single login, seamless experience
- Band features enhance rather than complicate

**Key Features**:
- Smart dashboard (shows relevant info based on roles)
- Context-aware navigation
- Progressive disclosure (advanced features hidden until needed)
- Mobile-optimized

---

### Panel 2: `/staff` - Operations Hub
**Color**: Blue (professional, trustworthy)
**Audience**: CMC Staff, Volunteer Coordinators, Production Crew
**Tag Line**: "Community Operations Center"

#### Navigation
```
Dashboard
├── Daily Overview
├── Pending Approvals
└── Key Metrics

Member Services
├── Member Directory
├── Subscriptions
├── Transactions
├── Support Tickets

Events & Programs
├── Productions
├── Community Events
├── Programs
├── RSVPs & Attendance

Operations
├── Volunteers
│   ├── Opportunities
│   ├── Shifts
│   └── Hours
├── Equipment
│   ├── Inventory
│   ├── Loans
│   └── Maintenance
├── Reservations
│   ├── Calendar
│   ├── Approvals
│   └── Conflicts

Content & Marketing
├── Publications
│   ├── Articles
│   ├── Submissions
│   └── Contributors
├── Posters
│   ├── Orders
│   ├── Community Listings
│   └── Distribution

Merchandise (Admin View)
├── All Products
├── All Sales
├── Payouts
└── Inventory

Reports & Analytics
├── Financial Reports
├── Member Analytics
├── Event Performance
└── Export Data
```

**Why Separate?**:
- Staff need aggregated view across all members
- Different permissions model (elevated access)
- Operational dashboards and reports
- Admin workflows (approvals, overrides)

**Key Features**:
- Approval queues
- System-wide search
- Bulk actions
- Admin overrides
- Advanced reporting

---

### Panel 3: `/business` - Professional Services Portal
**Color**: Slate (professional, business-like)
**Audience**: External Clients, Production Service Clients
**Tag Line**: "Professional Event Production"

#### Navigation
```
Dashboard
├── My Projects
├── Upcoming Events
└── Invoices Due

Projects
├── Active Projects
├── Past Projects
└── Request Quote

Services
├── Browse Services
├── Sound Packages
├── Booking Coordination
└── Custom Requests

Account
├── Company Profile
├── Contacts
├── Billing Information
└── Documents
```

**Why Separate?**:
- External users (not CMC members)
- B2B experience vs. community experience
- Different terminology and workflows
- Professional branding
- Limited access (their projects only)

**Key Features**:
- Client self-service portal
- Quote requests
- Contract signing
- Invoice payment
- Project timeline view
- File sharing

**Staff View** (within `/staff` panel):
```
Production Services
├── Client Management
├── Inquiry Pipeline
├── Project Calendar
├── Quotes & Contracts
├── Staff Scheduling
└── Equipment Assignments
```

---

### Panel 4: `/partners` - Sponsor & Partner Portal
**Color**: Purple (creative, supportive)
**Audience**: Sponsors, Partner Organizations, Funders
**Tag Line**: "Supporting Local Music"

#### Navigation
```
Dashboard
├── Sponsorship Overview
├── Active Campaigns
└── Impact Metrics

Sponsorships
├── Active Sponsorships
├── Invoices
└── Sponsorship Opportunities

Impact & Analytics
├── Event Attendance
├── Audience Reach
├── Media Mentions
└── ROI Reports

Resources
├── Logos & Assets
├── Press Kit
└── Recognition

Account
├── Organization Profile
├── Billing
└── Contacts
```

**Why Separate?**:
- External organizations
- Marketing/sponsorship focus
- ROI and impact tracking
- Different value proposition
- Can integrate with publications, posters, events

**Staff View** (within `/staff` panel):
```
Partners & Sponsors
├── Sponsor Directory
├── Sponsorship Packages
├── Invoice Management
├── Impact Reporting
└── Recognition Tracking
```

---

### Panel 5: `/create` - Creator Studio
**Color**: Indigo (creative, artistic)
**Audience**: Writers, Designers, Photographers, Template Artists
**Tag Line**: "Create. Contribute. Earn."

#### Navigation
```
Dashboard
├── My Contributions
├── Earnings Overview
└── Opportunities

Content
├── My Articles
├── Pitches
├── Submissions
└── Assignments

Design Work
├── Poster Templates
├── Template Sales
└── Commission Requests

Portfolio
├── Published Work
├── Analytics
└── Recognition

Earnings
├── Payment History
├── Pending Payments
└── Tax Documents
```

**Why Separate?**:
- Creative workflow vs. consumption
- Portfolio and earnings focus
- Submission and review process
- Different mental model
- Can be members OR external contributors

**Staff View** (within `/staff` panel):
```
Content Management
├── Editorial
│   ├── Pitch Review
│   ├── Article Editing
│   └── Publishing Queue
├── Design Assets
│   ├── Template Approval
│   ├── Usage Tracking
│   └── Artist Payments
└── Contributor Management
```

---

## Comparison: Modular vs. Audience-Based

### Modular Approach (Previous Proposal)
```
5 Panels by Function:
- /member (general features)
- /publications (editorial)
- /store (merchandise)
- /marketing (posters)
- /production (services)
```

**Pros**:
- Clear functional boundaries
- Easy to develop independently
- Specialized workflows

**Cons**:
- Users may need multiple panels
- Context switching
- Harder to discover features
- Role confusion

### Audience-Based Approach (This Proposal)
```
5 Panels by Audience:
- /member (members + bands)
- /staff (operations)
- /business (external clients)
- /partners (sponsors)
- /create (creators)
```

**Pros**:
- Users stay in "their" panel
- Contextual experiences
- Clearer mental model
- Better feature discovery

**Cons**:
- Some functional overlap
- More complex navigation logic
- Requires role detection

---

## Hybrid Approach (Recommended)

Combine the best of both: audience-based panels with modular backend.

### Panel Structure
```
User-Facing Panels (Audience-Based):
├── /member - Members & Bands
├── /business - External Clients
├── /partners - Sponsors
└── /create - Creators

Operations Panel (Staff):
└── /staff - All administrative functions
```

### Backend Structure (Modular)
```
Modules (Laravel packages):
├── Core
├── Finance
├── Events
├── Reservations
├── Programs
├── RSVP
├── Volunteers
├── Publications
├── Merchandise
├── BandProfiles
├── Posters
├── ProductionServices
└── Equipment
```

### How It Works

**Members see**: Integrated experience
```
/member
├── Dashboard (personalized based on roles)
├── Practice Space (Reservations module)
├── Events (Events + RSVP modules)
├── Community (Programs + Volunteers)
├── My Band (BandProfiles + Merchandise modules)
│   └── Band switcher if in multiple bands
└── Account (Finance module)
```

**Staff see**: Everything, organized operationally
```
/staff
├── Dashboard (aggregated metrics)
├── Member Services (Core + Finance)
├── Events & Programs (Events + Programs + RSVP)
├── Operations (Volunteers + Equipment + Reservations)
├── Content (Publications + Posters)
├── Merchandise (admin view)
├── Production Services (ProductionServices module)
└── Partners (Sponsors)
```

**External Clients see**: Only what they need
```
/business
├── Production Services (limited view)
└── Their projects and invoices

/partners
├── Sponsorships
└── Impact reports

/create
├── Submissions
└── Earnings
```

---

## Navigation Strategy

### Contextual Menus

```php
// Member panel - dynamic navigation
public function getNavigation(): array
{
    $nav = [
        'Dashboard',
        'Practice Space',
        'Community',
        'My Account',
    ];

    // Add band section if user is in any bands
    if (auth()->user()->bands->count() > 0) {
        $nav[] = 'My Band';
    }

    // Add creator section if user is a contributor
    if (auth()->user()->hasRole('writer|designer')) {
        $nav[] = 'Creator Studio';
    }

    return $nav;
}
```

### Smart Dashboards

```php
// Show relevant widgets based on user context
class MemberDashboard extends Page
{
    public function getWidgets(): array
    {
        $widgets = [
            UpcomingReservationsWidget::class,
            CommunityEventsWidget::class,
        ];

        // Add band widgets if applicable
        if ($this->hasBands()) {
            $widgets[] = BandSalesWidget::class;
            $widgets[] = BookingInquiriesWidget::class;
        }

        // Add volunteer widgets if opted in
        if ($this->isVolunteer()) {
            $widgets[] = UpcomingShiftsWidget::class;
        }

        return $widgets;
    }
}
```

### Panel Switching

```php
// Allow users to switch panels based on roles
Filament::getUserMenuItems() => [
    'member' => MenuItem::make()
        ->label('Member Portal')
        ->url('/member')
        ->visible(fn() => auth()->user()->isMember()),

    'staff' => MenuItem::make()
        ->label('Staff Hub')
        ->url('/staff')
        ->visible(fn() => auth()->user()->isStaff()),

    'create' => MenuItem::make()
        ->label('Creator Studio')
        ->url('/create')
        ->visible(fn() => auth()->user()->isCreator()),
];
```

---

## Implementation Phases (Audience-First)

### Phase 1: Core Member Experience (10-12 weeks)
**Panel**: `/member`
**Modules**: Core, Finance, Reservations, Events
**Audience**: General Members

**Deliverables**:
- Member dashboard
- Practice space booking
- Event calendar
- Membership management
- Basic profile

---

### Phase 2: Staff Operations (8-10 weeks)
**Panel**: `/staff`
**Modules**: Enhanced admin views of Phase 1 modules
**Audience**: CMC Staff

**Deliverables**:
- Operational dashboard
- Member management
- Approval workflows
- System administration
- Basic reporting

---

### Phase 3: Community Engagement (10-12 weeks)
**Panel**: `/member` (enhanced)
**Modules**: Programs, RSVP, Volunteers
**Audience**: Active Members

**Deliverables**:
- Program directory
- RSVP functionality
- Volunteer opportunities
- Enhanced calendar

---

### Phase 4: Band Services (14-16 weeks)
**Panel**: `/member` (band section)
**Modules**: BandProfiles, Merchandise
**Audience**: Band Members

**Deliverables**:
- EPK management
- Merchandise catalog
- Sales tracking
- Payout system
- Band switcher

---

### Phase 5: Creator Platform (12-14 weeks)
**Panel**: `/create`
**Modules**: Publications, Posters (templates)
**Audience**: Writers, Designers

**Deliverables**:
- Submission portal
- Editorial workflow
- Template marketplace
- Earnings tracking

---

### Phase 6: External Services (12-14 weeks)
**Panel**: `/business` + `/partners`
**Modules**: ProductionServices, Sponsors
**Audience**: External Clients, Sponsors

**Deliverables**:
- Client portal
- Project management
- Sponsor dashboard
- Impact reporting

---

### Phase 7: Advanced Operations (8-10 weeks)
**Panel**: `/staff` (enhanced)
**Modules**: Posters (distribution), Equipment, Advanced reporting
**Audience**: Marketing Team, Operations

**Deliverables**:
- Distribution management
- Equipment tracking
- Advanced analytics
- Marketing tools

---

## Mobile Strategy

### Progressive Web App (PWA)
- Responsive design across all panels
- Mobile-optimized layouts
- Offline capability (read-only)

### Native Apps (Future)
1. **Member App** - Primary mobile experience
2. **Staff App** - Operations on-the-go
3. **Creator App** - Content submission

---

## User Journey Examples

### Sarah (General Member)
**Access**: `/member` only

**Journey**:
1. Logs into `/member`
2. Sees upcoming reservations and events
3. Books practice space
4. RSVPs to Real Book Club
5. Updates profile
6. Checks credits balance

**Never sees**: Band features, staff tools, business services

---

### Marcus (Band Leader + Member)
**Access**: `/member` with band features

**Journey**:
1. Logs into `/member`
2. Dashboard shows reservations + band sales
3. Books practice for his band
4. Switches to "My Band" section
5. Updates band EPK
6. Checks merchandise sales
7. Reviews booking inquiry
8. Returns to member features

**Experience**: Seamless within one panel

---

### Elena (Staff + Member)
**Access**: `/member` + `/staff`

**Journey**:
1. Morning: Checks `/staff` for approvals
2. Reviews new member applications
3. Approves volunteer shift requests
4. Afternoon: Switches to `/member`
5. Books her own practice time
6. RSVPs to community event
7. Back to `/staff` for end-of-day review

**Experience**: Clear work vs. personal separation

---

### TechSound LLC (Production Client)
**Access**: `/business` only

**Journey**:
1. Logs into `/business`
2. Reviews upcoming project
3. Approves quote
4. Signs contract digitally
5. Pays deposit invoice
6. Downloads event timeline
7. Submits venue details

**Never sees**: Member community features

---

### Alex (Writer + Member)
**Access**: `/member` + `/create`

**Journey**:
1. Uses `/member` for community engagement
2. Switches to `/create` for writing work
3. Submits article pitch
4. Checks pitch status
5. Writes assigned article
6. Reviews earnings
7. Updates portfolio

**Experience**: Creative work separate from community participation

---

## Recommendation: Audience-Based

### Why This Approach Wins

1. **User-Centric**: Organized around how people think, not how we build
2. **Contextual**: Right features at the right time
3. **Scalable**: Add features within existing panels
4. **Discoverable**: Users find features organically
5. **Maintainable**: Modular backend, cohesive frontend

### Implementation Strategy

**Backend**: Build as modules (technical organization)
**Frontend**: Present as audience panels (user organization)
**Result**: Best of both worlds

### Panel Priority

1. **`/member`** - Core experience, highest usage
2. **`/staff`** - Operations efficiency
3. **`/business`** - Revenue generation
4. **`/create`** - Content pipeline
5. **`/partners`** - Future enhancement

### Timeline

- **MVP** (Phases 1-2): `/member` + `/staff` = 4-5 months
- **Revenue** (Phases 1-4): Add band services = 9-11 months
- **Complete** (All phases): 12-15 months

---

## Technical Implementation Notes

### Role Detection
```php
// User model methods
public function isBandMember(): bool
{
    return $this->bandMembers()->exists();
}

public function isStaff(): bool
{
    return $this->hasRole(['admin', 'staff', 'volunteer_coordinator']);
}

public function isCreator(): bool
{
    return $this->hasRole(['writer', 'designer', 'photographer']);
}

public function canAccessBusinessPanel(): bool
{
    return $this->productionClients()->exists();
}
```

### Dynamic Navigation
```php
// Filament panel configuration
Panel::make('member')
    ->navigation(function () {
        return NavigationBuilder::make()
            ->groups([
                NavigationGroup::make('Dashboard')->items([...]),
                NavigationGroup::make('Practice Space')->items([...]),
                NavigationGroup::make('Community')->items([...]),

                // Conditional groups
                NavigationGroup::make('My Band')
                    ->items([...])
                    ->visible(fn() => auth()->user()->isBandMember()),

                NavigationGroup::make('Creator Studio')
                    ->items([...])
                    ->visible(fn() => auth()->user()->isCreator()),
            ]);
    });
```

### Panel Registration
```php
// app/Providers/Filament/MemberPanelProvider.php
public function panel(Panel $panel): Panel
{
    return $panel
        ->id('member')
        ->path('member')
        ->colors(['primary' => Color::Amber])
        ->plugins([
            // Load relevant modules
            \CMC\Modules\Reservations\FilamentPlugin::make(),
            \CMC\Modules\Events\FilamentPlugin::make(),
            \CMC\Modules\BandProfiles\FilamentPlugin::make()
                ->visible(fn() => auth()->user()->isBandMember()),
        ]);
}
```

This audience-based approach creates a more intuitive, user-friendly experience while maintaining the technical benefits of modular architecture.
