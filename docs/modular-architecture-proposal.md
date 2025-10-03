# CMC Platform: Modular Architecture Proposal

## Overview

The CMC platform has grown significantly in scope. This document proposes a modular architecture that splits functionality into manageable, independently developable modules. Each module can be built, tested, and deployed independently while maintaining integration with the core platform.

## Architectural Approach

### Core Platform
The base Laravel application with essential functionality that all modules depend on:
- User authentication and profiles
- Band directory
- Member directory
- Basic reservations
- Core transactions
- Permissions and roles
- Filament base panel

### Module Structure
Each module is a self-contained package with:
- Own database migrations
- Own models and services
- Own Filament resources
- Own routes and controllers
- Own views and assets
- Own tests
- Clear API boundaries

## Proposed Module Breakdown

### 1. **Core Module** (Already Exists)
**Status**: Foundation - Already Built
**Panel**: `/member` (Main Panel)

**Features**:
- User authentication
- Member profiles
- Band directory
- Basic practice space reservations
- Simple transaction tracking
- Permission system
- Settings management

**Dependencies**: None

---

### 2. **Finance & Subscriptions Module**
**Panel**: `/member` (Main Panel - Finance Section)
**Estimated**: 30-40 hours

**Features**:
- Stripe subscription management
- Payment processing
- Transaction tracking
- Invoice generation
- Credits system
- Promo codes
- Financial reporting
- Member benefits calculation

**Key Models**:
- `Subscription`
- `Transaction`
- `UserCredit`
- `CreditTransaction`
- `PromoCode`

**Resources**:
- SubscriptionResource
- TransactionResource
- CreditResource
- InvoiceResource

**Dependencies**: Core

---

### 3. **Events & Productions Module**
**Panel**: `/member` (Main Panel - Events Section)
**Estimated**: 40-50 hours

**Features**:
- Production management
- Event calendar
- Band lineups
- Ticket integration
- Public event pages
- Event analytics

**Key Models**:
- `Production`
- `CommunityEvent`
- `BandMember` (lineup)

**Resources**:
- ProductionResource
- CommunityEventResource
- CalendarPage

**Dependencies**: Core, Finance

---

### 4. **Advanced Reservations Module**
**Panel**: `/member` (Main Panel - Reservations Section)
**Estimated**: 25-35 hours

**Features**:
- Recurring reservations (RRULE)
- Conflict detection
- Credit deduction
- Reservation analytics
- Automated reminders

**Key Models**:
- `RecurringReservation`
- Extends existing `Reservation`

**Resources**:
- RecurringReservationResource
- ReservationAnalyticsWidget

**Dependencies**: Core, Finance

---

### 5. **Community Programs Module**
**Panel**: `/member` (Main Panel - Programs Section)
**Estimated**: 60-80 hours

**Features**:
- Program management (Real Book Club, etc.)
- Session scheduling with RRULE
- RSVP system (base implementation)
- Attendance tracking
- Repertoire voting
- Program analytics

**Key Models**:
- `CommunityProgram`
- `ProgramSession`
- `ProgramRsvp`
- `ProgramAttendance`
- `ProgramRepertoire`

**Resources**:
- CommunityProgramResource
- ProgramSessionResource
- RepertoireResource

**Dependencies**: Core, Events (for unified calendar)

---

### 6. **RSVP & Attendance Module**
**Panel**: `/member` (Main Panel - shared across Events & Programs)
**Estimated**: 35-45 hours

**Features**:
- Unified RSVP system (polymorphic)
- Event settings
- Capacity management
- Waitlist automation
- Attendance tracking
- Invitation system
- RSVP analytics

**Key Models**:
- `Rsvp` (polymorphic)
- `Attendance` (polymorphic)
- `EventSetting` (polymorphic)
- `EventInvitation`

**Trait**: `HasRsvps` (for any event type)

**Resources**:
- RsvpResource (admin view)
- AttendanceResource

**Dependencies**: Core, Events, Community Programs

---

### 7. **Volunteer Management Module**
**Panel**: `/member` (Main Panel - Volunteers Section)
**Estimated**: 35-50 hours

**Features**:
- Volunteer opportunity management
- Shift scheduling
- Hour tracking
- Milestone achievements
- Credit rewards integration

**Key Models**:
- `VolunteerOpportunity`
- `VolunteerShift`
- `VolunteerShiftSignup`
- `VolunteerHours`

**Resources**:
- VolunteerOpportunityResource
- VolunteerShiftResource
- VolunteerHoursResource

**Dependencies**: Core, Finance (credits integration)

---

### 8. **Publications Module**
**Panel**: `/publications` (Separate Panel)
**Estimated**: 100-130 hours

**Features**:
- Article management
- Editorial workflow
- Publication creation
- Contributor management
- Sponsor integration
- Distribution tracking
- Public archive

**Key Models**:
- `Publication`
- `Article`
- `ArticlePitch`
- `ArticleContributor`
- `Sponsor`

**Resources**:
- ArticleResource
- PublicationResource
- PitchResource

**Why Separate Panel?**:
- Distinct user base (writers, editors)
- Different permissions model
- Separate branding potential
- Can be deployed independently

**Dependencies**: Core, Finance (sponsor payments)

---

### 9. **Merchandise Consignment Module**
**Panel**: `/store` (Separate Panel for Band Portal)
**Estimated**: 130-170 hours

**Features**:
- Band merchandise catalog
- Consignment tracking
- POS integration
- Inventory management
- Payout calculation
- Distribution tracking
- Online store

**Key Models**:
- `MerchandiseBand`
- `MerchandiseProduct`
- `MerchandiseVariant`
- `MerchandiseSale`
- `MerchandisePayout`

**Resources**:
- MerchandiseProductResource
- MerchandiseSaleResource
- MerchandisePayoutResource

**Why Separate Panel?**:
- Band-specific portal (only band members)
- POS interface requirements
- Distinct workflow from member panel
- Potential standalone store app

**Dependencies**: Core, Finance

---

### 10. **Band EPK & Profiles Module**
**Panel**: `/member` (Main Panel - Bands Section)
**Estimated**: 150-200 hours

**Features**:
- Premium profile subscriptions
- EPK content management
- Custom styling and templates
- Subdomain hosting
- Booking inquiry forms
- Analytics tracking
- Public profile pages

**Key Models**:
- `BandProfileSubscription`
- `BandEpkSection`
- `BandCustomization`
- `BandBookingInquiry`

**Resources**:
- BandEpkResource
- BandProfileResource
- BookingInquiryResource

**Why Main Panel?**:
- Extension of existing Band model
- Integrated with member experience
- Shared navigation

**Dependencies**: Core, Finance (subscriptions)

---

### 11. **Poster & Marketing Module**
**Panel**: `/marketing` (Separate Panel)
**Estimated**: 160-210 hours

**Features**:
- Poster template library
- Design customization
- Print order management
- Distribution network
- Community listing posters
- Sponsor management
- QR code generation
- Analytics tracking

**Key Models**:
- `PosterTemplate`
- `PosterOrder`
- `CommunityListingPoster`
- `DistributionLocation`
- `DistributionRun`

**Resources**:
- PosterOrderResource
- TemplateResource
- DistributionResource

**Why Separate Panel?**:
- Marketing team focus
- Designer workflow
- Distribution coordination
- Can operate semi-independently

**Dependencies**: Core, Finance, Events

---

### 12. **Production Services Module**
**Panel**: `/production` (Separate Panel)
**Estimated**: 135-175 hours

**Features**:
- Client management
- Quote generation
- Contract management
- Staff scheduling
- Equipment assignments
- Project tracking
- Invoice generation
- Profitability analysis

**Key Models**:
- `ProductionClient`
- `ProductionProject`
- `ProductionQuote`
- `ProductionContract`
- `ProductionStaffAssignment`

**Resources**:
- ProductionProjectResource
- ProductionClientResource
- ProductionQuoteResource

**Why Separate Panel?**:
- B2B workflow (different from member services)
- External clients
- Project-based interface
- Professional services focus

**Dependencies**: Core, Finance, Equipment, Volunteer (staff)

---

### 13. **Equipment Management Module**
**Panel**: `/member` (Main Panel - Equipment Section)
**Estimated**: 25-35 hours (Enhancement of existing)

**Features**:
- Enhanced equipment tracking
- Loan management
- Damage reports
- Maintenance scheduling
- Availability calendar
- Integration with Production Services

**Key Models**:
- `Equipment` (existing, enhanced)
- `EquipmentLoan` (existing)
- `EquipmentDamageReport` (existing)

**Resources**:
- EquipmentResource (enhanced)
- EquipmentLoanResource

**Dependencies**: Core

---

## Panel Structure Summary

### `/member` - Main Member Panel (Amber)
**Primary Users**: CMC Members

**Navigation Groups**:
- **Dashboard**: Home, Calendar, Activity Feed
- **My Account**: Profile, Subscription, Credits, Payments
- **Practice Space**: Reservations, Recurring Reservations
- **Events**: Productions, Community Events, Program Sessions
- **Community**: Programs, Volunteers, Member Directory
- **Bands**: My Bands, Band Profiles, EPK Manager
- **Equipment**: Browse, Loans, Damage Reports

**Modules Included**:
- Core
- Finance & Subscriptions
- Events & Productions
- Advanced Reservations
- Community Programs
- RSVP & Attendance
- Volunteer Management
- Band EPK & Profiles
- Equipment Management

---

### `/publications` - Publications Panel (Indigo)
**Primary Users**: Writers, Editors, Contributors

**Navigation Groups**:
- **Dashboard**: Submission Stats, Deadlines
- **Content**: Articles, Pitches, Publications
- **Editorial**: Review Queue, Assignments
- **Contributors**: Writers, Payments
- **Sponsors**: Sponsor Management, Invoices
- **Distribution**: Locations, Batches

**Modules Included**:
- Publications Module

**Access**:
- Writers: Own articles and pitches
- Editors: All content
- Admins: Full access

---

### `/store` - Merchandise Panel (Green)
**Primary Users**: Band Members (for their bands)

**Navigation Groups**:
- **Dashboard**: Sales Overview, Upcoming Payouts
- **Products**: My Products, Inventory, Variants
- **Sales**: Order History, Transactions
- **Payouts**: Payment History, Tax Documents
- **Analytics**: Sales Reports, Popular Items

**Modules Included**:
- Merchandise Consignment Module

**Access**:
- Band members only
- Filtered to their band(s)
- Automatic band selection

---

### `/marketing` - Marketing Panel (Purple)
**Primary Users**: Marketing Team, Designers, Street Team

**Navigation Groups**:
- **Dashboard**: Active Campaigns, Distribution Schedule
- **Posters**: Orders, Templates, Community Listings
- **Distribution**: Locations, Routes, Runs
- **Sponsors**: Sponsor Management
- **Analytics**: QR Scans, Placement Metrics

**Modules Included**:
- Poster & Marketing Module

**Access**:
- Marketing staff
- Designers (template management)
- Street team (distribution only)

---

### `/production` - Production Services Panel (Slate)
**Primary Users**: Production Team, Engineers, Booking Coordinators

**Navigation Groups**:
- **Dashboard**: Upcoming Projects, Inquiries
- **Projects**: Pipeline, Calendar, Archive
- **Clients**: Client Directory, Contracts
- **Services**: Service Catalog, Packages
- **Staff**: Schedule, Assignments, Hours
- **Equipment**: Assignments, Availability
- **Finance**: Quotes, Invoices, Expenses

**Modules Included**:
- Production Services Module

**Access**:
- Production managers: Full access
- Engineers/Staff: Own assignments
- Booking coordinators: Clients and quotes

---

## Implementation Strategy

### Phase 1: Foundation (Already Complete)
**Duration**: N/A - Existing
- Core module
- Basic member panel
- User authentication
- Band directory
- Simple reservations

### Phase 2: Financial Infrastructure
**Duration**: 6-8 weeks
**Modules**:
1. Finance & Subscriptions (30-40h)
2. Advanced Reservations (25-35h)

**Why First?**:
- Foundation for other paid services
- Credits system needed by multiple modules
- Subscription infrastructure critical

**Deliverables**:
- Stripe integration complete
- Credits system operational
- Recurring reservations working
- Member benefits calculation

---

### Phase 3: Community Engagement
**Duration**: 8-10 weeks
**Modules**:
1. Events & Productions (40-50h)
2. Community Programs (60-80h)
3. RSVP & Attendance (35-45h)

**Why Next?**:
- Core member experience
- High engagement features
- Builds community activity

**Deliverables**:
- Production management
- Program scheduling
- RSVP system working across event types
- Unified calendar

---

### Phase 4: Support Services
**Duration**: 6-8 weeks
**Modules**:
1. Volunteer Management (35-50h)
2. Equipment Management Enhancement (25-35h)

**Why Now?**:
- Supports community programs
- Needed for production services
- Enhances operations

**Deliverables**:
- Volunteer shifts and hours
- Equipment tracking
- Maintenance scheduling

---

### Phase 5: Band Services
**Duration**: 12-15 weeks
**Modules**:
1. Band EPK & Profiles (150-200h)
2. Merchandise Consignment (130-170h)

**Why Here?**:
- Major revenue generators
- Build on existing band infrastructure
- Distinct panels/workflows

**Deliverables**:
- `/store` panel operational
- Band EPK system
- Subdomain hosting
- POS integration
- Online store

---

### Phase 6: Content & Marketing
**Duration**: 10-12 weeks
**Modules**:
1. Publications (100-130h)
2. Poster & Marketing (160-210h)

**Why Later?**:
- Less critical to core operations
- Benefit from established community
- Complex workflows

**Deliverables**:
- `/publications` panel
- `/marketing` panel
- Editorial workflow
- Distribution network

---

### Phase 7: Professional Services
**Duration**: 10-12 weeks
**Modules**:
1. Production Services (135-175h)

**Why Last?**:
- Builds on all other systems
- Complex B2B workflow
- Benefits from equipment/volunteer systems

**Deliverables**:
- `/production` panel
- Client management
- Project pipeline
- Quote and contract system

---

## Development Best Practices

### Module Independence
```php
// Each module as a Laravel package
namespace CMC\Modules\Publications;

class PublicationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register services
        $this->app->singleton(PublicationService::class);
    }

    public function boot()
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Register Filament resources
        Filament::registerResources([
            ArticleResource::class,
            PublicationResource::class,
        ]);
    }
}
```

### Module Structure
```
modules/
├── Publications/
│   ├── src/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Filament/
│   │   │   ├── Resources/
│   │   │   ├── Pages/
│   │   │   └── Widgets/
│   │   └── PublicationsServiceProvider.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── tests/
│   └── composer.json
├── Merchandise/
│   └── ...
└── Production/
    └── ...
```

### Panel Registration
```php
// config/filament.php or PanelProvider

// Main member panel
Filament::panel('member')
    ->path('member')
    ->colors(['primary' => Color::Amber])
    ->plugins([
        // Core features always loaded
    ]);

// Publications panel
Filament::panel('publications')
    ->path('publications')
    ->colors(['primary' => Color::Indigo])
    ->plugin(new PublicationsPlugin());

// Merchandise panel
Filament::panel('store')
    ->path('store')
    ->colors(['primary' => Color::Green])
    ->plugin(new MerchandisePlugin());

// Marketing panel
Filament::panel('marketing')
    ->path('marketing')
    ->colors(['primary' => Color::Purple])
    ->plugin(new PosterPlugin());

// Production panel
Filament::panel('production')
    ->path('production')
    ->colors(['primary' => Color::Slate])
    ->plugin(new ProductionServicesPlugin());
```

### Inter-Module Communication
```php
// Use events for loose coupling
namespace CMC\Modules\Finance\Events;

class SubscriptionCreated
{
    public function __construct(
        public User $user,
        public Subscription $subscription
    ) {}
}

// Other modules listen
namespace CMC\Modules\Reservations\Listeners;

class AllocateMonthlyCredits
{
    public function handle(SubscriptionCreated $event)
    {
        // Allocate credits based on subscription
    }
}
```

### Testing Strategy
```php
// Each module has its own tests
namespace CMC\Modules\Publications\Tests;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_article()
    {
        // Test module functionality in isolation
    }
}
```

## Migration Path

### For Existing Code
1. **Identify boundaries** - Map existing code to proposed modules
2. **Extract gradually** - Move one module at a time
3. **Maintain compatibility** - Keep existing routes/APIs working
4. **Test thoroughly** - Ensure no regressions
5. **Update dependencies** - Adjust imports and service references

### For New Modules
1. **Start independent** - Build as standalone package
2. **Define interfaces** - Clear API contracts
3. **Mock dependencies** - Test without full platform
4. **Integrate incrementally** - Add to platform when stable
5. **Document thoroughly** - Module API and usage

## Benefits of This Approach

### Development
- **Parallel work** - Multiple developers on different modules
- **Focused testing** - Test modules independently
- **Easier debugging** - Isolated functionality
- **Incremental delivery** - Ship modules as ready
- **Reduced complexity** - Smaller codebases per module

### Maintenance
- **Easier updates** - Modify one module without affecting others
- **Better organization** - Clear separation of concerns
- **Simpler debugging** - Isolated issues
- **Selective deployment** - Update only changed modules
- **Independent versioning** - Module-specific releases

### Business
- **Faster time-to-market** - Ship core features first
- **Flexible scaling** - Add modules based on need
- **Lower risk** - Isolated failures
- **Easier outsourcing** - Clear module boundaries
- **Better planning** - Module-based roadmap

### User Experience
- **Focused interfaces** - Panels for specific user types
- **Better performance** - Load only needed modules
- **Clear navigation** - Purpose-built panels
- **Role-based access** - Panel-level permissions

## Estimated Timeline

### Full Platform Completion
**Phases 1-7**: ~52-65 weeks (1-1.25 years)

### Minimum Viable Product (MVP)
**Phases 1-3**: ~14-18 weeks (3.5-4.5 months)
- Core features
- Financial infrastructure
- Community engagement

### Revenue-Generating Version
**Phases 1-5**: ~32-41 weeks (8-10 months)
- Everything in MVP
- Support services
- Band services (EPK + Merchandise)

## Recommendations

### Start With
1. **Finance & Subscriptions** - Foundation for revenue
2. **Advanced Reservations** - Core member value
3. **Events & Productions** - Community engagement

### Prioritize
- Features with immediate revenue potential
- High user engagement features
- Operational efficiency improvements

### Defer
- Complex editorial workflows (Publications)
- Professional services (Production Services)
- Advanced marketing tools

### Consider External Help For
- Stripe integration (if complex)
- POS system integration
- Subdomain routing setup
- Mobile app development (street team/POS)

## Next Steps

1. **Validate assumptions** - Review with stakeholders
2. **Refine priorities** - Adjust based on business needs
3. **Set up module structure** - Create base architecture
4. **Begin Phase 2** - Start Finance & Subscriptions module
5. **Establish CI/CD** - Automated testing and deployment per module
6. **Document APIs** - Inter-module communication contracts
