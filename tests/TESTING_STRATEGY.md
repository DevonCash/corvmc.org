# Comprehensive Testing Strategy

## Overview

This document outlines the testing strategy for the Corvallis Music Collective application, covering all major components and user workflows.

## Test Structure

### 1. Unit Tests (`tests/Unit/`)

- **Models**: Test model relationships, accessors, mutators, and business logic
- **Services**: Test business logic, calculations, and data transformations  
- **Data Classes**: Test data transformation and validation
- **Utilities**: Test helper functions and utility classes

### 2. Feature Tests (`tests/Feature/`)

- **Authentication**: Registration, login, password reset, email verification
- **User Workflows**: Complete user journeys from start to finish
- **API Endpoints**: Test all API routes and responses
- **Filament Resources**: Test admin panel functionality
- **Integration**: Test component interactions and data flow

### 3. Browser Tests (`tests/Browser/`) - *Future*

- **User Interface**: Test actual user interactions
- **JavaScript Behavior**: Test frontend functionality
- **Multi-user Scenarios**: Test concurrent user actions

## Testing Priorities

### High Priority (Core Business Logic)

1. **User Subscription System**
   - Sustaining member detection via roles and transactions
   - Free hour calculations and usage tracking
   - Monthly rollover logic

2. **Reservation System**
   - Practice space booking workflow
   - Conflict detection (reservations vs productions)
   - Cost calculations and Stripe integration
   - Recurring reservations

3. **Production System**
   - Event creation and management
   - Band lineup management
   - Publication workflow
   - Conflict resolution

4. **Band Management**
   - Band creation and ownership
   - Member invitations and roles
   - Profile visibility and permissions

### Medium Priority (User Features)

1. **Member Profiles**
   - Profile creation and editing
   - Skill/genre/influence tagging
   - Media management
   - Privacy settings

2. **Financial Transactions**
   - Stripe payment processing
   - Zeffy webhook handling
   - Revenue calculation and reporting

3. **Notification System**
   - Email notifications for various events
   - Notification preferences and scheduling

### Lower Priority (Administrative)

1. **Filament Admin Panel**
   - Resource CRUD operations
   - Custom actions and bulk operations
   - Permission-based access

2. **Reporting and Analytics**
   - Usage statistics
   - Revenue reports
   - Member activity tracking

## Test Coverage Goals

### Models (Target: 95%+ coverage)

- ✅ User - Comprehensive tests exist
- ✅ Band - Fixed and comprehensive
- ✅ Reservation - Good coverage
- ✅ Production - Good coverage
- ⚠️ Transaction - Needs more payment-specific tests
- ❌ MemberProfile - Missing tests

### Services (Target: 90%+ coverage)

- ✅ UserSubscriptionService - Good coverage
- ✅ ReservationService - Good coverage  
- ✅ MemberProfileService - Basic coverage
- ❌ UserInvitationService - Needs comprehensive tests
- ❌ BandService - Missing tests
- ❌ ProductionService - Missing tests

### Feature Workflows (Target: 80%+ coverage)

- ⚠️ Reservation Workflow - Basic tests exist, needs expansion
- ❌ Band Creation Workflow - Missing
- ❌ Production Management Workflow - Missing
- ❌ User Registration/Invitation - Missing
- ❌ Payment Processing - Missing

## Testing Utilities

### Helper Methods (TestCase.php)

- `createSustainingMember()` - User with sustaining member role
- `createBandLeader()` - User with band leader role
- `createBand()` - Band with owner and optional members
- `createProduction()` - Production with manager
- `createReservation()` - Practice space reservation
- `createTransaction()` - Financial transaction

### Test Data Trait (CreatesTestData.php)

- `createUserWithSubscription()` - User with recurring payments
- `createBandWithMembers()` - Multi-member band setup
- `createProductionWithPerformers()` - Event with lineup
- `createConflictingReservations()` - Overlapping bookings
- `createRevenueTransactions()` - Financial test data

## Test Scenarios to Implement

### User Management

- [ ] User registration and email verification
- [ ] User invitation system end-to-end
- [ ] Role assignment and permission testing
- [ ] Profile creation and updates

### Subscription System  

- [ ] Sustaining member detection via transactions
- [ ] Free hour allocation and tracking
- [ ] Monthly rollover calculations
- [ ] Subscription status changes

### Reservation System

- [ ] Basic reservation creation and management
- [ ] Conflict detection with other reservations
- [ ] Conflict detection with productions
- [ ] Cost calculation for different user types
- [ ] Stripe payment integration
- [ ] Recurring reservation creation

### Production System

- [ ] Production lifecycle (creation → published → completed)
- [ ] Band lineup management
- [ ] Set time calculations
- [ ] Conflict detection with reservations
- [ ] Permission-based access control

### Band Management

- [ ] Band creation by different user types
- [ ] Member invitation workflow
- [ ] Role and permission management
- [ ] Profile visibility controls

### Financial System

- [ ] Stripe payment processing
- [ ] Zeffy webhook handling
- [ ] Transaction recording and status updates
- [ ] Revenue calculation and reporting

## Testing Best Practices

### Data Management

- Use factories for consistent test data creation
- Leverage RefreshDatabase for test isolation
- Create realistic test scenarios that match production usage
- Use the helper methods to reduce test setup boilerplate

### Assertions

- Test both positive and negative cases
- Verify database state changes
- Test edge cases and boundary conditions
- Include performance considerations for critical paths

### Maintenance

- Keep tests fast and focused
- Update tests when business logic changes
- Remove obsolete tests that no longer reflect requirements
- Document complex test scenarios

## Implementation Plan

### Phase 1: Core Business Logic (Week 1)

1. Complete UserSubscriptionService tests
2. Expand ReservationService tests  
3. Create UserInvitationService tests
4. Add comprehensive financial transaction tests

### Phase 2: Feature Workflows (Week 2)

1. Reservation workflow integration tests
2. Band management workflow tests
3. Production management workflow tests
4. User registration/invitation workflow tests

### Phase 3: API and Integration (Week 3)

1. API endpoint testing
2. Stripe payment integration tests
3. Zeffy webhook tests
4. Email notification tests

### Phase 4: Admin and Edge Cases (Week 4)

1. Filament resource tests
2. Permission and security tests
3. Performance and load tests
4. Error handling and recovery tests

## Success Metrics

- **Code Coverage**: >85% overall, >95% for critical business logic
- **Test Speed**: Full suite runs in <2 minutes
- **Reliability**: <1% flaky test rate
- **Documentation**: All complex business logic covered by descriptive tests
