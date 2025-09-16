# Equipment Loan Flow

## Current Implementation States

The equipment loan system uses a state machine with the following workflow:

### 1. **Requested** *(requires staff action)*

- Member requests the loan of a piece of equipment
- Equipment is marked as requested but not yet prepared
- Member can cancel at this stage
- From this point on, equipment cannot be reserved by others
- Transitions to: StaffPreparing or Cancelled

### 2. **StaffPreparing** *(requires staff action)*

- Staff member prepares equipment for loan
- Checks current condition, documents condition_out, takes photos
- Sets rental fees and security deposit amounts
- Member can still cancel at this stage
- Transitions to: ReadyForPickup or Cancelled

### 3. **ReadyForPickup** *(requires member action)*

- Equipment is prepared and ready for member pickup
- Member must complete handoff process
- Payment processing occurs (rental fee + security deposit)
- Member can verify condition markers and flag any issues
- Member can still cancel before pickup
- Sets checked_out_at timestamp when transitioning
- Transitions to: CheckedOut or Cancelled

### 4. **CheckedOut** *(requires member action)*

- Equipment is actively in member's possession
- Due date tracking begins
- Member is responsible for equipment care
- Can transition to overdue automatically if past due date
- Transitions to: Overdue (automatic) or DropoffScheduled (member action)

### 5. **Overdue** *(requires member action)*

- Equipment is past its due date
- Member needs to schedule return or extend loan
- System may send reminders/notifications
- Transitions to: DropoffScheduled
- Loan cannot be extended if there are others waiting on the equipment

### 6. **DropoffScheduled** *(requires member action)*

- Member has scheduled equipment return
- Member can reschedule if needed
- Member brings equipment to scheduled dropoff
- Transitions to: CheckedOut (reschedule) or StaffProcessingReturn (dropoff)

### 7. **StaffProcessingReturn** *(requires staff action)*

- Staff inspects returned equipment
- Documents condition_in and any damage
- Processes security deposit refund/charges
- If damage found, can report it before final return
- Transitions to: Returned or DamageReported

### 8. **DamageReported** *(requires staff action)*

- To be removed, not a state in and of itself. If the equipment isn't fit to be used, set the equipment status to maintenance.

### 9. **Returned** *(final state)*

- Loan completed successfully
- Equipment marked as available for future loans
- Financial transactions completed (refunds/charges processed)
- No further transitions
- Equipment is available for others to check out

### 10. **Cancelled** *(final state)*

- Loan cancelled before completion
- Can occur during Requested, StaffPreparing, or ReadyForPickup states
- Equipment returned to available status
- No financial obligations
- No further transitions
- Equipment is available for others to check out

## Key Implementation Details

- **Payment Processing**: Rental fees and security deposits are collected at pickup (ReadyForPickup → CheckedOut)
- **Condition Tracking**: condition_out set during preparation, condition_in set during return
- **Date Tracking**: checked_out_at and due_at timestamps manage loan periods
- **Automatic Overdue**: System automatically transitions CheckedOut → Overdue when past due
- **Damage Integration**: Links to EquipmentDamageReport system for repair tracking
- **Flexible Scheduling**: Members can reschedule dropoffs as needed
- **Early Cancellation**: Members can cancel before equipment is checked out

## Database Fields

- `equipment_id`: Equipment being loaned
- `borrower_id`: Member borrowing the equipment  
- `checked_out_at`: When equipment left CMC (nullable until pickup)
- `due_at`: When equipment should be returned
- `returned_at`: When equipment was actually returned (nullable)
- `condition_out`: Equipment condition at checkout
- `condition_in`: Equipment condition at return (nullable)
- `security_deposit`: Deposit amount (decimal, default 0.00)
- `rental_fee`: Rental fee amount (decimal, default 0.00)
- `notes`: General loan notes
- `damage_notes`: Damage discovered during return
- `state`: Current workflow state (Spatie Model States)
