# Staff Profile Management User Stories

## Core Staff Profile Management

### Story 1: Create Staff Profile

**As an** admin  
**I want to** create staff profiles for CMC leadership and volunteers  
**So that** community members can see who runs and supports the organization  

**Acceptance Criteria:**

- I can create staff profiles with name, title, bio, and contact information
- I can upload profile photos for staff members
- I can specify the profile type (board member, staff, volunteer, etc.)
- I can set the display order for how profiles appear on the website
- I can mark profiles as active or inactive
- Staff profiles can be linked to existing CMC user accounts if the person is also a member
- All staff profile information is immediately visible on the public website

### Story 2: Staff Member Self-Edit

**As a** staff member  
**I want to** edit my own staff profile information  
**So that** I can keep my information current and accurate  

**Acceptance Criteria:**

- I can edit my bio, contact information, and personal details
- I can upload or update my profile photo
- I cannot change my title, role type, or display order (admin-only fields)
- My changes are saved as "pending approval" and not immediately public
- I can see the current public version vs. my pending changes
- I receive notification when my changes are approved or rejected
- I can make additional edits while previous changes are pending review

### Story 3: Admin Profile Approval Workflow

**As a** site admin  
**I want to** approve all staff profile modifications before they appear publicly  
**So that** I can ensure all public staff information maintains professional standards  

**Acceptance Criteria:**

- I can see all pending staff profile changes in a review queue
- I can compare proposed changes with current public information
- I can approve changes to make them live on the public website
- I can reject changes with explanatory notes sent to the staff member
- I can edit changes before approval if minor adjustments are needed
- Approved changes are immediately reflected on the public website
- I receive notifications when new profile changes are submitted for review
- I can bulk approve multiple non-controversial changes

### Story 4: Update Staff Profiles (Admin Direct Edit)

**As an** admin  
**I want to** directly update staff profile information  
**So that** I can make administrative changes without approval workflow  

**Acceptance Criteria:**

- I can edit all staff profile information (name, title, bio, contact)
- I can update or replace profile photos
- I can change profile types and display order
- I can activate or deactivate staff profiles as people's roles change
- I can link or unlink staff profiles from user accounts
- My direct updates are immediately reflected on the public website (bypass approval)
- I can bulk update multiple staff profiles when needed
- Staff members are notified when I make changes to their profiles

### Story 5: Staff Profile Organization

**As an** admin  
**I want to** organize staff profiles by type and display order  
**So that** the website presents leadership information in a logical structure  

**Acceptance Criteria:**

- I can categorize staff profiles as board members, staff, or volunteers
- I can set custom display order for profiles within each category
- I can drag and drop to reorder staff profiles easily
- Board members and staff can be displayed in separate sections
- Inactive profiles are hidden from public view but preserved in admin
- Profile organization is immediately reflected on the public website

## Public Display Features

### Story 6: Public Staff Directory

**As a** website visitor  
**I want to** see who leads and supports the Corvallis Music Collective  
**So that** I can learn about the organization and contact appropriate people  

**Acceptance Criteria:**

- I can see active staff profiles displayed prominently on the website
- Staff profiles are organized by type (board, staff, volunteers)
- Each profile shows name, title, bio, and contact information (if provided)
- Profile photos make the staff directory more personal and engaging
- Contact information respects privacy settings and organizational policies
- The staff directory is mobile-friendly and loads quickly

### Story 7: Staff Profile Detail View

**As a** website visitor interested in a specific staff member  
**I want to** see detailed information about their role and background  
**So that** I can understand their expertise and how to connect with them  

**Acceptance Criteria:**

- I can click on staff profiles to see expanded biographical information
- Staff members' roles and responsibilities are clearly explained
- Contact information is provided when appropriate
- Staff profiles can link to their member profiles if they're also CMC members
- Profile photos and information present the organization professionally
- Staff achievements and background relevant to CMC are highlighted

## Administrative Features

### Story 8: Staff Profile Analytics

**As an** admin  
**I want to** see statistics about staff profiles and engagement  
**So that** I can understand how the community interacts with leadership information  

**Acceptance Criteria:**

- I can see total counts of active and inactive staff profiles
- I can see breakdown by profile type (board, staff, volunteers)
- I can see which staff profiles are most viewed or engaged with
- Analytics help me understand whether staff information is effectively presented
- I can export staff profile data for organizational reporting
- Statistics help guide decisions about staff directory improvements

### Story 9: Bulk Staff Management

**As an** admin during organizational transitions  
**I want to** manage multiple staff profiles efficiently  
**So that** I can update leadership information quickly during board changes or transitions  

**Acceptance Criteria:**

- I can select multiple staff profiles for bulk operations
- I can bulk update profile types (e.g., move multiple people from staff to board)
- I can bulk activate or deactivate profiles during organizational changes
- I can export all staff profile information for backup or reporting
- I can import staff profile information from spreadsheets when needed
- Bulk operations include confirmation steps to prevent accidental changes

## Integration Features

### Story 10: Staff Profile + User Account Integration

**As a** staff member who is also a CMC member  
**I want** my staff profile linked to my user account  
**So that** my involvement is visible and my information stays synchronized  

**Acceptance Criteria:**

- My staff profile can be linked to my CMC member account
- Changes to my member profile information can sync to my staff profile
- Other members can see my leadership role when viewing my member profile
- I can control what information syncs between my profiles
- Linking preserves the privacy settings of both profile types
- Staff members without CMC memberships can still have complete staff profiles

### Story 11: Staff Profile + Production Integration

**As a** production manager  
**I want to** easily contact staff members relevant to my production  
**So that** I can get appropriate support and approvals  

**Acceptance Criteria:**

- Production managers can access staff contact information from admin areas
- Staff roles and expertise are clear so managers contact the right people
- Staff profiles indicate who handles what types of production issues
- Contact preferences and protocols are clearly communicated
- Integration maintains professional boundaries while enabling necessary communication

### Story 12: Staff Profile + Member Communication

**As a** CMC member  
**I want to** easily find and contact appropriate staff members  
**So that** I can get help, provide feedback, or volunteer for leadership roles  

**Acceptance Criteria:**

- I can find staff contact information easily from the website
- Staff profiles clearly indicate what types of inquiries each person handles
- Contact methods respect staff members' preferences and availability
- Staff profiles can indicate if someone is accepting volunteers or help
- Communication channels are professional and appropriate
- Staff profiles help members understand organizational structure

## Governance & Transparency

### Story 13: Board Member Transparency

**As a** community member  
**I want to** see current board member information  
**So that** I understand CMC's governance and can engage appropriately  

**Acceptance Criteria:**

- Board member profiles are prominently displayed and clearly identified
- Board member roles, terms, and responsibilities are explained
- I can see board member qualifications and relevant experience
- Board meeting information and contact procedures are accessible
- Board profiles help me understand how CMC is governed
- Information supports transparency and community trust

### Story 14: Volunteer Recognition

**As a** volunteer who contributes to CMC  
**I want** recognition for my contributions through staff profiles  
**So that** my work is acknowledged and visible to the community  

**Acceptance Criteria:**

- Volunteer staff profiles recognize significant community contributions
- Volunteer roles and contributions are clearly described
- Volunteers can choose their level of visibility and contact information
- Volunteer recognition encourages continued participation
- Profile information helps other members understand volunteer opportunities
- Recognition balances visibility with volunteers' privacy preferences

Would you like me to continue with ReportService user stories next?
