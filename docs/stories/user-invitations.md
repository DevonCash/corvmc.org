# User Invitation User Stories

## Core Invitation Management

### Story 1: Invite New User to CMC

**As an** admin or staff member  
**I want to** invite someone to join the Corvallis Music Collective  
**So that** we can grow our community with new members  

**Acceptance Criteria:**

- I can invite a user by email address
- I can specify what roles they should receive (member, admin, moderator, etc.)
- System creates a temporary user account with invitation status
- An invitation email is sent with a secure registration link
- The invitation expires after a reasonable time (1 week)
- I can see a list of all pending invitations
- I can resend invitations that haven't been accepted yet

### Story 2: Accept User Invitation

**As someone** who received a CMC invitation  
**I want to** complete my registration using the invitation link  
**So that** I can join the CMC community  

**Acceptance Criteria:**

- I receive a clear invitation email with next steps
- I can click the invitation link to start registration
- I can set my name and password during registration
- My email is automatically verified upon completion
- I receive the roles that were specified in the invitation
- I receive a welcome notification after successful registration
- Expired invitation links show an appropriate error message

### Story 3: Manage Pending Invitations

**As an** admin  
**I want to** view and manage all pending invitations  
**So that** I can track recruitment efforts and clean up old invitations  

**Acceptance Criteria:**

- I can see a list of all pending invitations with details (email, roles, date sent)
- I can resend invitations that haven't been accepted
- I can cancel pending invitations that are no longer needed
- I can see invitation statistics (sent, accepted, expired)
- I can filter pending invitations by role or date
- System shows acceptance rate and other useful metrics

### Story 4: Invitation Token Security

**As a** system administrator  
**I want** invitation tokens to be secure and time-limited  
**So that** the invitation system cannot be abused  

**Acceptance Criteria:**

- Invitation tokens are encrypted and cannot be easily forged
- Tokens automatically expire after 1 week
- Expired tokens cannot be used for registration
- Invalid or tampered tokens are rejected gracefully
- Token validation includes user email verification
- System logs security-related invitation events

## Band Leadership Invitations

### Story 5: Invite New User with Band Ownership

**As an** admin  
**I want to** invite someone to join CMC and immediately own/manage a band  
**So that** we can onboard band leaders from outside the current community  

**Acceptance Criteria:**

- I can invite a user and simultaneously create a band profile for them
- I can specify the band name and initial details during invitation
- The band is created in "pending_owner_verification" status
- The invitation email explains both CMC membership and band ownership
- When they complete registration, the band becomes active
- They automatically become the band owner and admin member
- Existing users can also be assigned new band ownership

### Story 6: Band Ownership Confirmation

**As someone** invited to own a band  
**I want to** understand what band ownership entails  
**So that** I can make an informed decision about accepting  

**Acceptance Criteria:**

- The invitation email clearly explains band ownership responsibilities
- I can see the band name and basic details before completing registration
- Upon registration completion, I see the band in my dashboard
- I have full administrative control over the band profile
- I can manage band members, update information, and handle band settings
- If I decline (by not completing registration), the band remains pending

## Advanced Invitation Workflows

### Story 7: Bulk User Invitations

**As an** admin  
**I want to** send multiple invitations at once  
**So that** I can efficiently onboard groups of new members  

**Acceptance Criteria:**

- I can import a list of email addresses (CSV or manual entry)
- I can apply the same roles to all invitations in the batch
- System sends individual invitations to each email address
- I get a summary of successful invitations and any failures
- Duplicate email addresses are handled appropriately
- Existing users are skipped with appropriate notification

### Story 8: Invitation Analytics

**As an** admin  
**I want to** track invitation effectiveness  
**So that** I can improve our recruitment process  

**Acceptance Criteria:**

- I can see overall invitation statistics (sent, accepted, completion rate)
- I can see breakdown by role type and time period
- I can identify which invitations are taking longest to accept
- I can see trends in invitation acceptance over time
- System tracks time from invitation to completion
- Reports help identify successful recruitment strategies

### Story 9: Invitation Customization

**As an** admin  
**I want to** customize invitation messages  
**So that** invitations feel personal and context-appropriate  

**Acceptance Criteria:**

- I can add a personal message to invitation emails
- I can specify the sender name and contact info
- Different invitation types (regular vs band ownership) have appropriate templates
- I can preview the email before sending
- Custom messages are preserved if invitations need to be resent
- Templates maintain CMC branding and important legal/policy information

## Integration Stories

### Story 10: Production Staff Invitations

**As a** production manager  
**I want to** invite external collaborators for specific productions  
**So that** I can work with guest directors, sound engineers, and other specialists  

**Acceptance Criteria:**

- I can invite users with temporary production-specific roles
- Invitations can include context about the specific production
- Invited users have access only to relevant production information
- Production-specific permissions are automatically removed after the event
- Integration with production management workflows

### Story 11: Invitation + Member Profile Setup

**As someone** completing an invitation  
**I want** guidance on setting up my member profile  
**So that** I can fully participate in the CMC community  

**Acceptance Criteria:**

- After completing basic registration, I'm guided to create my member profile
- I can add my skills, genres, and musical background during onboarding
- I can set my profile visibility preferences immediately
- I can upload a profile photo during the initial setup
- I can skip profile setup and complete it later if needed
- Integration with member profile creation workflows

### Story 12: Role-Based Invitation Permissions

**As a** system designer  
**I want** invitation permissions to be role-based  
**So that** only appropriate people can invite new users  

**Acceptance Criteria:**

- Only users with 'admin' or 'staff' roles can send invitations
- Different roles may have different invitation capabilities (e.g., band leaders can invite band-specific roles)
- Permission checks prevent unauthorized invitation attempts
- Audit trail tracks who sent each invitation
- Role restrictions are enforced at both UI and API levels
