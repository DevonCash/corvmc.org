# User Subscription Management User Stories

## Core Membership Management

### Story 1: Sustaining Member Status Detection

**As a** CMC member  
**I want** the system to automatically recognize my sustaining member status  
**So that** I receive appropriate benefits without manual intervention  

**Acceptance Criteria:**

- System detects sustaining member status through role assignment OR monthly donation amount
- Members with "sustaining member" role are automatically considered sustaining
- Members with $10+ monthly donations are automatically considered sustaining
- Status is updated in real-time when donations or roles change
- I can see my current sustaining member status in my dashboard
- Benefits (free practice hours) are automatically applied based on status
- Status changes trigger appropriate notifications

### Story 2: Monthly Practice Hours Allocation

**As a** sustaining member  
**I want to** receive my monthly free practice hours  
**So that** I can use the practice space without additional cost  

**Acceptance Criteria:**

- I recieve 1 free hour of practice for every $5 in my recurring contribution
- Hours reset after my next contribution
- System tracks my usage throughout the month
- I can see my remaining free hours in my dashboard
- Unused hours don't roll over to the next month
- Free hours are applied automatically to new reservations

### Story 3: Practice Hours Usage Tracking

**As a** sustaining member  
**I want to** track my practice hours usage  
**So that** I can manage my monthly allocation effectively  

**Acceptance Criteria:**

- I can see my total free hours for the current month
- I can see how many hours I've used this month
- I can see how many hours remain available
- I can see my usage history for previous months
- Usage is updated immediately when I make or cancel reservations
- I can see which specific reservations used my free hours
- System warns me before I exceed my free allocation

## Stripe Integration & Payment Management

### Story 4: Stripe Checkout Integration

**As a** member making payments  
**I want** seamless payment processing through Stripe Checkout  
**So that** I can pay for services securely and conveniently  

**Acceptance Criteria:**

- All platform payments are processed through Stripe Checkout sessions
- System creates Stripe customer profile automatically for new users
- Customer information stays synchronized between CMC and Stripe
- I can save payment methods for future use through Stripe Checkout
- Payment methods are securely stored in Stripe's infrastructure
- I can manage my saved payment methods
- Failed payments are handled gracefully with retry options
- Payment history from Stripe Checkout is accessible and clear

### Story 5: Subscription Lifecycle Management

**As a** member with recurring donations  
**I want** my subscription managed automatically through Stripe Checkout  
**So that** my sustaining member benefits continue seamlessly  

**Acceptance Criteria:**

- System creates Stripe subscriptions using Stripe Checkout for recurring donations
- Subscription status is synchronized with sustaining member benefits
- Failed subscription payments are retried according to Stripe settings
- I'm notified of subscription status changes
- I can modify my subscription amount or cancel through Stripe Customer Portal
- Subscription changes are reflected in sustaining member status immediately
- Past due subscriptions affect benefit eligibility appropriately

### Story 6: Payment Method Management

**As a** member  
**I want to** manage my payment methods through Stripe  
**So that** I can keep my payment information current and secure  

**Acceptance Criteria:**

- I can add new credit/debit cards securely through Stripe Checkout
- I can set a default payment method for reservations via Stripe Customer Portal
- I can update payment method information through Stripe's secure interface
- I can remove payment methods I no longer use via Customer Portal
- System handles expired cards gracefully with Stripe's automatic retry logic
- I can see which payment method will be used for upcoming charges
- All payment data is stored securely in Stripe, never on CMC servers

### Story 7: External Transaction Integration

**As a** member who donates through external platforms  
**I want** my external donations to contribute to my sustaining member status  
**So that** I receive appropriate benefits regardless of payment method  

**Acceptance Criteria:**

- System can track transactions from external platforms (Zeffy, etc.) via API integration
- External donations count toward sustaining member status calculations
- Donation amounts from external platforms are included in monthly totals
- External transaction data is synchronized regularly and automatically
- I can see all my donations (Stripe + external) in one consolidated view
- Sustaining member benefits are calculated from total donations across all platforms
- External transaction failures or issues are monitored and reported

## Analytics & Reporting

### Story 8: Subscription Analytics

**As an** admin  
**I want to** understand subscription patterns and member engagement  
**So that** I can make informed decisions about pricing and benefits  

**Acceptance Criteria:**

- I can see total number of sustaining members by role vs. donation
- I can see monthly recurring revenue from subscriptions
- I can see subscription churn rate and retention metrics
- I can see average donation amounts and trends
- I can see practice hours utilization by sustaining members
- I can see conversion rates from regular to sustaining members
- Reports help identify opportunities to increase sustainability

### Story 9: Member Engagement Metrics

**As an** admin  
**I want to** see how sustaining members use their benefits  
**So that** I can optimize the value proposition  

**Acceptance Criteria:**

- I can see what percentage of sustaining members actively use practice space
- I can see average monthly practice hours used vs. allocated
- I can see seasonal patterns in practice space usage
- I can identify sustaining members who aren't using their benefits
- I can see correlation between practice space usage and continued membership
- Data helps guide outreach and engagement efforts

## Member Experience & Communication

### Story 10: Benefit Communication

**As a** sustaining member  
**I want** clear communication about my benefits  
**So that** I understand and can fully utilize my membership value  

**Acceptance Criteria:**

- I receive welcome information when I become a sustaining member
- I get monthly summaries of my practice hours usage
- I'm notified when benefits change or expand
- I can see a clear explanation of all sustaining member benefits
- I receive reminders about unused benefits approaching expiration
- Communication is personalized based on my actual usage patterns

### Story 11: Member Retention & Re-engagement

**As an** admin  
**I want** tools to retain and re-engage sustaining members  
**So that** we maintain a stable financial foundation  

**Acceptance Criteria:**

- System identifies sustaining members at risk of churning
- I can see members whose subscriptions are failing or cancelled
- I can reach out to members who haven't used their benefits recently
- System provides personalized retention offers based on usage patterns
- I can track effectiveness of retention efforts
- Re-engagement campaigns can be automated based on usage triggers

## Integration Stories

### Story 12: Subscription + Practice Space Integration

**As a** sustaining member  
**I want** my subscription status seamlessly integrated with practice space booking  
**So that** my free hours are automatically applied  

**Acceptance Criteria:**

- Practice space booking automatically applies my free hours first
- I can see my remaining free hours during the booking process
- System prevents overbooking beyond my free allocation without payment
- Free hour usage is immediately reflected in my subscription dashboard
- Booking system clearly shows when I'm using free vs. paid hours
- Cancellations properly restore free hours to my monthly allocation

### Story 13: Subscription + Member Profile Integration

**As a** sustaining member  
**I want** my membership status reflected in my community presence  
**So that** I receive appropriate recognition for supporting CMC  

**Acceptance Criteria:**

- My member profile can display sustaining member badge (if I choose)
- Sustaining members can be highlighted in member directory
- Profile shows my support level without revealing specific donation amounts
- I can control visibility of my sustaining member status
- Other members can filter directory to find sustaining members
- Recognition respects privacy preferences while encouraging community support

### Story 14: Subscription + Production Integration

**As a** sustaining member  
**I want** priority or special consideration for CMC productions  
**So that** my ongoing support provides additional value  

**Acceptance Criteria:**

- Sustaining members can receive early notification of new productions
- Production managers can see sustaining member status when building lineups
- Sustaining members might receive preference for limited performance slots
- Special sustaining member events can be organized
- Benefits are clearly communicated and fairly applied
- Integration supports community building among committed members

### Story 15: Financial Health Monitoring

**As an** admin  
**I want** real-time visibility into CMC's subscription-based financial health  
**So that** I can make informed operational decisions  

**Acceptance Criteria:**

- Dashboard shows monthly recurring revenue (MRR) from subscriptions
- I can see subscription growth/decline trends over time
- I can forecast revenue based on current subscription patterns
- Failed payment notifications let me address issues quickly
- I can see the financial impact of member churn
- Reports help with budgeting and strategic planning
- Integration with overall financial reporting systems
