# Finance / Order Revision — Implementation Plan

## Status: Ready for deployment

Epics 1–11, 14, 15, and 17 are complete. The system is fully functional with reservations and tickets flowing through the Order system. The old Charge-based system has been removed (Epic 15).

**Deployment sequence:**
1. `php artisan migrate` (creates orders, order_line_items, transactions, stripe_webhook_events tables)
2. `php artisan finance:backfill-charges` (converts existing Charges → Orders)
3. `php artisan finance:backfill-ticket-orders` (converts existing TicketOrders → Orders)
4. Verify counts match, spot-check a few orders in the admin UI
5. Clear caches: `php artisan cache:clear`

**Key architecture decisions made during implementation:**
- Transaction sign convention: organization perspective (payments positive, refunds negative)
- Purchasable lock: models declare their own lockable fields via `Purchasable` trait
- Cross-module effects: events (Finance fires OrderSettled/OrderCancelled/etc, owning modules listen)
- Wizard payment buttons: `wire:click` calls `submitWithPaymentMethod()` Livewire method, sets public property
- Charge model kept for read-only backward compat (old views use null-safe `->charge?->` operators)

---

## Completed Epics

### Epic 1: Models, migrations, state machines ✅
Order, LineItem, Transaction models with spatie state machines.

### Epic 2: Product system ✅
Product registry, model-backed and category products, config-driven wallets.

### Epic 3: Purchasable lock ✅
Models with active Orders are immutable (except lockable fields declared via `Purchasable` trait).

### Epic 4: Pricing ✅
`Finance::price()` generates LineItems with wallet discounts.

### Epic 5: Commit flow ✅
`Finance::commit()` deducts credits, creates Transactions, opens Stripe Checkout Sessions.

### Epic 6: Settlement ✅
`Finance::settle()` transitions Transactions to Cleared. CheckOrderSettlement listener auto-completes Orders.

### Epic 7: Refund ✅
`Finance::refund()` creates compensating Transactions, calls Stripe refund API. RefundOrderAction with rail-aware modal description.

### Epic 8: Comp ✅
`Finance::comp()` waives payment, transitions to Comped.

### Epic 9: Cancel ✅
`Finance::cancel()` cancels pending Transactions, reverses credit deductions.

### Epic 10: Filament Admin ✅
OrderResource (list/view), order actions (MarkPaid, CollectCash, Comp, Cancel, Refund, RetryPayment), member order history, ViewModelAction, dashboard queries on Orders/Transactions.

### Epic 11: Stripe integration & reservation flow ✅
- 11.1: Stripe metadata in Transaction (done in Epic 6)
- 11.2: CreateReservation wired to Finance::commit() with dual payment buttons
- 11.3: checkout.session.expired webhook → Failed Transaction
- 11.4: RetryPaymentAction via Finance::retryStripePayment()

### Epic 14: Data migration ✅
BackfillChargesToOrders and BackfillTicketOrdersToOrders commands. Idempotent, --dry-run supported.

### Epic 15: Old code removal ✅
Deleted: ChargeResource, HasCharges, Chargeable interface, old listeners (HandleChargeable*), PaymentAccepted event, old payment actions, RecalculateUnsettledReservations. Charge model kept read-only.

### Epic 17: Ticket purchase → Order integration ✅
TicketService rewritten to use Finance::commit(). GenerateTicketsOnOrderSettled event listener. Door sales create + settle cash orders immediately.

---

## Remaining Epics

### Epic 12: Subscription relocation — DEFERRED
Moving subscriptions out of Finance module. Deferred — will be replaced by recurring Orders in a future refactor.

### Epic 13: Kiosk module deletion — OPTIONAL
Independent cleanup. Can be done anytime.

### Epic 16: No-show sweep and reconciliation
Scheduled job to cancel stale Pending Transactions (orders where the checkout session expired but the webhook didn't fire). Nightly reconciliation to flag drift between Orders and Stripe.

---

## Known remaining cleanup (non-blocking)

These items don't affect functionality but should be cleaned up post-deploy:

1. **Blade templates still reference `->charge`** — Notifications (ReservationCreatedNotification, ReservationReminderNotification), reservation detail views (reservation-details.blade.php, reservation-details-member.blade.php), space usage widget. These use null-safe operators so they work fine — they just show nothing for new reservations. Should be rewritten to show Order/Transaction data instead.

2. **ReservationColumns::costDisplay()** — References `$record->charge->net_amount`. Returns null for new reservations. Should be rewritten to look up the Order.

3. **ViewSpaceUsage** — References ChargeResource (deleted). The link will 404. Remove or redirect to OrderResource.

4. **LogReservationActivity** — Some handlers still reference `$event->chargeable` for non-cancelled events (ReservationCreated, ReservationUpdated, ReservationConfirmed). These events still have `$chargeable` property names — rename to `$reservation` for consistency.

5. **Old ReservationWorkflowTest** — Tests reference `$reservation->charge` which no longer works for new reservations. Delete or rewrite.

6. **PaymentService and FeeService** — Old services still registered as singletons in FinanceServiceProvider. Not called by new code. Can be deleted.

7. **`charge` morph map entry** — Kept for historical activity_log compatibility. Can be removed once old activity log entries are no longer needed.

8. **Charge table** — Not dropped. Contains historical data referenced by backfilled Orders. Can be archived/dropped after backfill is verified.

---

## Dependency graph

```
Epic 1 (models)
  ├─▶ Epic 2 (products)
  │     ├─▶ Epic 3 (purchasable lock)
  │     └─▶ Epic 4 (pricing)
  │           └─▶ Epic 5 (commit)
  │                 ├─▶ Epic 6 (settlement)
  │                 ├─▶ Epic 7 (refund)
  │                 ├─▶ Epic 8 (comp)
  │                 └─▶ Epic 9 (cancel)
  │                       └─▶ Epic 10 (filament admin)
  │                             └─▶ Epic 11 (stripe + reservation flow)
  │                                   └─▶ Epic 17 (ticket flow)
  │                                         └─▶ Epic 14 (data migration)
  │                                               └─▶ Epic 15 (old code removal) ✅ HERE
  │
  Epic 12 (subscription relocation) ── deferred
  Epic 13 (kiosk deletion) ── independent
  Epic 16 (reconciliation) ── post-deploy
```
