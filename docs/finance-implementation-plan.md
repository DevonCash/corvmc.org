# Finance / Order Revision — Implementation Plan

Sequenced for a solo developer working through it PR by PR. Each epic groups related work; each task within an epic is roughly one pull request. Tasks within an epic are ordered; epics are ordered by dependency (later epics depend on earlier ones unless noted).

**Deviation from design doc:** The plan keeps the existing `UserCredit` / `CreditTransaction` / `CreditService` system instead of migrating to `bavix/laravel-wallet`. The conceptual shift (credits as discount, not currency) is preserved — credits produce a wallet withdrawal + discount LineItem on the Order, never appear in the `transactions` table — but the underlying balance storage stays as-is.

---

## Epic 1: New models, migrations, and state machines

Foundation. Everything else builds on these tables and state classes.

### 1.1 Install spatie/laravel-model-states

- `composer require spatie/laravel-model-states`
- Verify it works with the existing test suite (no conflicts)

### 1.2 Create Order model and migration

New table `orders` with the schema from the design doc:

```
id, user_id (nullable), status (OrderState), total_amount (int cents),
settled_at (nullable timestamp), notes (nullable text), timestamps
```

Model at `app-modules/finance/src/Models/Order.php`. Wire up spatie state machine for `status`:

- State classes under `app-modules/finance/src/States/OrderState/`: `Pending`, `Completed`, `Comped`, `Refunded`, `Cancelled`
- Allowed transitions: `Pending→Completed`, `Pending→Comped`, `Pending→Cancelled`, `Completed→Refunded`, `Comped→Refunded`
- Transition hooks are empty stubs for now (filled in during Epic 5)

### 1.3 Create LineItem model and migration

New table `order_line_items`:

```
id, order_id (FK), product_type (string), product_id (nullable int),
description (string), unit (string), unit_price (int cents),
quantity (decimal 8,2), amount (int cents), timestamps
```

Model at `app-modules/finance/src/Models/LineItem.php`. Relationship: `belongsTo(Order)`. Inverse on Order: `hasMany(LineItem)`.

### 1.4 Create Transaction model and migration

New table `transactions`:

```
id, order_id (FK), user_id (nullable int), currency (string),
amount (int cents), type (enum: payment/refund/fee),
status (TransactionState), cleared_at (nullable), cancelled_at (nullable),
failed_at (nullable), metadata (JSON nullable), timestamps
```

Model at `app-modules/finance/src/Models/Transaction.php`. State classes under `app-modules/finance/src/States/TransactionState/`: `Pending`, `Cleared`, `Cancelled`, `Failed`.

Allowed transitions: `Pending→Cleared`, `Pending→Cancelled`, `Pending→Failed`. All terminal.

Row-level immutability: override `setAttribute` (or use a `saving` observer) to freeze `amount`, `currency`, `type`, `order_id`, `user_id` after first write.

### 1.5 Create TicketState state machine

Upgrade `Ticket.status` from the flat `TicketStatus` enum (`pending`, `generated`, `purchased`, `refunded`, `checked_in`) to a spatie state machine.

State classes under `app-modules/events/src/States/TicketState/`: `Pending`, `Valid`, `CheckedIn`, `Cancelled`.

Transitions: `Pending→Valid` (on OrderSettled), `Pending→CheckedIn` (sliding-scale policy for early check-in), `Valid→CheckedIn`, `Valid→Cancelled`, `Pending→Cancelled`. `CheckedIn` is terminal for audit — never transitions to Cancelled.

Migration: add `status` column as spatie state; backfill existing rows from the old enum values.

### 1.6 Create stripe_webhook_events table

```
id, event_id (string, unique), event_type (string), processed_at (timestamp), timestamps
```

For webhook idempotency. Lightweight — just the dedup key.

### 1.7 Create OrderSettled event

`app-modules/finance/src/Events/OrderSettled.php` — replaces `PaymentAccepted`. Carries the Order. Fired from `Completed::entering()` and `Comped::entering()` hooks (wired in Epic 5).

### 1.8 Update morph map and add Order/Transaction relationships

- Add `'order' => Order::class` and `'transaction' => Transaction::class` to the morph map in `AppServiceProvider`
- Add `orders()` and `transactions()` relationships on `User`

---

## Epic 2: Product system

Replaces the `Chargeable` interface. Finance-side classes that describe how to price domain models.

### 2.1 Create base Product class and Finance registry

`app-modules/finance/src/Products/Product.php` — abstract base class with:

- Static `$type` (string) and `$model` (class-string, nullable for category products)
- Abstract static methods: `billableUnits`, `pricePerUnit`, `description`, `eligibleWallets`
- Instance wrapper that binds a domain model and proxies to the statics via `__get`

`Finance` facade gains:

- Registry: `register()`, `productFor($model)`, `productByType($type)`, `registeredTypes()`
- Balance (thin wrappers over `CreditService`): `balance(User $user, string $walletType): int`, `allocate(User $user, string $walletType, int $amount, string $reason, ?Model $source = null): void`, `adjust(User $user, string $walletType, int $amount, string $reason): void`
- Pricing and commitment: `price()`, `commit()`, `settle()`, `refund()` (implemented in later epics, stubbed here)

Backed by a `FinanceManager` singleton (or extend the existing service provider pattern) that holds the registry array. The `balance`/`allocate`/`adjust` methods delegate to `CreditService` with standardized metadata (reason, source). Callers outside Finance go through the facade, not `CreditService` directly.

### 2.1b Update config/finance.php

Reshape the config to match the design doc's wallet-oriented structure:

```php
'wallets' => [
    'free_hours' => [
        'cents_per_unit' => 750,
        'label' => 'Free rehearsal hours',
    ],
    'equipment_credits' => [
        'cents_per_unit' => 100,
        'label' => 'Equipment credits',
    ],
],

'processing_fee' => [
    'rate_bps' => 290,
    'fixed_cents' => 30,
],
```

The old `pricing` and `credits` sections are retained temporarily (used by old code paths until Epic 15). New code reads from `wallets` and `processing_fee`.

### 2.2 Create concrete model-backed Products

Integration layer classes in `app/Finance/Products/` (these live in `app/` because they reference domain models from SpaceManagement, Events, and Equipment):

- `RehearsalProduct` — wraps `RehearsalReservation`. Pulls rate from config. `eligibleWallets` returns `['free_hours']`.
- `TicketProduct` — wraps `TicketOrder`. Pricing from the event's ticket price.
- `EquipmentLoanProduct` — wraps `EquipmentLoan`. `eligibleWallets` returns `['equipment_credits']`.

Each replaces the `Chargeable` interface methods that currently live on the domain model.

### 2.3 Create category Products

Same directory:

- `ProcessingFeeProduct` — no domain model. `pricePerUnit` computed from config `rate_bps` + `fixed_cents`.
- `SustainingMemberDiscountProduct` — no domain model.
- `CompDiscountProduct` — no domain model.
- `ManualAdjustmentProduct` — no domain model.

### 2.4 Register Products in AppServiceProvider

Add the `Finance::register([...])` call in `AppServiceProvider::boot()`. This replaces the config-driven `finance.pricing` lookup.

### 2.5 Add LineItem product_type validation

`saving` observer on `LineItem` that rejects any row whose `product_type` is not in `Finance::registeredTypes()`.

---

## Epic 3: Purchasable lock

Short and independent — can land right after the Product system since it depends on Product registration.

### 3.1 Implement PurchasableLockedException and saving observer

- `app-modules/finance/src/Exceptions/PurchasableLockedException.php`
- A `saving` observer attached during Product registration: if the model has an attached non-Cancelled Order, throw. The observer is registered by `Finance::register()` for every model-backed Product.
- This replaces `HandleChargeableUpdated` / `adjustCredits` diff logic — bound models can't change.

---

## Epic 4: Rewrite PricingService

### 4.1 Rewrite PricingService to return LineItems

`PricingService::calculatePriceForUser()` is replaced by `Finance::price(array $items, ?User $user)`.

New behavior:
- Accepts an array of domain models (resolved via `productFor()`) and/or category tuples
- For each item, creates an unpersisted base `LineItem` using the Product's `billableUnits`, `pricePerUnit`, `description`
- When `$user` is provided, walks each base LineItem's Product `eligibleWallets`, checks `$user->getCreditBalance()` for each, emits discount LineItems (negative amount) up to the available balance
- Returns `Collection<LineItem>`
- Pure — no DB writes, no credit deductions

`PriceCalculationData` DTO is retired. Callers read LineItems directly.

### 4.2 Add processing fee to price()

When the caller includes a processing fee flag (or a `processing_fee` category tuple), `PricingService` emits a `ProcessingFeeProduct` LineItem computed from config. This replaces `FeeService::calculateProcessingFee()`.

---

## Epic 5: Commit flow

The core write path. This is the biggest epic.

### 5.1 Extend PaymentData DTO

Add commit-intent fields to `PaymentData`:
- `walletsToDraw` — which credit types to apply (and max amounts)
- `rails` — payment rails (`stripe`, `cash`, or both) with amounts
- `coversFees` — boolean, drives `processing_fee` LineItem creation

The existing Stripe-specific fields (`transactionId`, `paymentIntentId`) stay but are now written to `Transaction.metadata` instead of `Charge` columns.

### 5.2 Implement Finance::commit()

`Finance::commit(Order $order, PaymentData $payment): CommitmentResult`

Inside one `DB::transaction`:
1. `lockForUpdate` on the Order
2. Call `Finance::price()` to get the full LineItem set (including discounts based on `walletsToDraw`)
3. For each discount LineItem: call `CreditService::deductCredit()` to move balance; persist the discount LineItem on the Order
4. Persist all base LineItems on the Order
5. Update `Order.total_amount` as the sum of all LineItems
6. If `coversFees` is true, emit a `ProcessingFeeProduct` LineItem (computed from `config('finance.processing_fee')`)
7. For each payment rail: insert a `Pending` Transaction
8. For Stripe: create Checkout Session via Cashier, store `session_id` in `Transaction.metadata`, set `session.metadata.transaction_id` for webhook correlation
9. If the Order has a reservation, transition it `Scheduled → Confirmed`
10. If zero Transactions (fully discounted), transition Order to `Completed`

Return a `CommitmentResult` with the Order, Transactions, and redirect URL (if Stripe).

### 5.3 Create CommitOrderAction

Integration-layer action at `app/Actions/CommitOrderAction.php`. This is the user-facing entry point that:
- Builds the `PaymentData` from the request
- Calls `Finance::commit()`
- Handles the response (redirect to Stripe or confirmation screen)

### 5.4 Wire Order creation to domain events

Replace the `HandleChargeableCreated` listener. When `ReservationCreated` fires, the new listener creates a `Pending` Order with base LineItems (via `Finance::price()`) but does NOT deduct credits or create Transactions. That waits for `commit()`.

Same for equipment-loan creation events.

For ticket orders: create the Order with LineItems AND eagerly create one `Pending` Ticket row per attendee LineItem. This replaces the current lazy creation in `TicketService::activateTickets()`. Tickets transition `Pending → Valid` on `OrderSettled`.

### 5.5 Fill in OrderState transition hooks

Now that commit and settlement logic exist:

- `Pending → Completed` entering: assert all payment Transactions Cleared and sum to `total_amount`
- `Pending → Completed` entered: fire `OrderSettled`, dispatch receipt
- `Pending → Comped` entering: cascade Pending Transactions to Cancelled
- `Pending → Comped` entered: fire `OrderSettled`
- `Pending → Cancelled` entering: cascade Pending Transactions to Cancelled, reverse credit deductions (deposit back via `CreditService::addCredit`), cancel attached Tickets

### 5.6 Implement the reservation confirmation gate

The `Scheduled → Confirmed` transition is now driven by `commit()` inside the DB transaction. Remove the old `ConfirmReservationOnPayment` listener and the `PaymentAccepted → Confirmed` path. Confirmation happens at commit, not at Stripe webhook.

---

## Epic 6: Settlement flow

### 6.1 Implement Finance::settle()

`Finance::settle(Transaction $transaction): void`

Inside `DB::transaction` with `lockForUpdate` on the Transaction:
- Transition `Pending → Cleared`, set `cleared_at`
- Fire `TransactionCleared` event

### 6.2 TransactionCleared listener — Order settlement check

Listener on `TransactionCleared`:
- Re-read the Order's Transactions inside a locked transaction
- If no Pending Transactions remain and Cleared amounts cover `total_amount`, transition Order to `Completed`

### 6.3 Update Stripe webhook handler

- Check `stripe_webhook_events` for idempotency (insert-or-skip)
- Resolve Transaction via `session.metadata.transaction_id`
- Call `Finance::settle($transaction)`
- Store `payment_intent_id` in `Transaction.metadata`

### 6.4 Staff "Mark paid (cash)" action

Filament action that calls `Finance::settle($transaction)` for a cash-rail Transaction. This replaces the current `recordPayment()` path for cash.

---

## Epic 7: Refund flow

### 7.1 Implement Finance::refund()

`Finance::refund(Order $order): RefundResult`

Inside `DB::transaction` with `lockForUpdate` on the Order:
- Transition `Completed/Comped → Refunded`
- For each discount LineItem: reverse the credit deduction via `CreditService::addCredit()`
- For each Cleared payment Transaction: write a compensating `type: refund, status: Pending` Transaction
  - Stripe: initiate Stripe refund API call
  - Cash: create staff cash-out task
- Cancel attached Tickets (preserve CheckedIn as audit)

### 7.2 Refund webhook handler

Handle `charge.refunded` Stripe webhook → settle the refund Transaction (`Pending → Cleared`).

---

## Epic 8: Comp flow

### 8.1 Implement Order comp

`Finance::comp(Order $order): void` (or fold into a state transition action).

- Transition `Pending → Comped`
- The `entering()` hook (from 5.5) cascades Pending Transactions to Cancelled
- `entered()` fires `OrderSettled`

Filament action for staff to comp an Order.

---

## Epic 9: Cancel flow

### 9.1 Implement Order cancellation

`Finance::cancel(Order $order): void`

- Transition `Pending → Cancelled`
- The `entering()` hook (from 5.5) handles cascade: cancel Pending Transactions, reverse credit deductions, cancel Tickets

Wire up to `ReservationCancelled` event — replace `HandleChargeableCancelled` listener.

---

## Epic 10: Filament Admin ✅

### 10.1 Create OrderResource ✅

Staff panel resource for Orders. List page with status icon, user, product type, payment rail, amount columns. Unsettled filter tab. Action group with View, Mark Paid, Collect Cash, Comp, Cancel, Refund.

View page: subheading with status/total/user, details section (user link, timestamps, notes), inline Line Items and Transactions relation manager tables (not tabbed). Line items link to backing domain models via `ViewModelAction`. Transactions table is read-only with amount-due header badge.

All order actions extracted to reusable classes under `Actions/`: `MarkPaidAction`, `CollectCashAction`, `CompOrderAction`, `CancelOrderAction`, `RefundOrderAction`.

### 10.2 Create relation managers ✅

- `LineItemsRelationManager` on OrderResource — read-only table with total summary, `ViewModelAction` linking to backing models
- `TransactionsRelationManager` on OrderResource — read-only ledger view, amount-due badge in header
- `OrdersRelationManager` on UserResource — mirrors OrderResource table layout

TransactionResource was built then removed — staff interact with transactions through the order detail view.

### 10.3 Rewrite StaffDashboard queries ✅

Rewrite `getMonthlyRevenueData()` and `getTodaysOperationsData()` to source from `transactions` + `order_line_items`. `Schema::hasTable()` guards for pre-migration compatibility.

### 10.4 Sign convention flip ✅

Transaction amounts flipped from customer perspective (payments negative) to organization perspective (payments positive, refunds negative). Updated FinanceManager, Order model, all tests, dashboard, and UI.

### 10.5 Member order history page

Read-only list of the authenticated user's orders on the member panel. Status, product type, amount, date. Links to order detail.

---

## Epic 11: Stripe integration & reservation flow

### 11.1 Move Stripe identifier storage to Transaction.metadata ✅

Already done during Epic 6 — `commit()` stores `session_id` and `checkout_url`, `settle()` stores `payment_intent_id`.

### 11.2 Wire CreateReservation to Finance::commit()

Replace `PaymentService::createCheckoutSession()` in `CreateReservation` with the new Order flow:

- Create Order + price via `Finance::price()`
- Commit via `Finance::commit()` with Stripe rail
- Redirect to `$order->checkoutUrl()`
- Handle fully-discounted orders (zero total → direct completion)

### 11.3 Add checkout.session.expired webhook

Listen for `checkout.session.expired` in `StripeWebhookController`. Find the pending Stripe Transaction by session ID in metadata, transition to Failed state.

### 11.4 Retry Payment action

Member-side action on pending Orders with a failed/expired Stripe Transaction. Creates a fresh Checkout Session for the existing Transaction amount and redirects.

### 11.5 Remove Stripe columns from Charge

Deferred to Epic 15 with the rest of the old code cleanup.

---

## Epic 12: Subscription relocation

Independent of most other epics — can be done whenever.

### 12.1 Move Subscription model and service to app/

- `Finance\Models\Subscription` → `app/Models/Subscription.php`
- `Finance\Services\SubscriptionService` → `app/Services/SubscriptionService.php`
- `Finance\Actions\Subscriptions\*` → `app/Actions/Subscriptions/*`
- Update `Cashier::useSubscriptionModel()` reference in AppServiceProvider
- Update morph map, imports, and any Filament resources that reference these classes
- No behavioral changes

### 12.2 Wire MemberBenefitService allocations through Finance::allocate()

When a subscription invoice pays and `MemberBenefitService` allocates free hours, it now calls `Finance::allocate()` which wraps `CreditService::addCredit()` with standardized metadata. This is a thin rename/wrapper, not a rewrite.

---

## Epic 13: Kiosk module deletion

Independent — can land anytime.

### 13.1 Delete kiosk module

- Delete `app-modules/kiosk/` directory
- Remove kiosk service provider registration
- Drop `kiosk_devices` and `kiosk_payment_requests` tables (migration)
- Drop `ticket_orders.is_door_sale` column (migration)
- Remove `CreateDoorSale` action from events module

---

## Epic 14: Data migration and backfill

Do this after all the new code paths work and are tested against new data.

### 14.1 Backfill rehearsal charges → Orders

Migration script (not a Laravel migration — a command):

- One Order per Charge
- One base LineItem (`product_type='rehearsal_time'`)
- Discount LineItems from `Charge.credits_applied`
- Payment Transaction from `Charge.status` + `payment_method`:
  - `Paid` → `Cleared` Transaction
  - `CoveredByCredits` → discount LineItems only, no Transaction
  - `Cancelled` → Order `Cancelled`
  - `Pending` → grandfathered (see 14.3)

### 14.2 Backfill ticket charges → Orders ✅

Backfill command created. One Order per TicketOrder with ticket/discount/fee LineItems and payment Transaction.

### 14.3 Grandfather pending charges at cutover

For each `Pending` Charge:
- Credit deductions already happened in the old system, so discount LineItems reflect that
- Payment Transaction written as `Pending`
- Post-cutover, normal webhook/staff flow settles it
- `Order.notes` tagged as `grandfathered`

### 14.4 Handle confirmed-but-unpaid reservations

Small cohort. Cutover timing minimizes these. Outliers are grandfathered as Confirmed — the new confirmation gate applies only to reservations created after cutover.

---

## Epic 15: Old code removal

Last. Only after the backfill is verified and the new paths are in production.

### 15.1 Remove Chargeable interface and HasCharges trait

- Remove `implements Chargeable` from `RehearsalReservation` and `TicketOrder`
- Remove `use HasCharges` from those models
- Delete `app-modules/finance/src/Contracts/Chargeable.php`
- Delete `app-modules/finance/src/Concerns/HasCharges.php`

### 15.2 Remove old listeners

- Delete `HandleChargeableCreated`, `HandleChargeableUpdated`, `HandleChargeableCancelled`, `HandleChargeableConfirmed`
- Delete `ConfirmReservationOnPayment`
- Remove their registrations from `AppServiceProvider`

### 15.3 Remove old services and facades

- Delete `FeeService` (replaced by `ProcessingFeeProduct`)
- Delete `FeeService` facade
- Remove `FeeService` singleton from `FinanceServiceProvider`
- `PricingService` is already rewritten (Epic 4); delete the old `PriceCalculationData` DTO

### 15.4 Remove old models

- Delete `Charge` model
- Delete `CreditAllocation` model (allocation logic moved to `MemberBenefitService`)
- Remove `'charge'` from the morph map
- Delete `ChargeResource` Filament resource (replaced by `OrderResource`)

### 15.5 Remove deferCredits parameter

Grep for `deferCredits` across the codebase. Remove:
- The parameter from `ReservationCreated` event constructor
- Any branches that check it in listeners or services
- The `deferCredits` flag on recurring reservation creation

Credits are never touched at reservation creation under the new model, so the flag, the branch, and any callers passing it all go away.

### 15.6 Remove RecalculateUnsettledReservations command

`app/Console/Commands/RecalculateUnsettledReservations.php` — price is locked at commit, no recalculation needed.

### 15.7 Remove old enums and DTOs

- `ChargeStatus` — replaced by `OrderState`
- `PaymentStatus` — replaced by `TransactionState`
- `CompData` — folded into Order comp flow
- `PriceCalculationData` — replaced by `Collection<LineItem>`

### 15.8 Drop old columns

Migration:
- `reservations.free_hours_used`
- `ticket_orders.payment_method`
- `Charge.credits_applied`, `Charge.stripe_session_id`, `Charge.stripe_payment_intent_id`

### 15.9 Archive old tables

Rename to `archived_charges`, `archived_credit_allocations`. No application code references them. Retained for audit history.

Note: `user_credits` and `credit_transactions` stay active — they're still the balance/ledger system.

---

## Epic 16: No-show sweep and reconciliation

### 16.1 No-show sweep job

Scheduled command that cancels stale Pending Transactions past their SLA. Transitions each to `Cancelled` with `metadata.cancellation.reason = 'no_show_sweep'`. If all Transactions on an Order are now Cancelled, transitions the Order to `Cancelled` (which triggers credit reversal via the state hook).

This is the only background job that touches Transaction/Order state, and it only cancels — never clears.

### 16.2 Nightly reconciliation job

Scheduled command that:
- Reasserts credit balances against `CreditTransaction` ledger (flags drift)
- Flags any remaining Pending Transactions past SLA that the sweep missed
- Logs anomalies for human review
- No auto-correction — drift is human-reviewed

---

## Out of scope for this plan (SpaceManagement-side)

These changes are described in the design doc but live in the SpaceManagement module. They should be planned separately once the Finance core is stable:

- **Recurring reservation lazy materialization** — move instance materialization from eager to a rolling window. Fewer open Pending Orders per member.
- **No auto-confirm for recurring instances** — every instance requires explicit user commitment through the checkout modal.

Both are prerequisites for recurring reservations to work cleanly with the new commit flow, but they don't block any Finance epic.

---

## Dependency graph (epics)

```
Epic 1 (models/migrations)
  ├─▶ Epic 2 (products)
  │     ├─▶ Epic 3 (purchasable lock)
  │     └─▶ Epic 4 (pricing rewrite)
  │           └─▶ Epic 5 (commit flow)
  │                 ├─▶ Epic 6 (settlement)
  │                 ├─▶ Epic 7 (refund)
  │                 ├─▶ Epic 8 (comp)
  │                 └─▶ Epic 9 (cancel)
  │                       └─▶ Epic 10 (filament admin)
  │                             └─▶ Epic 11 (stripe cleanup)
  │
  Epic 12 (subscription relocation) ── independent
  Epic 13 (kiosk deletion) ── independent
  │
  Epics 5–11 all done
  └─▶ Epic 14 (data migration)
        └─▶ Epic 15 (old code removal)
              └─▶ Epic 16 (reconciliation)
```

Epics 12 and 13 can be done in parallel with anything — they have no dependencies on the new models.

---

## Epic 17: Ticket purchase → Order integration

**Pre-deployment blocker.** Wire the ticket purchase flow to create Orders instead of Charges before cutover — no parallel systems.

### 17.1 Wire TicketService::createOrder to Finance::commit()

Replace `Charge::create()` in `TicketService::createOrder()` with Order creation via `Finance::price()` + `Finance::commit()`. TicketProduct already exists.

### 17.2 Wire TicketService::processCheckout to Order checkout

Replace Stripe Checkout Session creation with the Order's checkout URL from `Finance::commit()` with the Stripe rail.

### 17.3 Update ticket webhook handling

`handleTicketOrderCheckout` in `StripeWebhookController` already resolves via `TicketService::completeOrder()`. Update to call `Finance::settle()` on the Order's Transaction instead.

### 17.4 Update ticket Filament views

Add Order-aware actions to the ticket admin (TicketOrderResource) and member ticket pages. Remove Charge-based payment display.

### 17.5 Remove HasCharges from TicketOrder

Once tickets flow through Orders, remove `implements Chargeable` and `use HasCharges` from TicketOrder.
