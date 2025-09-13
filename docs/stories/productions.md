# Production Management User Stories

## Core Production Lifecycle

### Story 1: Create New Production

**As a** production manager or admin  
**I want to** create a new CMC production  
**So that** I can organize and promote upcoming shows and events  

**Acceptance Criteria:**

- I can create a production with title, description, and basic details
- I can set the date, time, and duration (start, end, doors open)
- I can specify the location (at CMC or external venue with address details)
- I can assign myself or another user as the production manager
- I can add genre tags and production type flags
- The production starts in "pre-production" status
- I receive a notification confirming the production was created
- Other stakeholders are not notified until the production is published

### Story 2: Update Production Details

**As a** production manager  
**I want to** update production information  
**So that** I can keep details current and accurate  

**Acceptance Criteria:**

- I can edit all production details (title, description, timing, location)
- I can update genre tags and production flags
- Significant changes (date, time, venue) trigger notifications to interested parties
- Minor changes (description updates) don't spam notifications
- Changes are immediately visible to authorized users
- I can see a history of changes made to the production

### Story 3: Production Status Management

**As a** production manager  
**I want to** control the production status lifecycle  
**So that** I can manage when information becomes public and coordinate with performers  

**Acceptance Criteria:**

- I can publish a production to make it visible to the public
- Publishing triggers notifications to all performers and stakeholders
- I can unpublish a production if changes are needed
- I can mark a production as completed after the event
- I can cancel a production with an optional reason
- Cancellations immediately notify all interested parties
- Status changes are logged and visible to managers

## Performer Management

### Story 4: Add Performers to Production

**As a** production manager  
**I want to** add performers (bands or solo artists) to the production lineup  
**So that** I can build a diverse performance roster  

**Acceptance Criteria:**

- I can search for bands by name to add to the lineup
- I can search for CMC members to add as solo performers
- I can specify each performer's order in the lineup
- I can set each performer's allocated set length (in minutes)
- Performers are automatically placed at the end of the lineup if no order is specified
- I can't add the same performer twice to one production
- For bands: all band members are notified when their band is added
- For solo artists: the individual member is notified when added as a performer
- Both bands and solo artists appear clearly distinguished in the lineup

### Story 5: Manage Performance Lineup

**As a** production manager  
**I want to** organize and reorder the performance lineup  
**So that** I can create the optimal show flow  

**Acceptance Criteria:**

- I can reorder performers (bands and solo artists) by dragging and dropping or setting specific positions
- I can update individual performer set lengths as planning progresses
- I can remove performers from the lineup if plans change
- Lineup changes are immediately visible to all affected performers
- I can see the total runtime based on all set lengths
- Both band members and solo artists are notified of lineup changes that affect them

### Story 6: Performer Production Details

**As a** performer (band member or solo artist)  
**I want to** see my performance details for productions  
**So that** I can prepare appropriately and coordinate as needed  

**Acceptance Criteria:**

- I can see all productions I'm scheduled to perform in (either as part of a band or as a solo artist)
- I can see my set length allocation and performance order
- I can see other performers on the lineup and their set lengths
- I can see production details (date, venue, doors/show times)
- I receive notifications about production updates that affect my performances
- I can contact the production manager if needed
- As a solo artist, I can see my individual performance details
- As a band member, I can see my band's performance details

## Production Discovery & Public Access

### Story 7: Browse Upcoming Productions

**As a** CMC member or public visitor  
**I want to** discover upcoming CMC productions  
**So that** I can attend shows and support the community  

**Acceptance Criteria:**

- I can see all published, upcoming productions in chronological order
- I can search productions by title, description, venue, or city
- I can filter productions by genre tags
- I can see basic details (date, time, venue, lineup) without logging in
- I can see ticket information and how to attend
- Past productions are archived and searchable

### Story 8: Production Detail View

**As someone** interested in a production  
**I want to** see comprehensive production information  
**So that** I can decide whether to attend and plan accordingly  

**Acceptance Criteria:**

- I can see complete production details (title, description, full lineup)
- I can see venue information and directions
- I can see timing details (doors, show start, expected end)
- I can see information about each performing band
- I can access social media links and promotional materials
- I can share the production with others

## Advanced Production Management

### Story 9: Transfer Production Management

**As a** production manager  
**I want to** transfer management responsibilities to someone else  
**So that** productions can continue if I become unavailable  

**Acceptance Criteria:**

- I can assign production management to any other qualified user
- The new manager is notified and gains full management permissions
- I retain access to view the production but lose management privileges
- All stakeholders are notified of the management change
- Production history shows the management transfer

### Story 10: Duplicate Existing Production

**As a** production manager  
**I want to** duplicate a successful production with a new date  
**So that** I can easily create recurring or similar events  

**Acceptance Criteria:**

- I can select any existing production to use as a template
- I can specify new date and timing for the duplicate
- All production details, lineup, and settings are copied
- The new production starts in "pre-production" status
- I can modify the duplicated production as needed
- Performers are not automatically notified until the new production is published

### Story 11: Production Analytics

**As an** admin or production manager  
**I want to** see production statistics and performance metrics  
**So that** I can understand our programming success and make improvements  

**Acceptance Criteria:**

- I can see overall production statistics (total, published, upcoming, completed, cancelled)
- I can see which genres are most popular
- I can see which venues host the most events
- I can see average lineup sizes and set lengths
- I can see production manager workload distribution
- I can export data for further analysis

## Notification & Communication

### Story 12: Production Communication Hub

**As a** production manager  
**I want** centralized communication tools for each production  
**So that** I can coordinate effectively with all stakeholders  

**Acceptance Criteria:**

- I can send announcements to all production participants
- Band members receive notifications about productions they're involved in
- Managers receive notifications about productions they oversee
- Sustaining members can opt into notifications about new published productions
- I can see who has been notified and when
- Communication preferences can be managed by recipients

### Story 13: Performer Coordination

**As a** performer (band member or solo artist) in a production  
**I want to** coordinate with the production manager and other performers  
**So that** my performance goes smoothly  

**Acceptance Criteria:**

- I can see contact information for the production manager
- I can see other performers on the lineup and potential collaboration opportunities
- I can submit technical requirements or special needs to the manager
- I can confirm my participation and availability (as individual or band)
- I can receive production updates and schedule changes
- I can report issues or conflicts to the production manager
- As a solo artist, I can coordinate my individual performance needs
- As a band member, I can coordinate on behalf of my band

## Integration Stories

### Story 14: Production + Practice Space Integration

**As a** performer in a production  
**I want to** easily book practice space to prepare for the show  
**So that** I can rehearse my set effectively  

**Acceptance Criteria:**

- When viewing production details, I can see links to book practice space
- Practice space booking can reference the specific production
- Band members can see practice sessions booked for production prep
- Solo artists can book individual practice time for production prep
- Production managers can see which performers are actively rehearsing
- Practice space conflicts with production dates are highlighted
- Both band rehearsals and solo practice sessions can be associated with productions

### Story 15: Production + Member Directory Integration

**As a** production manager  
**I want to** discover and invite performers from the member directory  
**So that** I can build diverse and engaging lineups  

**Acceptance Criteria:**

- I can search the member directory for bands by genre or availability
- I can search for individual members who might perform as solo artists
- I can see which bands are seeking performance opportunities
- I can see which members are available for solo performances
- I can see performer profiles and recent activity when considering lineups
- I can invite bands or solo artists directly from the member directory interface
- Performer discovery suggestions are based on production genre and style
- Solo artists with relevant skills/genres are suggested alongside bands

### Story 16: Production + Calendar Integration (Future)

**As a** CMC member  
**I want to** see productions integrated with the community calendar  
**So that** I can coordinate my schedule and discover events  

**Acceptance Criteria:**

- Published productions automatically appear in the community calendar
- I can see my band's production commitments in my personal calendar
- Production conflicts with other CMC events are identified
- I can export production dates to my external calendar applications
- *Note: Depends on Community Calendar feature implementation*

Would you like me to add any additional user stories or modify these before moving on to the next service?