# Band User Stories

## Core Band Creation & Management

### Story 1: Band Creation

**As a** CMC member  
**I want to** create a band profile  
**So that** I can represent my musical group and connect with other musicians  

**Acceptance Criteria:**

- I can create a band with name, bio, hometown, and contact info
- I can upload a band photo/avatar
- I can add social media links and website
- I can add genre tags and musical influences
- I automatically become the band owner/admin
- The band profile is immediately active and visible to other CMC members

### Story 2: Band Profile Management

**As a** band owner  
**I want to** update my band's profile information  
**So that** it stays current and represents us accurately  

**Acceptance Criteria:**

- I can edit all band details (name, bio, photos, links, genres)
- I can set band visibility (public, members-only, private)
- I can upload/change band photos
- Changes are immediately visible to authorized users

## Member Invitation & Management

### Story 3: Invite Existing CMC Member

**As a** band admin  
**I want to** invite existing CMC members to join my band  
**So that** I can build my band roster from the CMC community  

**Acceptance Criteria:**

- I can search for CMC members by name/email
- I can send an invitation specifying their role (member, admin) and position (guitarist, vocalist, etc.)
- The invited member receives a notification
- The invitation shows in their pending invitations list
- I can cancel pending invitations
- I can resend invitations

### Story 4: Accept/Decline Band Invitation (Existing User)

**As a** CMC member  
**I want to** respond to band invitations  
**So that** I can join bands I'm interested in or decline politely  

**Acceptance Criteria:**

- I can view all my pending band invitations
- I can see band details (name, genre, other members) before deciding
- I can accept an invitation and become an active band member
- I can decline an invitation with optional message
- Band admins are notified of my decision
- Declined invitations can be re-sent by band admins

## Advanced Member Management

### Story 7: Member Role Management

**As a** band owner/admin  
**I want to** manage member roles and permissions  
**So that** I can delegate responsibilities appropriately  

**Acceptance Criteria:**

- I can promote members to admin role
- I can demote admins to regular members (unless they're the owner)
- I can update member positions/instruments
- I can remove members from the band
- Role changes are logged and members are notified

### Story 8: Leave Band

**As a** band member  
**I want to** leave a band I'm no longer active in  
**So that** I can keep my profile current  

**Acceptance Criteria:**

- I can leave any band where I'm a regular member
- Band admins are notified when I leave
- I cannot leave a band where I'm the owner (must transfer ownership first)

### Story 9: Add Guest/External Members

**As a** band admin  
**I want to** add band members who don't have CMC accounts  
**So that** I can represent my complete band roster including session musicians, former members, or musicians who aren't CMC members  

**Acceptance Criteria:**

- I can add a member with just their name and role/position (no CMC account required)
- Guest members appear in the band roster but can't log in or receive notifications
- I can specify their role (member, session musician, former member, etc.) and position
- I can edit or remove guest member entries
- Guest members are clearly distinguished from CMC members in the roster
- If a guest member later joins CMC with the same name/info, I can link their existing entry to their new account

### Story 10: Ownership Transfer

**As a** band owner  
**I want to** transfer ownership to another admin  
**So that** the band can continue if I step back  

**Acceptance Criteria:**

- I can only transfer ownership to existing band admins (must be CMC members, not guest members)
- The new owner is notified and must accept
- I become a regular admin after transfer
- All band permissions transfer to the new owner

## Discovery & Visibility

### Story 11: Band Discovery

**As a** CMC member  
**I want to** discover bands in the community  
**So that** I can find collaboration opportunities or follow interesting groups  

**Acceptance Criteria:**

- I can browse all public bands
- I can filter by genre, location, looking for members status
- I can see basic band info without being a member
- I can contact bands if they allow it

### Story 12: Band Member Directory

**As a** band member  
**I want to** see who else is in my bands  
**So that** I can connect with bandmates and see the full roster  

**Acceptance Criteria:**

- I can see all active members of bands I'm in
- I can see member roles and positions
- I can see member contact info based on their privacy settings
- I can see pending invitations (if I'm an admin)

**Feature Enhancement - Band Member Contact Visibility:**

- Members can set contact visibility to "Band Members Only"
- When this setting is enabled, only people I share bands with can see my contact info
- This provides a middle ground between "Public" and "Private" contact settings
- Useful for musicians who want to network within their bands but maintain privacy otherwise

## Integration Stories

### Story 5: Band + User Invitation Integration (New User to Band)

**As a** band admin  
**I want to** invite someone who isn't a CMC member yet to join my band  
**So that** I can recruit talented musicians from outside the community  

**Acceptance Criteria:**

- I can invite by email address from the band management interface
- System creates a user invitation linked to the specific band
- The band profile is created but not active until the invitation is accepted
- An invitation email is sent with CMC registration link and band context
- When they register, they see the pending band invitation(s)
- They can accept/decline after completing their CMC profile
- When invitation is accepted, the band becomes active and they become the owner
- The invitation expires after a reasonable time (30 days?)
- Integration between BandService and UserInvitationService

### Story 6: New User Band Invitation Acceptance Integration

**As a** musician invited to join a CMC band  
**I want to** create my CMC account and join the band  
**So that** I can participate in the band and CMC community  

**Acceptance Criteria:**

- I receive an invitation email with clear next steps and band details
- I can register for CMC using the invitation link
- After registration, I see the pending band invitation(s) in my dashboard
- I can review band details before accepting (name, genre, members, etc.)
- When I accept, the band becomes active and I become the band owner
- I automatically become an active member with any specified roles assigned
- I can decline and still remain a CMC member
- Band admins are notified of my decision
- Integration between user registration flow and band invitation system

### Story 13: Band + Production Integration

**As a** band member  
**I want to** coordinate my band's participation in CMC productions  
**So that** we can properly prepare for shows and meet production requirements  

**Acceptance Criteria:**

- Band can be added to production lineups
- Band members can see production details (date, venue, set length requirements)
- Band can specify/update their set length for the production
- Band performance order is visible to band members
- Band members are notified of production updates that affect them
- (Future) Band can specify equipment needs for the production
- Band profile shows upcoming CMC productions they're participating in

### Story 14: Band + Community Calendar Integration (Future Feature)

**As a** band member  
**I want to** see my band's non-CMC gigs and events  
**So that** I can stay informed about all band activities outside of CMC productions  

**Acceptance Criteria:**

- Band can post their external gigs to a community calendar
- Band members can see all band events (both CMC productions and external gigs)
- Other CMC members can discover when bands are playing around town
- *Note: This depends on the Community Calendar feature being implemented*

### Story 15: Band + Practice Space Integration

**As a** band member  
**I want to** book practice space for my band  
**So that** we can rehearse together  

**Acceptance Criteria:**

- When booking practice space, I can specify which band it's for
- Band members can see band-related reservations
- Band admins can manage practice space bookings
