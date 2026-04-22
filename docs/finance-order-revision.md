# Finance / Order Revision

Greenfield design for the Finance module. Contrasts with what's on disk today — `Charge`, `Chargeable`, `CreditTransaction`, `UserCredit`, `HasCharges`, `PaymentService::createCharge`, and the `credits_applied` JSON column — not with earlier drafts of this doc. Nothing in the proposed model (Order, LineItem, Transaction, Product) exists yet.

The central shifts: split `Charge` into purchase / balance / payment; stop modeling credits as a currency; gate reservation confirmation on explicit user commitment.

---

## Why the refactor

Today's Finance has three issues:

1. **`Charge` is overloaded.** One model carries the purchase description (what was bought), the credits applied (`credits_applied` JSON column), the payment method, and the settlement status. Changing any of those dimensions requires touching all of them.
2. **Credits are modeled as a currency.** `UserCredit` holds balances in cents, `CreditTransaction` is a currency-keyed ledger, and `PaymentService` splits payment across credit "currencies" as if they were rails. In practice every credit application always resolves to a cents amount that reduces what the customer owes. Modeling it as a currency adds friction with no payoff — and scattering credit state across `Charge.credits_applied`, `CreditTransaction`, `UserCredit`, and `Reservation.free_hours_used` has meant `HandleChargeableUpdated` / `adjustCredits` diff-and-move-credits code to keep them in sync.
3. **Reservations can confirm without payment.** The state machine allows `Scheduled → Confirmed` independent of `Charge.status`. Patrons have confirmed and fulfilled unpaid reservations. This is the original gap the refactor exists to close.

The revision splits `Charge` into three independent things, removes credits from the payment side entirely, and gates reservation confirmation on explicit user commitment.

---

## The three worlds

The mental model the rest of this doc assumes:

- **Purchase** — what the customer is buying. Described by `Product`, captured as `LineItem` rows on an `Order`. The Order total is the sum of its LineItems.
- **Balance** — what the customer has. Per-user wallets (credits today, prepaid balances tomorrow). Owned by `bavix/laravel-wallet`. Not a currency; a spendable resource that, when applied, reduces the Order total.
- **Payment** — how the customer pays. `Transaction` rows in legal currency (Stripe, cash). A Transaction is a commitment to move real money; it clears at webhook (Stripe) or staff action (cash).

These three are intentionally separate. A wallet balance does not "pay" an Order. It triggers a discount LineItem on the Order; the Order total drops; fewer legal-currency Transactions are needed to cover it. The Order can settle with zero Transactions (fully discounted), one Transaction (single rail), or many Transactions (split Stripe + cash).

Every Transaction is legal money moving. Every wallet withdrawal is a balance movement. Every LineItem is a line on the purchase description. The three ledgers don't interleave.

---

## Credits as discount, not currency

The core conceptual shift.

Today, applying 4 blocks of free hours to a $60 rehearsal produces:
- A `credits_applied = {free_hours: 4}` entry on the Charge.
- A `CreditTransaction` row in the `free_hours` currency.
- A reduction in `UserCredit.balance`.
- `PaymentService` treats `free_hours` as one of the payment rails; the "net" that hits Stripe is what's left.

Tomorrow, the same application produces:
- A **wallet withdrawal** (bavix/laravel-wallet) against the user's `free_hours` wallet — the balance movement, with its own ledger entry managed by the library.
- A **discount LineItem** on the Order with `amount` = the negative cents value of the withdrawn blocks — the price impact on the purchase.

Two records, different worlds. Neither is a Transaction. The Order total drops by the discount amount. If the remaining total is zero, no legal-currency Transaction is written and the Order settles directly (see Order Lifecycle → Commit with zero outstanding). If positive, Stripe or cash covers the rest.

**Why two records and not one.** The wallet withdrawal belongs to Balance — it's how we know the user's credit balance is correct, and it's the audit trail for what came out of which wallet. The discount LineItem belongs to Purchase — it's how the Order displays a breakdown, how analytics roll up by product, and how refunds reverse cleanly (refund the LineItem by writing back to the wallet). Collapsing them into a single row means one world has to impersonate the other, and we lose the clean separation the rest of the design depends on.

**Pre-payment is the same mechanism.** If we ever let members pre-load a dollar balance, it's a new wallet type (`prepaid_usd`, 1:1 cents-per-unit) declared in `config/finance.php` and listed on whichever Product classes should accept it (likely all of them). It produces a wallet withdrawal + discount LineItem at commit, just like `free_hours`. No new code paths in Finance.

---

## Products

A `Product` describes *how Finance should price and ledger a given thing*, keyed by the domain class it represents. This replaces today's `Chargeable` interface on domain models (`RehearsalReservation implements Chargeable`, etc.).

The problem with `Chargeable`: every chargeable model pulls a cluster of Finance methods into its own class (`getChargeableDescription`, `getChargeableAmount`, `getBillableUser`, and helpers scattered through `HasCharges`). The domain model has to know how Finance prices it, which couples SpaceManagement-side models to Finance and makes non-model line items (processing fees, sustaining discounts) second-class — they aren't chargeables, so today they get synthesized inside `PaymentService` with ad-hoc logic rather than living on the same axis as anything else.

The new shape moves all of that into Finance-side classes the domain doesn't know about:

```php
// Finance-side product definition, keyed to a domain class.
class RehearsalProduct extends Product
{
    public static string $type = 'rehearsal_time';
    public static string $model = RehearsalReservation::class;

    public static function billableUnits(RehearsalReservation $r): float { ... }
    public static function pricePerUnit(RehearsalReservation $r): int   { ... }
    public static function description(RehearsalReservation $r): string { ... }
    public static function eligibleWallets(RehearsalReservation $r): array
    {
        return ['free_hours'];
    }
}
```

Static methods, so the class is a pure description — no construction, no state. Callers go through a `Product` instance that holds the domain model and threads access to the statics via `__get`:

```php
$product = Finance::productFor($reservation);
$product->billableUnits;       // → RehearsalProduct::billableUnits($reservation)
$product->pricePerUnit;        // → RehearsalProduct::pricePerUnit($reservation)
$product->eligibleWallets;     // → RehearsalProduct::eligibleWallets($reservation)
$product->description;         // → RehearsalProduct::description($reservation)
```

The `Product` instance is Finance's local view; the domain model stays Finance-ignorant. `RehearsalReservation` doesn't implement a Finance interface, doesn't import Finance types.

**Category-style products** (processing fee, sustaining discount, comp discount, manual adjustment) use the same shape without a domain model. Their statics take no argument; the instance-threading is the identity. They register under the same `Finance::register()` call. This gives non-model line items first-class standing — today's `FeeService::calculateProcessingFee()` and the sustaining-member discount logic inside `PricingService` both become product classes with consistent shape.

**Registration** is one flat list of product classes in the integration layer:

```php
// app/Providers/AppServiceProvider.php
Finance::register([
    RehearsalProduct::class,
    TicketProduct::class,
    EquipmentLoanProduct::class,

    ProcessingFeeProduct::class,
    SustainingMemberDiscountProduct::class,
    CompDiscountProduct::class,
    ManualAdjustmentProduct::class,
]);
```

Finance resolves `LineItem.product_type` through its own registry, not the global morph map (same reasoning as before: app-wide aliases are orthogonal to Finance's product taxonomy).

---

## Library strategy

The pieces we're adding — `Order`, `LineItem`, `Transaction`, `Product`, state machines — don't exist in the codebase today. That frees us to lean on maintained packages rather than hand-roll.

- **`bavix/laravel-wallet`** owns Balance. Every credit type becomes a named wallet on the user. `deposit()` / `withdraw()` / `transfer()` are library calls; balance queries are library calls; the wallet ledger is library-managed. Replaces `UserCredit`, `CreditTransaction`, and most of `CreditService`.
- **Laravel Cashier** owns Stripe. Already installed and in production for subscriptions. The refactor extends its use to one-time order checkout (today's `PaymentService::createCharge` does Stripe work by hand where it could go through Cashier).
- **`spatie/laravel-model-states`** owns state machines for Order, Transaction, and Ticket. Entering/exiting hooks live on state classes; transitions reject unregistered edges. Replaces today's flat status enums (`ChargeStatus`, `PaymentStatus`, `Ticket.status` string column).
- **Custom** — `Order`, `LineItem`, `Transaction`, `Product`. These are the refactor's own models and have no equivalent today.

Trade-off acknowledged: bavix/laravel-wallet has opinions (how balances are stored, transaction types, floatings). Accepting those opinions is the point — we're not building ledger infrastructure, we're describing what the community center does on top of it.

---

## Module boundaries

Finance remains fully decoupled from SpaceManagement. Neither module references the other's internal state. Finance is a payment processor; it doesn't know what a "Reservation" is except as the domain class bound to a registered Product.

**Finance module** (`app-modules/finance/`):
- Models: `Order`, `LineItem`, `Transaction`, `Product` (base class).
- Services: `PricingService`, `PaymentService`, `LedgerService`.
- Facade: `Finance`.
- State machines: `OrderState`, `TransactionState`.
- Gone from today: `Chargeable` contract, `Charge`/`CreditTransaction`/`UserCredit`/`CreditAllocation` models, `HasCharges`/`HasCredits` traits, `CreditService`/`FeeService`, `Subscription*` (moved to `app/`).

**Integration layer** (`app/`):
- Product classes (`RehearsalProduct`, `TicketProduct`, etc.) — Finance's view of domain models.
- `CommitOrderAction` — the user-facing write that creates the Order, confirms the Reservation, and triggers the commit in one DB transaction.
- `MemberBenefitService` — credit allocation rules (who gets what, when).
- `Subscription` model and `SubscriptionService` — Cashier-backed, unchanged mechanics; moved out of Finance.
- Cross-module listeners — `ReservationCreated` (and equivalent ticket / equipment-loan creation events) → create Pending Order; `OrderSettled` → ticket activation, receipt dispatch; refund-window policy gates.

**SpaceManagement module** (`app-modules/space-management/`):
- `Reservation`, `ReservationState`, recurring-series logic.
- Knows nothing about Orders, Transactions, LineItems, Products.
- Fires domain events (`ReservationCreated`, `ReservationCancelled`); integration-layer listeners translate those into Finance operations.

---

## Finance Facade

Single entry point. All write paths and registry queries go through `CorvMC\Finance\Facades\Finance`, backed by a `FinanceManager` singleton.

### Pricing

```php
Finance::price(array $items, ?User $user = null): Collection;
```

Returns an Eloquent `Collection<LineItem>` of **unpersisted** LineItems. Each item in `$items` is either a domain model (Finance resolves it via `productFor()`) or a category tuple (`['type' => 'processing_fee', 'amount' => 250]`). When `$user` is provided, PricingService walks the user's wallets against each product's `eligibleWallets`, applies discounts up to the available balance, and emits discount LineItems alongside the base product LineItems.

`price()` is pure. No DB writes, no wallet calls. It only previews; nothing moves until commit.

### Product resolution

```php
Finance::productFor(Model $domainModel): Product;
Finance::productByType(string $type): Product;     // for category products with no model
Finance::registeredTypes(): array;
```

`productFor()` looks up the product class whose `$model` matches (or is a subclass of) the given instance and returns a `Product` bound to it.

### Commitment

```php
Finance::commit(Order $order, PaymentData $payment): CommitmentResult;
Finance::settle(Transaction $transaction): void;
Finance::refund(Order $order): RefundResult;
```

`commit()` is the write. Opens a `DB::transaction`, locks the Order, executes wallet withdrawals for each discount, inserts the discount LineItems, inserts payment Transactions in `Pending`, drives credit-currency side effects, and returns a `CommitmentResult`. If the Order total is zero after discounts, no payment Transactions are written and the Order settles immediately. If all payment Transactions clear synchronously, the Order settles at the end of the DB transaction. Otherwise the Order stays `Pending` until the last Transaction clears.

There is no separate `planCommit()` / dry-run method. The confirmation modal composes its preview from `Finance::price($items, $user)` (which already carries the discount LineItems clipped to wallet balance) plus the user's chosen payment split. `commit()` validates and throws early on any reason it can't proceed — insufficient balance, unsupported currency, locked purchasable — before any side effect has been written, since validation runs inside the outer `DB::transaction` and any throw rolls the whole thing back.

`settle()` transitions a Pending Transaction to Cleared. The Stripe webhook calls it; the "Mark paid (cash)" staff action calls it. When the last Pending Transaction on an Order clears, an `OrderSettled` event fires (from the Order's `Completed::entering()` hook).

`refund()` transitions the Order to `Refunded`. Per-rail cascade: wallet deposits reverse the discount LineItems (credits returned); Stripe refunds are initiated; cash refunds create a staff task. Refund Transactions are written as `Pending` in their respective rails and clear on the same triggers.

### Balance

```php
Finance::balance(User $user, string $walletType): int;
Finance::allocate(User $user, string $walletType, int $amount, string $reason, ?Model $source = null): void;
Finance::adjust(User $user, string $walletType, int $amount, string $reason): void;
```

Thin wrappers over `bavix/laravel-wallet`. `balance()` returns current balance in cents (or the wallet's native unit, converted). `allocate()` and `adjust()` are `deposit()` / signed `deposit-or-withdraw()` with standardized metadata (reason, source). Neither writes to `transactions` — that table is legal currency only.

**Trait placement.** `HasWallets` from bavix goes on the `User` model because bavix's API (`deposit`, `withdraw`, `getWallet`, `balance`) is only reachable through the trait — the facade methods call those trait methods internally. Callers outside Finance are expected to go through the facade, not through `$user->deposit(...)` / `$user->balance` directly: the facade is where standardized metadata (reason, source), event dispatch, and the test seam live. The trait is a library requirement; the facade is the blessed access path.

---

## Wallet eligibility

Declared on the Product, not in config. Each Product's `eligibleWallets($model)` returns the list of wallet-type keys that can discount a LineItem for this product. The Product owns its own purchasing rules; the wallet itself doesn't carry a reverse list of products it applies to.

```php
class RehearsalProduct extends Product
{
    public static function eligibleWallets(RehearsalReservation $r): array
    {
        return ['free_hours'];
    }
}

class EquipmentLoanProduct extends Product
{
    public static function eligibleWallets(EquipmentLoan $l): array
    {
        return ['equipment_credits'];
    }
}
```

Config defines only the wallet itself — its unit value and display label:

```php
// config/finance.php
'wallets' => [
    'free_hours' => [
        'cents_per_unit' => 750,     // per 30-min block
        'label'          => 'Free rehearsal hours',
    ],
    'equipment_credits' => [
        'cents_per_unit' => 100,
        'label'          => 'Equipment credits',
    ],
],

'processing_fee' => [
    'rate_bps'    => 290,
    'fixed_cents' => 30,
],
```

`PricingService` walks each base LineItem, asks its Product for `eligibleWallets($model)`, and for each returned wallet with non-zero balance emits a discount LineItem up to the base amount.

**Why the Product, not the config.** The Product already describes "what this is and how it prices" — which wallets apply is the same kind of knowledge, and the Product owns it. Putting a reverse `applicable_to` list on the wallet duplicates the mapping on the wrong side and means two edits (config + Product) when a new product or wallet is added. Declaring eligibility on the Product also lets it be dynamic — a product could return different wallets based on user tier, reservation characteristics, or season — without widening the config schema.

**Adding a new wallet type** (e.g. `prepaid_usd`) is: define the bavix wallet; add a config entry (`cents_per_unit` + `label`); update the Product classes that should accept it by adding the wallet key to their `eligibleWallets()` return.

**Adding a new product** is: write the Product class with its own `eligibleWallets()`. No config change needed.

---

## Schema

### State machines

`Order.status` and `Transaction.status` are spatie/laravel-model-states state machines, not string enums. Entering/exiting hooks live on the state classes. Allowed transitions are declared per-state; unregistered transitions throw.

State classes:
- `CorvMC\Finance\States\OrderState` (abstract) with concrete `Pending`, `Completed`, `Comped`, `Refunded`, `Cancelled`.
- `CorvMC\Finance\States\TransactionState` (abstract) with concrete `Pending`, `Cleared`, `Cancelled`, `Failed`.
- `CorvMC\Events\States\TicketState` (abstract) with concrete `Pending`, `Valid`, `CheckedIn`, `Cancelled`.

### `orders`

```
id
user_id          integer, nullable   — null for guest purchases
status           OrderState          — Pending (default), Completed, Comped, Refunded, Cancelled
total_amount     integer (cents)     — denormalized sum of LineItems
settled_at       timestamp, nullable — set on transition into Completed or Comped
notes            text, nullable
timestamps
```

Terminal state semantics:
- **Completed** — payment was rendered (cash, Stripe, discounts, or a mix summing to total).
- **Comped** — order waived; no payment. Original LineItems intact; no payment Transactions.
- **Refunded** — reached settlement and was reversed. Refund Transactions present.
- **Cancelled** — terminated pre-settlement (or comp revoked). No payment was ever rendered.

"Settled" covers Completed + Comped. `OrderSettled` event fires on transitions into either.

### `order_line_items`

```
id
order_id         integer, FK
product_type     string       — Finance product-type string; resolved via Finance registry
product_id       integer, nullable  — populated for model-backed products; null for categories
description      string       — human-readable snapshot at purchase time
unit             string       — 'hour', 'ticket', 'fee', 'discount'
unit_price       integer      — cents per unit (negative for discounts)
quantity         decimal(8,2)
amount           integer      — cents; may be negative for discounts
timestamps
```

All cents. Discount LineItems carry negative `amount`. Sum of all LineItems equals `Order.total_amount`. Write-time validation: `LineItem` `saving` observer rejects any row whose `product_type` is not in `Finance::registeredTypes()`.

### `transactions`

Append-only ledger of **legal currency only** (Stripe, cash). Not credits. Not allocations. Not discounts. Those live elsewhere (wallet ledger for balance movements; LineItems for discounts).

```
id
order_id           integer, FK
user_id            integer, nullable — nullable for guest purchases
currency           string            — 'stripe' or 'cash'
amount             integer (cents)   — negative for customer outflow (payment), positive for refund
type               enum              — payment, refund, fee
status             TransactionState  — Pending (default), Cleared, Cancelled, Failed
cleared_at         timestamp, nullable
cancelled_at       timestamp, nullable
failed_at          timestamp, nullable
metadata           JSON, nullable    — { session_id, payment_intent_id, stripe_fee_id, cancellation.reason, failure.reason }
timestamps
```

Sign convention: payment rows are negative (money leaving customer), refund rows are positive (money returning), fee rows are positive (Stripe fee we track).

Row-level immutability: `amount`, `currency`, `type`, `order_id`, `user_id` are frozen once written. `status`, terminal timestamps, and `metadata` mutate during the lifecycle.

### `user_credits` — removed

Gone. bavix/laravel-wallet owns all balances. The library's `wallets` and `transactions` tables (internal to the library) are the source of truth. Our existing `UserCredit` model is deleted; `HasCredits` trait is replaced by bavix's `HasWallet` / `HasWallets`.

---

## Transaction lifecycle

The transition graph (see also `docs/transaction-flow.mermaid`):

```
            Pending ──(webhook / staff action)──▶ Cleared   [terminal]
               │
               ├────(session expire / staff void / Order cancel/comp)──▶ Cancelled  [terminal]
               │
               └────(rail rejection — card declined, etc.)──▶ Failed   [terminal]
```

- **Pending → Cleared** is the only happy path. Triggered by Stripe webhook (for stripe rows) or staff "Mark paid cash" (for cash rows).
- **Pending → Cancelled** is the voided-commitment path — the user abandoned, staff voided, or the parent Order was cancelled/comped before settlement. `metadata.cancellation.reason` records which.
- **Pending → Failed** is the rail-rejection path (rare; Checkout Session rarely produces rail-level rejections, mostly reserved for future PaymentIntent / Terminal flows). A Failed row is append-only audit; the user retries, which spawns a new Pending row.

Cleared, Cancelled, and Failed are all terminal.

## Order lifecycle

```
             Pending ──(all payment Tx Cleared, total covered)──▶ Completed  [terminal]
                │
                ├─────(staff comp)────────────▶ Comped       [terminal]
                │
                ├─────(cancel while Pending)──▶ Cancelled    [terminal]
                │
Completed/Comped ──(refund requested)──▶ Refunded            [terminal]
```

Two distinct positive terminal states (Completed, Comped). Two distinct negative terminal states (Cancelled, Refunded). `OrderSettled` fires on entry to Completed or Comped.

Transition hooks:
- `Pending → Completed` — `entering()`: assert all payment Transactions are Cleared and sum to `total_amount`; `entered()`: fire `OrderSettled`, which activates Tickets and dispatches receipt notifications.
- `Pending → Comped` — `entering()`: cascade any Pending Transactions to `Cancelled` with `cancellation.reason = 'order_comped'`; `entered()`: fire `OrderSettled`, activate Tickets.
- `Pending → Cancelled` — `entering()`: cascade Pending Transactions to `Cancelled`, transition attached Tickets to `Cancelled` (preserving CheckedIn).
- `Completed/Comped → Refunded` — `entering()`: for each Cleared payment Transaction, write a compensating refund Transaction (Pending) and initiate the rail-specific refund; for each discount LineItem, reverse the wallet withdrawal (deposit back to the wallet); transition attached Tickets to `Cancelled` (preserving CheckedIn as audit).

The Transaction-driven settlement loop (see `docs/transaction-flow.mermaid`) is the only path to Completed. No service code flips `Order.status` directly — settlement is driven by a listener on `TransactionCleared` that re-evaluates the Order's outstanding balance.

---

## Commit flow

The commit is one DB transaction that does six things atomically:

1. Lock the Order for update.
2. For each discount LineItem in the commit plan: call `wallet.withdraw()` (bavix) to move balance. Record the withdrawal; write the negative-amount discount LineItem on the Order.
3. For each payment rail in the commit plan: insert a Pending Transaction (Stripe, cash, or both).
4. For the Stripe portion (if any): create the Checkout Session via Cashier, store the session id in `Transaction.metadata`.
5. If the Order has a reservation, transition the reservation `Scheduled → Confirmed`.
6. If every Transaction is already Cleared (i.e. zero Stripe, zero cash, fully discount-covered), transition the Order to `Completed`.

Commit atomicity: the wallet withdrawal, the Pending Transaction insert, the reservation confirmation, and the Order-state update share one DB transaction. If anything throws, everything rolls back — balance, ledger, reservation state, Order state. No partial commits.

Post-commit: listeners dispatch `afterCommit` so they see consistent committed state. The user is redirected to Stripe Checkout if applicable, or returned to a confirmation screen if everything cleared synchronously.

### Settlement

Each Pending Transaction clears independently on its own trigger:
- **Stripe**: webhook handler calls `Finance::settle($transaction)`.
- **Cash**: staff "Mark paid (cash)" action calls `Finance::settle($transaction)`.

`settle()` transitions `Pending → Cleared`, writes `cleared_at`, fires `TransactionCleared`. A listener on `TransactionCleared` checks the Order: if no Pending Transactions remain and Cleared sums to `total_amount`, it transitions the Order to `Completed`.

See `docs/transaction-flow.mermaid` for the full graph including refund paths.

### No auto-commit

No scheduled or background job ever commits an Order. Every `Pending → Cleared` is user-initiated (user finished checkout, Stripe webhook received, staff marked cash). The only background activity on Pending Transactions is the **no-show sweep** that cancels stale Pending commitments past their SLA — it transitions Pending → Cancelled, never Pending → Cleared.

---

## Refund flow

Refunds are Order-level and cohesive. Either the whole Order refunds or none of it does. Per-line partial refunds are out of scope (see Deferred).

`Finance::refund($order)` triggers `Completed/Comped → Refunded`. The `entering()` hook:

- For each **discount LineItem** (wallet-triggered): call `wallet.deposit()` to return balance. No Transaction is written — credits aren't transactions in this model.
- For each **Cleared payment Transaction** (Stripe, cash): write a new `type: refund, status: Pending` Transaction, amount inverted. For Stripe, call the Stripe refund API; clear on the `charge.refunded` webhook. For cash, create a staff cash-out task; clear when staff confirms cash-handed-back.

Refund Transactions follow the same lifecycle as payment Transactions — they sit Pending until their rail clears them.

**Policy gates are the caller's job.** Finance doesn't know whether a reservation is eligible for refund (e.g. "reservation not yet started" or "cancellation window still open"). The caller (refund orchestrator in `app/`) checks the rules, then calls `Finance::refund()`. Finance executes the mechanics.

---

## Purchasable lock

Any domain model bound to an active (non-Cancelled) Order is immutable. Reshaping — different time, different equipment, different attendees — goes through cancel-and-rebook.

Enforcement is a `saving` model observer attached by the Product registration: if the model has an attached Order and that Order is not Cancelled, throw `PurchasableLockedException`. Applies uniformly to every Product-registered model (Reservation, Ticket, EquipmentLoan, future additions).

This kills the old `HandleChargeableUpdated` / `adjustCredits` diff-and-move-credits logic entirely. No listener watches for price changes on bound models because bound models can't change.

---

## Processing fees

Fee coverage is represented by LineItem presence, not a flag on the Order. At the commitment modal the user toggles "cover processing fees"; the choice enters `commit()` via `PaymentData.coversFees` (transient, not persisted). When true, `PricingService` emits a `processing_fee` LineItem computed from config (`rate_bps` + `fixed_cents`); the LineItem is written with the rest of the Order at commit. When false, no fee LineItem is written.

After commit, "did the customer cover fees?" is answered by whether the Order has a `processing_fee` LineItem. The LineItem survives on fully-discounted Orders too (a credit discount can cancel both the reservation cost and the fee — all three LineItems remain on the Order and sum to zero), so the intent record is preserved regardless of whether Stripe actually ran.

Independent of that choice, every Stripe-rail payment writes a `type: fee` Transaction when the Stripe fee is recorded. If the Order has a `processing_fee` LineItem, the fee was added to what the customer paid; if not, the org absorbs it. The fee Transaction is the accounting record of the Stripe side either way.

---

## Stripe integration

Checkout Sessions remain the primary flow. Identifier storage moves from columns on `Charge` to `Transaction.metadata`:
- `session_id` stored on the Stripe payment Transaction's metadata at commit time.
- `payment_intent_id` recorded when the session completes.
- Webhook correlation via `session.metadata.transaction_id` (we set this when creating the session).

**Webhook idempotency**:
- A `stripe_webhook_events` table with unique `event_id` deduplicates at the event layer.
- spatie/laravel-model-states transition validation rejects `Pending → Cleared` running twice on the same Transaction.
- Functional unique index on `transactions.metadata->>'session_id'` guards against duplicate Transaction creation.

---

## Concurrency & atomicity

- **Commit** runs inside `DB::transaction` with `lockForUpdate` on the Order. Wallet withdrawals, LineItem inserts, Transaction inserts, and state transitions all share the transaction.
- **Settlement** runs inside `DB::transaction` with `lockForUpdate` on the Transaction. The TransactionCleared listener re-reads the Order's Transactions inside its own locked transaction to decide whether to transition the Order to Completed.
- **Refund** runs inside `DB::transaction` with `lockForUpdate` on the Order.
- **Events** dispatch `afterCommit`.
- **Reconciliation job** runs nightly: reasserts wallet balance invariants against bavix's ledger, flags Pending Transactions past SLA, and logs drift. No auto-correction — drift is human-reviewed.

---

## Filament Admin

Mirror of today's surface, retargeted at Order/Transaction.

**`OrderResource`** — straight replacement for `ChargeResource`. Same nav group, same icon, same staff roles.
- Columns: ID, state, user, primary product type, `total_amount`, `paid_amount` (derived), `outstanding`, timestamps.
- Filters: state, product type, date range.
- Infolist: LineItems sub-panel, Transactions sub-panel (read-only).
- Actions: Refund (Order-level), Cancel, Comp (full), Mark paid (cash).

**`TransactionResource`** — new. Full-system legal-currency ledger.
- Columns: ID, Order, user, currency, type, amount, state, terminal timestamp.
- Row actions: Mark paid (cash), Void.

**Relation managers**:
- `LineItemsRelationManager` on Order — read-only.
- `TransactionsRelationManager` on Order and User — read-only, with Mark paid (cash) / Void row actions.
- `WalletsRelationManager` on User — bavix-backed; shows per-wallet balance, recent ledger entries, and an "Allocate / Adjust" action that routes through `Finance::allocate()` / `Finance::adjust()`.

Partial comp UX and per-Transaction refund UX are deferred. Model supports them; the admin doesn't expose them in this refactor.

---

## Dashboard

`StaffDashboard` queries rewritten to source from Transactions + LineItems. Metrics preserved 1:1:

| Metric today | New source |
|---|---|
| `charges_collected` | `SUM(-amount)` from Transactions where `status=Cleared, type=payment` in period |
| `credits_applied` | `SUM(-amount)` from LineItems where `product_type` is a discount category in period (joined to Order) |
| `cash_collected` | Same as charges_collected, scoped to `currency='cash'` |
| `charges_pending` | `COUNT(DISTINCT order_id)` from Orders where `status=Pending` |
| `total_fees` | `SUM(amount)` from Transactions where `type=fee, status=Cleared` |
| Subscription metrics | Unchanged — Cashier tables |

---

## Migration strategy

### New tables
Create `orders`, `order_line_items`, `transactions`. Install bavix/laravel-wallet; run its migrations.

### Carry forward wallet balances
Per-user balances from `user_credits.balance` (keyed by `user_id` and `credit_type`) are written as starting balances on bavix wallets. One wallet per `CreditType` per user. The historical `credit_transactions` ledger is **not** replayed — bavix starts fresh, `credit_transactions` stays archived.

### Backfill from `charges`

**Rehearsal charges**:
- One Order per Charge.
- One base LineItem (`product_type='rehearsal_time'`), rate + units from config + reservation.
- One discount LineItem per credit type in `Charge.credits_applied` (negative amount = blocks × cents_per_block). Each is paired with a backdated wallet withdrawal on bavix, dated `charge.created_at`.
- One payment Transaction (Stripe or cash) from `Charge.status` + `payment_method`:
  - `Paid` with non-zero net → `status=Cleared`, `cleared_at=paid_at`, amount = `-net_amount`. Stripe session id moves to `Transaction.metadata.session_id`.
  - `CoveredByCredits` → no payment Transaction. Discount LineItems sum to total.
  - `Pending` → see grandfathering below.
  - `Cancelled` → Order goes to `Cancelled`; wallet withdrawals reversed; no payment Transaction synthesized.

**Ticket charges** (from `TicketOrder`):
- One Order per TicketOrder.
- LineItems: base ticket, sustaining discount (negative), Stripe fee if applicable — from existing columns.
- One Ticket row per quantity in `Valid` (bypassing Pending since the real-world Order is settled).
- One Stripe payment Transaction in `Cleared` with session id from related Charge metadata.

### Grandfathering pending charges at cutover

A `Pending` Charge at cutover has already had its credits deducted (today's system deducts at Charge creation). The migration preserves that reality:
- Wallet withdrawals written as if they happened at `charge.created_at` (bavix supports backdated entries).
- Discount LineItems written with the same effective date.
- Payment Transaction written as `Pending`. Post-cutover, the normal webhook / staff path transitions it to Cleared and settles the Order.
- `Order.metadata.grandfathered = true`.

### Dropped columns
- `reservations.free_hours_used` — derived from wallet ledger joined through Order.
- `ticket_orders.is_door_sale` — zero prod rows; kiosk module deleted.
- `ticket_orders.payment_method` — subsumed by `Transaction.currency`.
- `Charge.credits_applied`, `Charge.stripe_session_id`, `Charge.stripe_payment_intent_id` — retired with Charge.
- The `'charge' => Charge::class` entry in the global morph map.

### Archive
`charges`, `credit_transactions`, `credit_allocations`, and `user_credits` are retained as cold archives. No application code references them after cutover.

### Existing Confirmed-but-unpaid reservations
Small cohort. Cutover is timed to minimize. Any outliers are grandfathered Confirmed — the new confirmation gate applies to reservations created after cutover.

---

## Subscriptions — out of scope

Cashier-backed subscriptions stay in Cashier. Their financial mechanics (proration, dunning, fee coverage via Stripe price) are untouched. Subscription payments **do not** enter the Transaction ledger in this revision.

What moves (location only, no rewrite):
- `Finance\Models\Subscription` → `app/Models/Subscription` (still extends `Laravel\Cashier\Subscription`).
- `Finance\Services\SubscriptionService` → `app/Services/SubscriptionService`.
- `Finance\Actions\Subscriptions\*` → `app/Actions/Subscriptions/*`.

What stays the same:
- Cashier as source of truth for subscription state.
- Subscription webhook handling.
- `swapAndInvoice` / `noProrate()` / billing logic.
- `Subscription.covers_fees` fee-coverage Stripe price mechanism.

What crosses over: when a subscription invoice pays and `MemberBenefitService` allocates free hours, that's a `Finance::allocate()` call → `wallet.deposit()`. The allocation lives in bavix's ledger, not the `transactions` table.

Two representations of fee coverage coexist: Subscription-level lives on `Subscription.covers_fees` as a Cashier-managed flag driving a fee-coverage Stripe price; Order-level is the presence of a `processing_fee` LineItem on the Order. Same user intent, different worlds, different mechanisms. Unifying them is out of scope.

Bringing subscription payments into the Transaction ledger is a future revision.

---

## Kiosk / Door Sales — removed

The `kiosk` module is deleted in entirety. It has zero production rows (`TicketOrder.is_door_sale` always false) and depends on the retired `Charge` model. Porting it onto Order/Transaction now would be speculative.

Git history preserves the implementation if door sales return as a product priority. When that happens, the natural mapping is implied: a door-sale Order is just an Order with a cash (or Stripe Terminal) Transaction.

---

## Recurring reservations

No dedicated Finance representation. Each materialized instance is a separate Order; cancellation and refund loop over instances.

SpaceManagement-side changes driven by this refactor (noted here for visibility, implemented in SpaceManagement):
- Instance materialization moves eager → lazy (rolling window aligned with normal booking distance). Fewer open Pending Orders per member.
- No auto-confirm / auto-commit for recurring instances. Every instance requires explicit user commitment.
- Pricing is locked per-instance at commit time — rate changes mid-series don't retroactively apply.

The `deferCredits` no-op bug disappears: credits are never deducted at reservation creation under this model, so the flag, the branch, and the `ReservationCreated(deferCredits:...)` parameter all go away.

---

## What changes

| Area | Change |
|---|---|
| `Charge` model | Retired. Replaced by `Order` + `LineItem` + `Transaction`. |
| `CreditTransaction` model | Retired. Balance movements owned by bavix/laravel-wallet's ledger. |
| `UserCredit` model | Retired. Balances owned by bavix. |
| `CreditAllocation` model | Retired. Allocations are `Finance::allocate()` calls into bavix; bavix's ledger is the audit trail. |
| `Chargeable` interface (on `RehearsalReservation`, `TicketOrder`, etc.) | Retired. Replaced by Finance-side `Product` classes keyed to domain models. Domain models no longer import Finance types. |
| `HasCharges` trait | Retired. Relationships wired via `Product::register()` using `resolveRelationUsing`. |
| `HasCredits` trait | Retired. Replaced by bavix's `HasWallets`. |
| `CreditService` | Retired. Deposit / withdraw / balance go through bavix; allocation policy moves to `MemberBenefitService`. |
| `FeeService` | Retired. Processing-fee math moves into `ProcessingFeeProduct`; fee Transaction creation moves into `PaymentService`. |
| `PaymentService::createCharge($chargeable, bool $deferCredits)` | Signature gone. The whole "pick credit currencies, split net to Stripe" logic is replaced by commit(): wallet withdrawals + discount LineItems + legal-currency Transactions. `deferCredits` parameter deleted everywhere — credits are never touched at reservation creation. |
| `PricingService` | Retained as a name; rewritten. Returns `Collection<LineItem>` instead of `PriceCalculationData`; asks each Product for `eligibleWallets($model)` and emits discount LineItems accordingly. |
| `PriceCalculationData` DTO | Retired. Callers read LineItems directly. |
| `Charge.credits_applied` JSON column | Retired. Per-credit application is one wallet withdrawal + one discount LineItem per credit type. |
| `ChargeStatus`, `PaymentStatus` enums | Retired. Replaced by `OrderState` and `TransactionState` (spatie/laravel-model-states). |
| `CreditType` enum | Retained as the set of wallet-type keys; referenced from config, not from `CreditTransaction.credit_type` (which is gone). |
| `CompData`, `PaymentData` DTOs | `PaymentData` extended to carry commit intent — wallets to draw, rails to charge, `coversFees` boolean (transient, not persisted — decides whether a `processing_fee` LineItem is written); `CompData` folded into Order comp flow. |
| `Subscription` / `SubscriptionService` / subscription actions | Move to integration layer. Mechanics unchanged. See Subscriptions. |
| Credits as currency | **Replaced** by credits as discount. Wallet withdrawal (bavix) + discount LineItem (Order). Transactions are legal currency only. |
| Payment rails in Transaction ledger | Legal currency only (`stripe`, `cash`). No credit currencies. |
| Credit deduction timing | At user commitment (inside commit DB txn). Not at reservation creation, not at Charge creation, not at Stripe webhook. |
| `HandleChargeableUpdated` / `adjustCredits` listener | Retired with no replacement. Purchasable lock prevents any edit-in-place. |
| `RecalculateUnsettledReservations` command | Retired. Price locked at commit, not recalculable. |
| `Reservation.free_hours_used` column | Dropped. Derived from wallet ledger joined through Order. |
| `TicketOrder` as `Chargeable` | Not Finance-bound. `Ticket` is a Product; `TicketOrder` has no Finance role. |
| `TicketOrder.is_door_sale`, `TicketOrder.payment_method` columns | Dropped. |
| `kiosk` module | Deleted. Depends on `Charge`, zero production rows (`is_door_sale` never true). |
| Recurring instance materialization | Eager → lazy rolling window. (SpaceManagement-side.) |
| Recurring instance auto-commit | Removed. Explicit commitment required. |
| `Ticket.status` column (flat enum `active`/`voided`) | Upgraded to spatie state machine `TicketState` (`Pending`, `Valid`, `CheckedIn`, `Cancelled`). Pending Tickets are checkable-in (sliding-scale policy). |
| Ticket creation timing | Eager — one row per attendee LineItem at Order creation, in `Pending` — rather than lazy in `TicketService::activateTickets()`. |
| Reservation confirmation gate | Gated on user commitment through checkout modal. Confirms at commit time (inside commit DB txn), not on Cleared payment. Closes the today-gap where `Scheduled → Confirmed` runs independent of `Charge.status`. |
| Processing fee handling | `Charge.covers_fees` column retired. User intent is carried only by LineItem presence: if the Order has a `processing_fee` LineItem, the customer covered fees; if not, the org absorbs. `PaymentData.coversFees` is the transient commit input that decides whether the LineItem is written. Stripe fee always recorded as `Transaction { type: fee }` — symmetric across covered and absorbed. |
| Stripe identifier storage | `Charge.stripe_session_id` / `Charge.stripe_payment_intent_id` retired. Identifiers live in `Transaction.metadata`. Webhook correlation via `session.metadata.transaction_id`. |
| `PaymentAccepted` event | Renamed `OrderSettled`. Fires from `OrderState\Completed::entering()` and `Comped::entering()`. |
| `ChargeResource` (Filament) | Renamed `OrderResource`. Same nav group, same icon, same staff roles. |
| `CreditTransactionsRelationManager` on User | Replaced by `WalletsRelationManager` (bavix-backed). |
| `TransactionResource` + Order/User relation managers | New. Legal-currency ledger surfaces. |
| Partial comp UX, per-Transaction refund UX | Deferred. Model supports; admin does not expose. |
| Dashboard queries | `StaffDashboard::getMonthlyRevenueData` / `getTodaysOperationsData` rewritten against Transactions + LineItems. Metrics preserved 1:1. |
| Cancellation flow | State-hook cascade via `OrderState` `entering()` hooks. Pending Transactions → Cancelled; Cleared payment Transactions → compensating refund Transactions; wallet withdrawals reversed; Tickets → Cancelled (CheckedIn preserved). |
| `LineItem.product_type` resolution | Finance-scoped registry, not global morph map. |
| Global morph map `'charge' => Charge::class` entry | Removed. `activity_log` rows with `subject_type='charge'` become unresolvable (dead historical data accepted). |

---

## What doesn't change

| Area | Notes |
|---|---|
| `PaymentData` DTO | Retained (extended) — shape evolves but the DTO at `Finance\Data\PaymentData` stays. |
| `MemberBenefitService` | Retained by name. Internals swap `CreditService` calls for `Finance::allocate()`; rule surface (who gets what, when) stays. |
| Reservation state machine | Unchanged on its own axis: `Scheduled → Confirmed → Completed / Cancelled`. New `CommitOrderAction` in `app/` drives `Scheduled → Confirmed` at commit. |
| Ticket fulfillment logic | QR code generation, attendee tracking, check-in workflow unchanged. Only creation timing and state-tracking shape change. |
| Module event/listener wiring pattern | Same `AppServiceProvider` pattern. Integration-layer actions (`CommitOrderAction`, `TransactionCleared` listener) live in `app/`. |
| Stripe webhook entry point | Same route, same signature verification. Internals resolve Transaction via `session.metadata.transaction_id` and transition to `Cleared`. |
| Subscription billing mechanics | Cashier unchanged. See Subscriptions. |
| Sustaining-member fee coverage | `Subscription.covers_fees` mechanism unchanged. |
| Subscription proration / upgrades / downgrades | Unchanged. |
| Global morph map aliases (other than `'charge'`) | Unchanged. Finance's product-type registry is additive and orthogonal. |

---

## Deferred

- **Partial comp UX** — negative-amount LineItem as comp_discount. Model supports it; admin action deferred.
- **Per-Transaction refund UX** — model supports per-rail refund; admin exposes only Order-level refund.
- **"Pending past SLA" / "drift" dashboard widgets** — reconciliation job lands; Filament visualization waits until the job's output tells us what's worth surfacing.
- **Subscription payments in Transaction ledger** — subscriptions stay in Cashier for this revision.
- **Door sales / kiosk** — module deleted; rebuilt if/when door sales return as a product priority.
- **Prepaid dollar balances** — architecture supports it (new wallet type with 1:1 cents_per_unit, config entry); no product demand yet.

---

## Open questions

None blocking. Design is specified; implementation can begin.

## Appendix: Transaction flow

See `docs/transaction-flow.mermaid` for the commit → settlement → refund state diagram.
