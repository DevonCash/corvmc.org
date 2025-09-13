# Practice Space Reservation User Stories

## Core Reservation Management

### Story 1: Book Practice Space

**As a** CMC member  
**I want to** reserve practice space for specific times  
**So that** I can rehearse my music in a dedicated space  

**Acceptance Criteria:**

- I can select available date and time slots for practice space
- I can see the hourly rate ($15/hour) and total cost before booking
- I can book between 1-8 hours in a single reservation
- System shows available time slots between 9 AM - 10 PM
- I can see conflicts with existing reservations and productions
- Sustaining members see their free hours applied automatically to reduce cost
- I receive confirmation notification when reservation is successful
- Payment is processed through Stripe Checkout for paid hours

### Story 2: View My Reservations

**As a** CMC member  
**I want to** view all my practice space reservations  
**So that** I can track my scheduled practice times and manage my bookings  

**Acceptance Criteria:**

- I can see all my upcoming reservations in chronological order
- I can see my past reservations for reference
- Each reservation shows date, time, duration, cost, and payment status
- I can see which hours were free (sustaining member benefit) vs. paid
- I can filter reservations by date range or status
- I can see cancellation status and refund information
- Mobile view is optimized for quick reservation checking

### Story 3: Cancel and Rebook Reservations

**As a** CMC member  
**I want to** cancel reservations and create new ones when my schedule changes  
**So that** I can adjust my practice times without editing existing bookings  

**Acceptance Criteria:**

- I can cancel reservations that haven't started yet
- I cannot edit or modify existing reservations - I must cancel and rebook
- Cancellations made with sufficient advance notice receive full refunds
- Free hours used by sustaining members are restored to their monthly allocation
- After cancelling, I can immediately book new time slots if available
- Other members are notified when popular time slots become available
- I receive confirmation when cancellation and refund are processed
- Cancellation policy is clearly explained before I confirm
- System prevents cancellation of reservations that have already started

## Sustaining Member Benefits

### Story 4: Free Hours for Sustaining Members

**As a** sustaining member  
**I want** my free practice hours applied automatically  
**So that** I can use my membership benefits without extra steps  

**Acceptance Criteria:**

- I receive 1 free hour for every $5 in my recurring monthly contribution
- Free hours are automatically applied to new reservations to minimize cost
- I can see my remaining free hours before making a reservation
- Free hours reset with my monthly contribution cycle
- Unused free hours don't roll over to the next month
- I can see in my reservation history which hours were free vs. paid
- System clearly shows when I'm using free hours vs. when I need to pay

### Story 5: Free Hours Tracking

**As a** sustaining member  
**I want to** track my free hours usage throughout the month  
**So that** I can manage my practice schedule and membership value  

**Acceptance Criteria:**

- I can see my total free hours allocation based on my contribution level
- I can see how many free hours I've used this month
- I can see how many free hours remain available
- I can view my free hours usage history for previous months
- System warns me when I'm approaching my free hours limit
- I can see the monetary value of free hours I've used
- Usage tracking helps me optimize my membership contribution level

## Scheduling & Availability

### Story 6: View Available Time Slots

**As a** CMC member  
**I want to** see all available practice space time slots  
**So that** I can find the best times for my practice sessions  

**Acceptance Criteria:**

- I can see a calendar view of available and booked time slots
- Available slots are clearly distinguished from booked ones
- I can see production conflicts that block practice space availability
- I can navigate between different weeks/months to plan ahead
- Popular time slots show wait-list or notification options
- Mobile calendar view is touch-friendly and responsive
- System shows operating hours (9 AM - 10 PM) and days

### Story 7: Off-Hours Reservation Management

**As a** CMC member wanting to practice outside normal hours  
**I want** to book unsupervised practice time with appropriate payment requirements  
**So that** I can access practice space when it works best for my schedule  

**Acceptance Criteria:**

- I can see which time slots are considered "off-hours" or "unsupervised"
- Off-hours reservations clearly indicate they require full payment in advance
- I cannot book off-hours slots without completing payment through Stripe Checkout
- Free sustaining member hours cannot be used for off-hours reservations
- Off-hours slots are clearly distinguished from regular supervised hours
- I understand the additional responsibilities of unsupervised access
- Off-hours booking includes acknowledgment of facility use policies

### Story 8: Conflict Detection

**As the** system  
**I want to** prevent double-booking of practice space  
**So that** members don't have scheduling conflicts or access issues  

**Acceptance Criteria:**

- System prevents overlapping reservations from different users
- Productions using practice space block reservation availability
- Buffer time between reservations can be configured if needed
- Conflicting reservations are clearly identified with specific details
- Users see helpful error messages when attempting to book conflicted slots
- System suggests alternative available times when conflicts occur
- Edge cases (ending exactly when another begins) are handled correctly

### Story 9: Recurring Reservations

**As a** sustaining member with regular practice needs  
**I want to** book recurring weekly practice sessions  
**So that** I can maintain a consistent practice schedule  

**Acceptance Criteria:**

- I can book the same time slot for multiple weeks in advance
- System checks for conflicts across all requested dates before confirming
- I can modify or cancel individual instances of recurring reservations
- Free hours are applied appropriately across all recurring bookings
- I'm notified if any future recurring reservations conflict with new productions
- Recurring bookings respect my free hours allocation per month
- I can end a recurring series while keeping already-booked sessions

## Payment & Billing

### Story 10: Payment Processing

**As a** CMC member booking paid practice time  
**I want** secure and convenient payment processing  
**So that** I can complete my reservations quickly and safely  

**Acceptance Criteria:**

- All payments are processed through Stripe Checkout
- I can save payment methods for faster future bookings
- Payment is required before reservation is confirmed
- Failed payments prevent reservation confirmation with clear error messages
- I receive email receipts for all paid reservations
- Partial payments (when some hours are free) are handled correctly
- Stripe fees can optionally be covered by user to support CMC

### Story 11: Resolve Outstanding Payments

**As a** CMC member with unpaid reservation bills  
**I want** to easily see and pay my outstanding balances  
**So that** I can continue using practice space without restrictions  

**Acceptance Criteria:**

- I can clearly see any outstanding reservation payments in my dashboard
- I can pay outstanding balances directly through the same payment system
- I receive clear notifications about overdue payments and consequences
- The system explains why I can't make new reservations when payments are outstanding
- I can see payment due dates and any applicable late fees or policies
- Once I pay outstanding amounts, I can immediately make new reservations
- Payment reminders are helpful rather than punitive in tone

### Story 12: Refund Processing

**As a** CMC member with a cancelled reservation  
**I want** timely and fair refunds  
**So that** I'm not charged for practice time I didn't use  

**Acceptance Criteria:**

- Refunds are processed automatically through Stripe for eligible cancellations
- Refund policy is clearly communicated during booking process
- Full refunds are provided for cancellations made with adequate notice
- Partial refunds may apply for last-minute cancellations based on policy
- Free hours used by sustaining members are restored upon cancellation
- Refund status is visible in my reservation history
- I'm notified when refunds are processed and appear on my payment method

## Administrative Features

### Story 13: Reservation Management (Admin)

**As an** admin  
**I want to** manage all practice space reservations  
**So that** I can resolve conflicts and optimize space utilization  

**Acceptance Criteria:**

- I can view all reservations across all users and time periods
- I can cancel reservations on behalf of users when necessary
- I cannot edit existing reservations - I must cancel and help users rebook
- I can create administrative reservations for maintenance or events
- I can see reservation utilization statistics and popular time slots
- I can process manual refunds for exceptional circumstances
- I can see payment status and resolve payment-related issues
- I can export reservation data for analysis and reporting
- When I cancel user reservations, appropriate refunds are processed automatically

### Story 14: Outstanding Payment Management

**As an** admin  
**I want to** ensure members with outstanding reservation bills pay before making new reservations  
**So that** we maintain financial accountability and prevent accumulating unpaid balances  

**Acceptance Criteria:**

- System blocks new reservations for members with unpaid reservation bills
- Members can see their outstanding balances clearly in their dashboard
- Payment reminders are sent automatically for overdue reservation payments
- I can see all members with outstanding reservation payments
- I can manually flag or unflag members' reservation privileges
- Grace periods can be configured for payment processing delays
- Members receive clear messaging about why they can't book when payments are outstanding
- Emergency exceptions can be made by admins for urgent situations

### Story 15: Off-Hours Feature Management

**As an** admin  
**I want to** control off-hours reservation availability through feature flags  
**So that** I can enable or disable unsupervised access based on operational needs  

**Acceptance Criteria:**

- I can enable or disable off-hours reservations system-wide through admin settings
- When disabled, off-hours time slots are not available for booking
- When enabled, off-hours slots appear with appropriate payment requirements
- Feature flag changes take effect immediately for new reservations
- Existing off-hours reservations are preserved when feature is disabled
- I can configure which hours are considered "off-hours" vs "supervised hours"
- Feature flag status is clearly visible in admin dashboard
- I can temporarily disable off-hours access for maintenance or policy changes

### Story 16: Space Utilization Analytics

**As an** admin  
**I want to** analyze practice space usage patterns  
**So that** I can make informed decisions about pricing and scheduling policies  

**Acceptance Criteria:**

- I can see utilization rates by day, week, and month
- I can see peak usage times and underutilized periods
- I can analyze sustaining member usage vs. regular member usage
- I can see revenue generated from practice space reservations
- I can track cancellation patterns and identify issues
- I can see average session duration and booking lead times
- Analytics help optimize pricing, policies, and space management

## Integration Stories

### Story 17: Reservation + Production Integration

**As a** production manager  
**I want** production schedules to block practice space availability  
**So that** there are no conflicts between events and practice sessions  

**Acceptance Criteria:**

- Productions using practice space automatically block reservation availability
- Production setup and breakdown time can extend the blocked period
- Members see production conflicts when trying to book practice time
- Production changes automatically update practice space availability
- System suggests alternative practice times when productions cause conflicts
- Production managers can see if any reservations need to be moved for events

### Story 18: Reservation + Member Profile Integration

**As a** CMC member  
**I want** my reservation activity integrated with my member profile  
**So that** my practice space usage reflects my community engagement  

**Acceptance Criteria:**

- My member profile can show my practice space activity level (if I choose)
- Active practice space users may be highlighted in member discovery
- Reservation activity contributes to community engagement metrics
- I can control visibility of my practice space usage in my profile
- Practice space usage history helps other members see my commitment level
- Integration respects all privacy settings and member preferences

### Story 19: Reservation + Band Integration

**As a** band member  
**I want to** book practice space for my band  
**So that** we can rehearse together and coordinate our schedule  

**Acceptance Criteria:**

- I can specify which band a reservation is for during booking
- Band members can see reservations made for band practice
- Band practice sessions are distinguished from solo practice in my history
- Band admins can make reservations on behalf of the band
- Band members can be notified of upcoming band practice sessions
- Band practice history is visible to all band members (respecting privacy)
