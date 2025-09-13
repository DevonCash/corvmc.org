# Member Profile User Stories

## Core Profile Management

### Story 1: Create Member Profile

**As a** new CMC member  
**I want to** create my member profile  
**So that** I can connect with other musicians and showcase my skills  

**Acceptance Criteria:**

- I can add a bio describing my musical background and interests
- I can upload a profile photo/avatar
- I can add my skills as tags (instruments, vocals, production, etc.)
- I can add my preferred genres as tags
- I can add my musical influences as tags
- I can set my profile visibility (public, members-only, bandmates-only, private)
- Profile is automatically created when I join CMC but I can enhance it anytime
- Changes are saved immediately and visible according to my privacy settings

### Story 2: Update Profile Information

**As a** CMC member  
**I want to** update my profile information  
**So that** it stays current with my evolving musical journey  

**Acceptance Criteria:**

- I can edit my bio and personal information anytime
- I can update my profile photo/avatar
- I can add, remove, or modify my skill tags
- I can update my genre preferences and influences
- I can change my profile visibility settings
- Updates are immediately reflected throughout the system
- Other members see my updated information according to my privacy settings

### Story 3: Profile Privacy Controls

**As a** CMC member  
**I want to** control who can see my profile information  
**So that** I can maintain appropriate privacy while still connecting with the community  

**Acceptance Criteria:**

- I can set my profile to public (visible to anyone)
- I can set my profile to members-only (visible to logged-in CMC members)
- I can set my profile to bandmates-only (visible only to me, admins, and anyone I'm in a band with)
- I can set my profile to private (visible only to me and admins)
- I can control visibility of specific information (bio, contact info, skills)
- Privacy settings are enforced throughout the system
- I can see a preview of how my profile appears to others

## Member Directory & Discovery

### Story 4: Browse Member Directory

**As a** CMC member  
**I want to** browse other member profiles  
**So that** I can discover musicians to collaborate with  

**Acceptance Criteria:**

- I can see a directory of all members (respecting their privacy settings)
- I can search members by name or bio content
- I can filter members by skills, genres, or influences
- I can see member photos and basic information at a glance
- I can click through to view full profiles (if permitted)
- Directory shows only information members have chosen to share
- Results are paginated and load quickly

### Story 5: Advanced Member Search

**As a** CMC member looking for collaborators  
**I want to** search for members with specific skills or interests  
**So that** I can find the right people for my musical projects  

**Acceptance Criteria:**

- I can search by multiple skills simultaneously (e.g., "guitar" AND "vocals")
- I can search by multiple genres to find stylistic matches
- I can combine skill and genre searches for precise results
- I can see how many members match my search criteria
- I can save search queries for future use
- Search respects member privacy settings
- I can contact members directly from search results (if they allow it)

### Story 6: Member Profile Flags

**As a** CMC member  
**I want to** set flags about my availability and interests  
**So that** other members know how I'd like to engage with the community  

**Acceptance Criteria:**

- I can flag myself as "seeking band" to show I'm looking to join groups
- I can flag myself as "available for session work" for one-off gigs
- I can flag myself as "open to collaboration" for project work
- I can set multiple flags simultaneously
- Other members can filter the directory by these flags
- I can change my flags anytime as my situation changes
- Flags are clearly visible on my profile and in directory listings

## Social Features & Networking

### Story 7: Member Collaboration Discovery

**As a** CMC member  
**I want to** get suggestions for potential collaborators  
**So that** I can discover new musical connections  

**Acceptance Criteria:**

- System suggests members with complementary skills to mine
- System suggests members with shared genre interests
- Suggestions consider members seeking collaboration or bands
- I can see why someone was suggested (shared genres, complementary skills)
- I can dismiss suggestions that don't interest me
- Suggestions respect member privacy settings
- Algorithm learns from my interactions and preferences

### Story 8: Profile Activity & Engagement

**As a** CMC member  
**I want to** see relevant activity from other members  
**So that** I can stay connected with the community  

**Acceptance Criteria:**

- I can see when members I'm connected to update their profiles
- I can see when new members join with similar interests
- I can see when members add skills or genres relevant to me
- Activity feed respects privacy settings
- I can control what activity notifications I receive
- I can discover trending skills or genres in the community

## Administrative Features

### Story 9: Member Directory Statistics

**As an** admin  
**I want to** see member directory statistics  
**So that** I can understand community composition and engagement  

**Acceptance Criteria:**

- I can see total member count and profile completion rates
- I can see most popular skills and genres in the community
- I can see how members are using privacy settings
- I can see which members are actively seeking collaboration
- I can identify skill gaps or oversaturated areas
- Statistics help guide community development efforts
- Data is anonymized to protect member privacy

### Story 10: Directory Flag Management

**As a** site admin  
**I want to** manage the available directory flags and their descriptions  
**So that** we can add new search criteria and adapt to community needs  

**Acceptance Criteria:**

- I can create new directory flags (e.g., "seeking recording opportunities", "available for teaching")
- I can edit existing flag names and descriptions
- I can disable flags that are no longer relevant
- I can reorder flags to prioritize important ones
- Flag changes are immediately available to members
- I can see usage statistics for each flag (how many members use it)
- Historical flag data is preserved when flags are disabled
- Members are notified when new relevant flags become available

### Story 11: Profile Moderation

**As an** admin or moderator  
**I want to** moderate member profiles for inappropriate content  
**So that** the community remains welcoming and professional  

**Acceptance Criteria:**

- I can review profiles flagged for inappropriate content
- I can edit or remove inappropriate profile information
- I can contact members about profile policy violations
- I can temporarily hide profiles during moderation review
- Moderation actions are logged for accountability
- Members are notified of moderation actions and can appeal
- Community guidelines are clearly communicated to members

## Integration Stories

### Story 12: Profile + Band Integration

**As a** CMC member  
**I want** my band memberships reflected in my profile  
**So that** others can see my group affiliations and musical projects  

**Acceptance Criteria:**

- My profile shows bands I'm a member of
- Profile shows my role in each band (guitarist, vocalist, etc.)
- Band information links to full band profiles
- I can control visibility of my band memberships
- Band membership changes automatically update my profile
- Other members can discover me through my band affiliations

### Story 13: Profile + Production Integration

**As a** CMC member  
**I want** my production history visible in my profile  
**So that** others can see my performance experience  

**Acceptance Criteria:**

- My profile shows CMC productions I've performed in (as solo artist or band member)
- Profile shows upcoming productions I'm scheduled for
- Production history helps establish my experience and credibility
- I can control visibility of my production history
- Links connect to full production details
- Solo performances and band performances are clearly distinguished

### Story 14: Profile + Practice Space Integration

**As a** CMC member  
**I want** my practice space usage reflected in my engagement metrics  
**So that** my community involvement is visible  

**Acceptance Criteria:**

- My profile can show my practice space usage (if I choose)
- Active users are highlighted in member discovery
- Practice space activity contributes to community engagement scoring
- Privacy controls let me hide this information if desired
- Admins can see engagement patterns to improve services

### Story 15: Profile + Contact Integration

**As a** CMC member  
**I want** flexible contact sharing options  
**So that** I can network appropriately while maintaining privacy  

**Acceptance Criteria:**

- I can set contact info visibility to public, members-only, or private
- I can set contact visibility to "band members only" (people I share bands with)
- I can share different contact methods with different groups
- Contact preferences are enforced throughout the system
- I can allow contact through CMC messaging system only
- Contact sharing integrates with all member discovery features
