# Action Audit: Business Decision Review

This document evaluates each user-facing action in the application from a business perspective.

## Legend

- **Action**: The verb/operation users can perform
- **Current Access**: Who can currently perform this action
- **Business Rationale**: Why this access level makes sense (or questions about it)
- **Risk Level**: What happens if this action is misused
- **Decision Status**: ✅ Confirmed | ❓ Needs Review | ⚠️ Potential Issue

---

## 1. Practice Space Reservations

### 1.1 Create Reservation
| Aspect | Details |
|--------|---------|
| **Current Access** | Any authenticated member |
| **Business Rationale** | Core service offering - all members should be able to book practice space |
| **Risk Level** | Low - reservations require payment or credits |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 1.2 View Own Reservations
| Aspect | Details |
|--------|---------|
| **Current Access** | Reservation owner only |
| **Business Rationale** | Privacy - users should see their own bookings |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 1.3 View All Reservations
| Aspect | Details |
|--------|---------|
| **Current Access** | Users with `manage reservations` permission |
| **Business Rationale** | Staff need visibility into all bookings for scheduling and support |
| **Risk Level** | Low - read-only access |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 1.4 Confirm Reservation
| Aspect | Details |
|--------|---------|
| **Current Access** | Reservation owner OR `manage reservations` permission |
| **Business Rationale** | Owners confirm their own bookings; staff can confirm on behalf of members (phone bookings, etc.) |
| **Risk Level** | Medium - confirmation may deduct credits |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should staff confirmation trigger different behavior than self-confirmation? |

### 1.5 Pay Online (Stripe Checkout)
| Aspect | Details |
|--------|---------|
| **Current Access** | Reservation owner only |
| **Business Rationale** | Only the person responsible for payment should initiate checkout |
| **Risk Level** | Low - payment goes to correct reservation |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 1.6 Cancel Reservation
| Aspect | Details |
|--------|---------|
| **Current Access** | Reservation owner (if active) OR `manage reservations` permission |
| **Business Rationale** | Owners manage their own bookings; staff can cancel for policy violations, no-shows, etc. |
| **Risk Level** | Medium - may affect member's schedule and credits |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Is there a cancellation policy (deadline, refund rules)? Should cancellation reasons be tracked differently for staff vs member? |

### 1.7 Mark as Paid (Manual)
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage reservations` permission only |
| **Business Rationale** | Staff record cash/check payments received in person |
| **Risk Level** | High - could mark unpaid reservations as paid fraudulently |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should this require a second approval? Is there an audit trail? |

### 1.8 Mark as Comped
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage reservations` permission only |
| **Business Rationale** | Staff can waive fees for special circumstances (events, apologies, partnerships) |
| **Risk Level** | High - revenue loss if misused |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should comps require a reason? Should there be comp limits or approval workflow? |

### 1.9 Create Recurring Reservation
| Aspect | Details |
|--------|---------|
| **Current Access** | Sustaining members only |
| **Business Rationale** | Recurring bookings are a membership perk; ensures committed members get priority scheduling |
| **Risk Level** | Medium - could lock up popular time slots |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should there be limits on recurring reservations per member? Maximum duration? |

### 1.10 Cancel Recurring Series
| Aspect | Details |
|--------|---------|
| **Current Access** | Series owner OR `manage reservations` permission |
| **Business Rationale** | Owners manage their series; staff can cancel for policy reasons |
| **Risk Level** | Medium - cancels multiple future reservations at once |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

---

## 2. Band Management

### 2.1 Create Band
| Aspect | Details |
|--------|---------|
| **Current Access** | Any authenticated member |
| **Business Rationale** | Encourages community participation; bands are core to the music collective mission |
| **Risk Level** | Low - band must have real members to be useful |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should there be limits on bands per user? Verification that band is legitimate? |

### 2.2 View Band Profile
| Aspect | Details |
|--------|---------|
| **Current Access** | Anyone (public bands) |
| **Business Rationale** | Bands want visibility; public directory serves community |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.3 Edit Band
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner OR band admin |
| **Business Rationale** | Band leadership controls their own profile |
| **Risk Level** | Low - affects only that band's profile |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.4 Delete Band
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner only |
| **Business Rationale** | Only the owner should make this permanent decision |
| **Risk Level** | High - permanent data loss, affects all band members |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should admins be able to delete bands (spam, policy violations)? Should this be soft-delete with recovery period? |

### 2.5 Add Band Member (Invite)
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner OR band admin |
| **Business Rationale** | Band leadership controls membership |
| **Risk Level** | Low - invitee must accept |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.6 Remove Band Member
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner OR band admin (cannot remove owner) |
| **Business Rationale** | Band leadership manages roster; owner protection prevents hostile takeover |
| **Risk Level** | Medium - removed member loses access to band features |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.7 Update Member Role
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner OR band admin |
| **Business Rationale** | Leadership can promote/demote members |
| **Risk Level** | Low - internal band governance |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Can band admins demote other admins? Should only owner control admin promotions? |

### 2.8 Accept/Decline Band Invitation
| Aspect | Details |
|--------|---------|
| **Current Access** | Invitee only |
| **Business Rationale** | Consent-based membership |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.9 Cancel Band Invitation
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner OR band admin |
| **Business Rationale** | Leadership can retract invitations before acceptance |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 2.10 Transfer Band Ownership
| Aspect | Details |
|--------|---------|
| **Current Access** | Band owner only |
| **Business Rationale** | Only current owner can transfer control |
| **Risk Level** | High - permanent change of control |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Is this action available in the UI? Should it require confirmation from the new owner? |

---

## 3. Member Profiles

### 3.1 View Member Profile
| Aspect | Details |
|--------|---------|
| **Current Access** | Anyone (public), members (members-only), owner (private) |
| **Business Rationale** | Three-tier visibility gives members control over their exposure |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 3.2 Edit Own Profile
| Aspect | Details |
|--------|---------|
| **Current Access** | Profile owner |
| **Business Rationale** | Users control their own information |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 3.3 Edit Any Profile
| Aspect | Details |
|--------|---------|
| **Current Access** | `update member profiles` permission |
| **Business Rationale** | Staff can correct information, handle requests, moderate content |
| **Risk Level** | Medium - could alter member information without consent |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should profile edits by staff be logged/audited? Should member be notified? |

### 3.4 Set Profile Flags (is_teacher, is_professional)
| Aspect | Details |
|--------|---------|
| **Current Access** | Unclear - likely admin only |
| **Business Rationale** | These flags affect directory filtering; may require verification |
| **Risk Level** | Low - cosmetic/filtering impact |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Can members self-identify as teacher/professional, or is this staff-verified? |

---

## 4. User Administration

### 4.1 View Users
| Aspect | Details |
|--------|---------|
| **Current Access** | `view users` permission |
| **Business Rationale** | Staff need to look up members for support |
| **Risk Level** | Low - read-only |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.2 Edit User
| Aspect | Details |
|--------|---------|
| **Current Access** | Self OR `update users` permission |
| **Business Rationale** | Users edit their own account; staff can assist with account issues |
| **Risk Level** | Medium - could change email, disable account |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.3 Delete User (Soft)
| Aspect | Details |
|--------|---------|
| **Current Access** | `delete users` permission |
| **Business Rationale** | Staff can deactivate accounts for policy violations, at user request |
| **Risk Level** | Medium - user loses access |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.4 Force Delete User (Permanent)
| Aspect | Details |
|--------|---------|
| **Current Access** | `delete users` permission |
| **Business Rationale** | GDPR/privacy compliance, permanent removal requests |
| **Risk Level** | High - permanent data loss, may affect related records |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should force delete require additional confirmation? What happens to their reservations, band memberships, etc.? |

### 4.5 Restore Deleted User
| Aspect | Details |
|--------|---------|
| **Current Access** | `restore users` permission |
| **Business Rationale** | Undo accidental deletions, reactivate accounts |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.6 Invite User
| Aspect | Details |
|--------|---------|
| **Current Access** | `invite users` permission |
| **Business Rationale** | Staff can invite new members to the platform |
| **Risk Level** | Low - invitee must complete registration |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Can regular members invite others, or only staff? Is there a referral system? |

### 4.7 Resend Invitation
| Aspect | Details |
|--------|---------|
| **Current Access** | `invite users` permission |
| **Business Rationale** | Follow up with people who haven't completed registration |
| **Risk Level** | Low - annoyance if spammed |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.8 Cancel Invitation
| Aspect | Details |
|--------|---------|
| **Current Access** | `invite users` permission |
| **Business Rationale** | Retract invitations sent in error or to wrong address |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 4.9 Impersonate User
| Aspect | Details |
|--------|---------|
| **Current Access** | Admin only |
| **Business Rationale** | Debug issues, see what user sees, assist with complex problems |
| **Risk Level** | High - full access to user's account and actions |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Is impersonation logged? Is the user notified? Can impersonator take destructive actions? |

### 4.10 Adjust Credits (Free Hours)
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage credits` permission |
| **Business Rationale** | Staff can grant bonus hours, correct errors, handle special situations |
| **Risk Level** | Medium - gives away free service |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should credit adjustments require a reason? Is there an audit trail? Limits? |

### 4.11 Assign Roles
| Aspect | Details |
|--------|---------|
| **Current Access** | Admin only (during invitation) |
| **Business Rationale** | Only admins can grant elevated permissions |
| **Risk Level** | High - could grant admin access inappropriately |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

---

## 5. Equipment Management

### 5.1 View Equipment
| Aspect | Details |
|--------|---------|
| **Current Access** | Any authenticated member |
| **Business Rationale** | Members can see what equipment is available to borrow |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 5.2 Checkout Equipment to Member
| Aspect | Details |
|--------|---------|
| **Current Access** | Staff (implicit - panel access only) |
| **Business Rationale** | Equipment loans are managed by staff, not self-service |
| **Risk Level** | Medium - equipment could be lost/damaged |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should there be an explicit permission? Should members be able to request checkouts? |

### 5.3 Advance Loan Status
| Aspect | Details |
|--------|---------|
| **Current Access** | Staff (implicit - panel access only) |
| **Business Rationale** | Staff track equipment through loan lifecycle |
| **Risk Level** | Low - workflow management |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should there be an explicit permission for equipment management? |

### 5.4 Process Return
| Aspect | Details |
|--------|---------|
| **Current Access** | Staff (implicit - panel access only) |
| **Business Rationale** | Staff verify condition and check equipment back in |
| **Risk Level** | Low |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should there be condition tracking/damage reporting? |

### 5.5 Report Equipment Damage
| Aspect | Details |
|--------|---------|
| **Current Access** | Unknown - needs investigation |
| **Business Rationale** | Members should be able to report issues; staff should track damage |
| **Risk Level** | Low |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Is this feature implemented? Who can create damage reports? |

---

## 6. Events/Productions

### 6.1 View Events
| Aspect | Details |
|--------|---------|
| **Current Access** | Anyone (public) |
| **Business Rationale** | Events are public community offerings |
| **Risk Level** | N/A |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 6.2 Create Event
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage events` permission |
| **Business Rationale** | Events represent the organization; controlled creation |
| **Risk Level** | Medium - public-facing content |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should band owners be able to propose events? Is there an event submission workflow? |

### 6.3 Edit Event
| Aspect | Details |
|--------|---------|
| **Current Access** | Event organizer OR `manage events` permission |
| **Business Rationale** | Organizers manage their events; staff can assist/intervene |
| **Risk Level** | Medium - changes public information |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 6.4 Delete Event
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage events` permission |
| **Business Rationale** | Removing events is administrative action |
| **Risk Level** | High - removes public content, may affect performers |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should organizers be able to delete their own events? Should this notify performers? |

### 6.5 Publish Event
| Aspect | Details |
|--------|---------|
| **Current Access** | Event organizer OR `manage events` permission |
| **Business Rationale** | Publication makes event public; organizer controls timing |
| **Risk Level** | Medium - public visibility |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 6.6 Cancel Event
| Aspect | Details |
|--------|---------|
| **Current Access** | `manage events` permission (likely) |
| **Business Rationale** | Cancellation is significant; requires authority |
| **Risk Level** | High - disappoints attendees, affects performers |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should organizers be able to cancel? Is notification automatic? |

### 6.7 Add/Remove Performers
| Aspect | Details |
|--------|---------|
| **Current Access** | Event organizer OR `manage events` permission |
| **Business Rationale** | Organizers build their lineup |
| **Risk Level** | Low - affects event content |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should performers need to accept being added? |

---

## 7. Content Moderation

### 7.1 Report Content
| Aspect | Details |
|--------|---------|
| **Current Access** | Any authenticated member |
| **Business Rationale** | Community self-policing; members flag inappropriate content |
| **Risk Level** | Low - reports require staff review |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 7.2 View Reports
| Aspect | Details |
|--------|---------|
| **Current Access** | `view reports` permission |
| **Business Rationale** | Moderators need to see reported content |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 7.3 Uphold Report (Confirm Violation)
| Aspect | Details |
|--------|---------|
| **Current Access** | `view reports` permission |
| **Business Rationale** | Moderators can confirm policy violations |
| **Risk Level** | Medium - may result in content removal or user action |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should upholding trigger automatic content action? Should there be an appeals process? |

### 7.4 Dismiss Report
| Aspect | Details |
|--------|---------|
| **Current Access** | `view reports` permission |
| **Business Rationale** | Moderators can clear false reports |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 7.5 Review Revision
| Aspect | Details |
|--------|---------|
| **Current Access** | `approve revisions` permission |
| **Business Rationale** | Content changes may need approval before going live |
| **Risk Level** | Medium - gatekeeping public content |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Which content requires revision review? Is there auto-approval for trusted users? |

### 7.6 Approve Revision
| Aspect | Details |
|--------|---------|
| **Current Access** | `approve revisions` permission |
| **Business Rationale** | Moderators publish approved changes |
| **Risk Level** | Low |
| **Decision Status** | ✅ Confirmed |
| **Notes** | |

### 7.7 Reject Revision
| Aspect | Details |
|--------|---------|
| **Current Access** | `reject revisions` permission |
| **Business Rationale** | Moderators block inappropriate changes |
| **Risk Level** | Medium - user's changes are discarded |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Is the user notified with a reason? Can they appeal? |

---

## 8. Administrative Actions

### 8.1 Clean Up Activity Logs
| Aspect | Details |
|--------|---------|
| **Current Access** | Staff (likely admin) |
| **Business Rationale** | Database maintenance, privacy compliance |
| **Risk Level** | Medium - destroys audit trail |
| **Decision Status** | ❓ Needs Review |
| **Notes** | Should this be automated instead of manual? What's the retention policy? |

---

## Summary: Actions Needing Business Decisions

### High Priority (Security/Financial Impact)
1. **Mark as Paid** - Should require audit trail, possibly second approval
2. **Mark as Comped** - Should require reason, possibly limits
3. **Impersonate User** - Should be logged, user notified
4. **Adjust Credits** - Should require reason, have limits
5. **Force Delete User** - Should handle cascading effects clearly

### Medium Priority (User Experience)
1. **Create Band** - Limits? Verification?
2. **Delete Band** - Admin override? Soft delete?
3. **Create Event** - Can bands propose events?
4. **Cancel Event** - Who can cancel? Notifications?
5. **Equipment Checkout** - Explicit permissions? Self-service requests?

### Low Priority (Policy Clarification)
1. **Profile Flags** - Who sets teacher/professional status?
2. **Transfer Band Ownership** - Is this in the UI?
3. **Invite User** - Can members invite, or only staff?
4. **Revision Review** - What triggers review vs auto-approval?
