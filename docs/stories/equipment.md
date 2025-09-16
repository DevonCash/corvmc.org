# Equipment User Stories

## Equipment Donation & Acquisition

### Story 1: Equipment Donation

**As a** CMC member or community supporter  
**I want to** donate equipment to the CMC gear lending library  
**So that** other musicians can benefit from gear I no longer need  

**Acceptance Criteria:**

- I can submit equipment donation information through a form or contact process
- CMC staff can record my donation with full details (name, type, brand, model, condition)
- My contact information is stored as the donor
- I receive acknowledgment of my donation for potential tax purposes
- The equipment is marked as "donated" and "CMC owned" in the system
- I can see a list of equipment I've donated if I'm a CMC member

### Story 2: Equipment Loan to CMC

**As a** CMC member  
**I want to** loan my equipment to CMC temporarily  
**So that** other members can use it while I retain ownership  

**Acceptance Criteria:**

- I can specify loan terms including return date
- CMC staff records the loan with my contact information
- The equipment is marked as "loaned to CMC" with return date tracking
- I receive reminders before the return due date
- I can request early return of my equipment
- The equipment is returned to me in the same condition (or better)

### Story 3: Equipment Intake Processing

**As a** CMC staff member  
**I want to** process incoming equipment donations and loans  
**So that** they can be added to our lending library efficiently  

**Acceptance Criteria:**

- I can record comprehensive equipment details (photos, condition, serial numbers)
- I can link equipment to the donor/lender's member account or external contact
- I can set appropriate status (available, needs maintenance, etc.)
- I can assign storage location and estimated value
- I can add intake notes about condition or special considerations

## Equipment Lending to Members

### Story 4: Browse Available Equipment

**As a** CMC member  
**I want to** browse available equipment in the lending library  
**So that** I can find gear I need for practice, recording, or performances  

**Acceptance Criteria:**

- I can view all available equipment with photos and details
- I can filter by equipment type (guitar, bass, amplifier, etc.)
- I can see equipment condition, brand, model information
- I can check rental fees and security deposit requirements
- I can see if I'm eligible to borrow (no overdue items, member in good standing)

### Story 5: Request Equipment Checkout

**As a** CMC member  
**I want to** request to checkout equipment  
**So that** I can use it for my musical activities  

**Acceptance Criteria:**

- I can request specific equipment for specific dates
- I can see rental fees and security deposit amounts
- I can specify the purpose and duration of use
- CMC staff receives my request for approval
- I can cancel my request before staff begins preparation
- I receive confirmation once approved with pickup instructions

### Story 6: Equipment Checkout Process

**As a** CMC staff member  
**I want to** process equipment checkouts with proper preparation and handoff  
**So that** members can safely borrow gear with complete tracking  

**Acceptance Criteria:**

- I can prepare requested equipment by checking current condition and taking photos
- I can mark equipment as ready for member pickup
- When member arrives for pickup, I can complete the handoff and record:
  - Checkout date, due date, and condition when checked out
  - Security deposits and rental fees through our payment system
  - Link the loan to member transactions for financial tracking
  - Checkout notes and photos if needed
- System automatically flags equipment as overdue if past due date
- I can set automatic reminders for return dates

### Story 7: Member Return Scheduling

**As a** CMC member  
**I want to** schedule my equipment return dropoff  
**So that** I can return gear at a convenient time and avoid late fees  

**Acceptance Criteria:**

- I can schedule a return appointment for my borrowed equipment
- I can see available dropoff times that work with CMC staff schedules
- I can reschedule my appointment if my plans change
- I can note any condition issues or damage I'm aware of
- I receive confirmation of my scheduled dropoff time

### Story 8: Equipment Return Processing

**As a** CMC staff member  
**I want to** process equipment returns with thorough inspection  
**So that** gear is properly evaluated and made available for others  

**Acceptance Criteria:**

- When equipment arrives for return, I can inspect and process it by:
  - Recording return date and condition when returned
  - Comparing return condition to checkout condition
  - Documenting any damage with photos and notes
- If damage is found, I can flag it for assessment and repair cost evaluation
- If no issues, I can complete the return process and:
  - Process security deposit refunds or charges for damage
  - Mark equipment as available or send to maintenance as needed

## Equipment Management & Maintenance

### Story 9: Equipment Maintenance Tracking

**As a** CMC staff member  
**I want to** track equipment maintenance needs  
**So that** gear stays in good condition for member use  

**Acceptance Criteria:**

- I can mark equipment as needing maintenance
- I can track maintenance history and costs
- I can schedule regular maintenance for high-use items
- I can mark equipment as out of service temporarily
- I can update equipment condition after maintenance

### Story 10: Overdue Equipment Management

**As a** CMC staff member  
**I want to** track and manage overdue equipment  
**So that** gear is returned promptly and fairly  

**Acceptance Criteria:**

- I can see all overdue equipment loans in one view
- I can send automated reminders to borrowers
- I can track how many days items are overdue
- I can apply late fees or penalties as appropriate
- I can restrict borrowing privileges for repeat offenders

### Story 11: Equipment Statistics & Reporting

**As a** CMC administrator  
**I want to** view equipment library statistics  
**So that** I can make informed decisions about the program  

**Acceptance Criteria:**

- I can see total equipment value by acquisition type (donated vs purchased)
- I can view popular equipment and usage patterns
- I can track loan revenue and expenses
- I can generate donor acknowledgment reports
- I can see which equipment needs replacement or retirement

## Member Experience

### Story 12: View My Equipment Activity

**As a** CMC member  
**I want to** see my equipment borrowing history  
**So that** I can track what I've used and when  

**Acceptance Criteria:**

- I can see all my past and current equipment loans
- I can view loan dates, return dates, and fees paid
- I can see my current borrowing status and any restrictions
- I can view photos and condition reports from my loans
- I can request extensions on current loans if allowed

## Advanced Reservation Features

### Story 16: Reserve Equipment for Future Events

**As a** CMC member planning a recording session, gig, or special project  
**I want to** reserve specific equipment weeks or months in advance  
**So that** I can guarantee availability for important dates  

**Acceptance Criteria:**

- I can book equipment for future dates (not just immediate checkout)
- I can see equipment availability in a calendar view for planning
- I can specify the exact reservation period (start time and end time)
- I can reserve multiple pieces of equipment for the same event
- I receive confirmation of my future reservation with all details
- I can view and manage all my upcoming reservations in one place
- The system prevents conflicts and double-booking automatically

### Story 17: Equipment Availability Calendar

**As a** CMC member or staff member  
**I want to** view equipment availability in a calendar interface  
**So that** I can easily find optimal booking times and avoid conflicts  

**Acceptance Criteria:**

- I can see a calendar view showing when equipment is available vs reserved
- I can filter the calendar by equipment type or specific items
- I can see existing reservations (mine and others if staff) with borrower info
- I can click on available time slots to start a new reservation
- I can see equipment that's in maintenance or otherwise unavailable
- I can view daily, weekly, and monthly calendar layouts
- I can export calendar data for planning purposes

### Story 18: Automatic Conflict Detection

**As a** CMC staff member processing reservations  
**I want to** have the system prevent double-booking of equipment  
**So that** members don't show up to conflicting reservations  

**Acceptance Criteria:**

- The system automatically checks for conflicts when creating reservations
- I receive clear error messages if attempting to book unavailable equipment
- The system suggests alternative available times for conflicting requests
- I can see exactly which existing reservation is causing the conflict
- The system accounts for equipment preparation and cleaning time between loans
- Production and event equipment blocks are respected in conflict detection

### Story 19: Reservation Modifications

**As a** CMC member with an existing reservation  
**I want to** modify my reservation details if plans change  
**So that** I can adapt to changing schedules without cancelling entirely  

**Acceptance Criteria:**

- I can extend or shorten my reservation period if equipment is available
- I can reschedule my reservation to different dates if available
- I can add additional equipment to an existing reservation
- I can remove equipment from a reservation (with partial refunds if applicable)
- I can cancel reservations with appropriate notice (full refunds if early enough)
- Modifications check for conflicts and availability automatically
- I receive updated confirmation emails after making changes

### Story 20: Equipment Blocking for Productions

**As a** CMC event coordinator or production manager  
**I want to** block equipment availability during productions and events  
**So that** gear needed for shows isn't accidentally loaned to members  

**Acceptance Criteria:**

- I can mark equipment as unavailable for production periods
- Production blocks take priority over member reservations
- The system prevents new reservations during blocked periods
- Existing reservations are flagged if they conflict with new production needs
- I can specify which equipment is needed for each production
- Production blocks appear in the equipment availability calendar
- I can coordinate with existing reservations to minimize conflicts

### Story 21: Band Equipment Coordination

**As a** band member planning group activities  
**I want to** coordinate equipment reservations with my bandmates  
**So that** we can ensure all needed gear is available for rehearsals or shows  

**Acceptance Criteria:**

- I can link my reservation to other band members' reservations
- I can see what equipment my bandmates have already reserved
- I can create "group reservations" that reserve multiple items simultaneously
- Band members can share access to view and modify group reservations
- The system can suggest complete equipment packages for band setups
- I can split costs among band members for group reservations

### Story 22: Waitlist Management

**As a** CMC member wanting unavailable equipment  
**I want to** join a waitlist when equipment is already reserved  
**So that** I'm automatically notified if earlier reservations are cancelled  

**Acceptance Criteria:**

- I can join a waitlist for specific equipment and date ranges
- I'm automatically notified (email/SMS) when equipment becomes available
- I have a limited time to claim the available slot before it goes to the next person
- I can see my position in the waitlist queue
- I can join multiple waitlists for different equipment or dates
- I can remove myself from waitlists if plans change
- Staff can manage waitlists and manually reassign cancelled reservations

### Story 23: Bulk Equipment Reservations

**As a** CMC staff member organizing workshops, classes, or events  
**I want to** reserve multiple pieces of equipment simultaneously  
**So that** I can ensure complete setups are available for programs  

**Acceptance Criteria:**

- I can select multiple equipment items and reserve them as a package
- I can create equipment "kits" or "bundles" for common use cases
- I can reserve equipment for multiple participants in a class or workshop
- The system checks availability for all items before confirming the bulk reservation
- I can apply bulk discounts or special pricing for educational programs
- I can duplicate reservations across multiple dates for recurring programs

### Story 24: Recurring Equipment Reservations

**As a** CMC member with a regular practice schedule  
**I want to** book the same equipment on a recurring basis  
**So that** I don't have to manually re-request gear for routine sessions  

**Acceptance Criteria:**

- I can set up weekly, bi-weekly, or monthly recurring reservations
- I can specify an end date for the recurring series or make it ongoing
- I can modify or cancel individual instances within a recurring series
- I can modify the entire recurring series at once
- The system checks for conflicts with each recurring instance
- I receive reminders before each recurring reservation
- I can pause recurring reservations temporarily without cancelling the series

### Story 25: Equipment Recommendation Engine

**As a** CMC member looking for equipment  
**I want to** receive suggestions for alternative equipment when my first choice is unavailable  
**So that** I can find suitable substitutes for my musical needs  

**Acceptance Criteria:**

- The system suggests similar equipment when my preferred item is unavailable
- Recommendations are based on equipment type, specifications, and member reviews
- I can see why each alternative is suggested (similar features, etc.)
- I can compare recommended alternatives side-by-side
- The system learns from my past borrowing patterns to improve suggestions
- I can save favorite alternatives for future reference
- Recommendations include availability dates for suggested alternatives

### Story 13: Equipment Damage Reporting

**As a** CMC member  
**I want to** report equipment damage that occurs during my loan  
**So that** I can be transparent and help maintain the equipment library  

**Acceptance Criteria:**

- I can report damage through the member portal
- I can upload photos of damage and provide descriptions
- I can see any damage charges applied to my account
- I understand the damage policy and replacement costs
- I can appeal damage charges if I disagree

## Equipment Return to Owners

### Story 14: Loan Return Notifications

**As a** CMC staff member  
**I want to** track equipment that needs to be returned to original owners  
**So that** we honor our agreements with lenders  

**Acceptance Criteria:**

- I can see all equipment with upcoming return due dates
- I can send return notifications to original owners
- I can schedule return appointments and handoffs
- I can mark equipment as returned to owner
- I can update equipment status to prevent further loans

### Story 15: Donor Recognition

**As a** CMC administrator  
**I want to** recognize and thank equipment donors  
**So that** we maintain good relationships and encourage future donations  

**Acceptance Criteria:**

- I can generate donor acknowledgment letters for tax purposes
- I can track total donation value per donor
- I can create recognition displays or communications
- I can invite donors to special events or programs
- I can provide updates on how their donated equipment is being used