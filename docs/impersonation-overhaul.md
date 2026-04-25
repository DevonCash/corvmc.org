# Impersonation Overhaul

Staff impersonation lets authorized staff view the Member and Band panels as another user — useful for debugging issues, seeing what a member sees, and assisting with complex problems. Today the feature partially works via `stechstudio/filament-impersonate` (wrapping `lab404/laravel-impersonate`), but the redirect is broken and the action only appears on the Users list table.

This spec covers three changes: fix the redirect, add the action to the user detail page, and block staff panel access during impersonation.

---

## What exists today

The `Impersonate::make()` action sits in `UsersTable.php` as a record action. It calls `filament('member')->getUrl()` in a `redirectTo` closure, but in practice the redirect lands back on the staff panel instead of `/member`. The `canImpersonate` check on the User model gates on the `impersonate users` permission (currently admin-only). A banner component from the package shows at the top of any Filament panel during an active impersonation session, with a "Leave" link that ends the session and returns to the originating page.

All three panels (Staff, Member, Band) share the `web` auth guard, which means an impersonated session has no guard-level boundary preventing navigation to `/staff`.

---

## Changes

### 1. Fix the redirect

Replace the `redirectTo` closure with a hardcoded path to the member panel dashboard.

**Current** (in `UsersTable.php`):
```php
Impersonate::make()
    ->hiddenLabel()
    ->redirectTo(function () {
        $panel = filament('member');
        return method_exists($panel, 'getUrl') ? $panel->getUrl() : '/member';
    }),
```

**New:**
```php
Impersonate::make()
    ->hiddenLabel()
    ->redirectTo('/member'),
```

The closure resolution appears to be the issue — `filament('member')->getUrl()` may not resolve correctly in the Livewire action context. A hardcoded `/member` path is stable and matches the panel path configured in `MemberPanelProvider`.

### 2. Add impersonate action to ViewUser header

Add the same `Impersonate::make()` action to `ViewUser::getHeaderActions()`, alongside Edit, Add Credits, and Deduct Credits.

```php
// In ViewUser.php getHeaderActions()
Impersonate::make()
    ->redirectTo('/member'),
```

On this page the action should show its label (not `hiddenLabel`) since header actions have room for text. The package's built-in visibility logic handles the rest — it checks `canImpersonate()` on the current user, `canBeImpersonated()` on the target (if defined), and hides itself when already impersonating.

### 3. Block staff panel access during impersonation

Add middleware to the Staff panel that redirects to `/member` when the current session is impersonating. This prevents an impersonated session from accessing the staff panel regardless of the target user's roles.

**New middleware** at `app/Http/Middleware/BlockImpersonationInStaffPanel.php`:

```php
class BlockImpersonationInStaffPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(ImpersonateManager::class)->isImpersonating()) {
            return redirect('/member');
        }

        return $next($request);
    }
}
```

Register it in `StaffPanelProvider` via `->middleware([..., BlockImpersonationInStaffPanel::class])`, after the auth middleware so the session is already resolved.

The "Leave" banner route (`filament-impersonate/leave`) is registered outside the panel middleware stack, so it remains accessible — staff can always end the impersonation session from any panel.

### 4. Audit logging

Log impersonation start and stop events using `spatie/laravel-activitylog`. The `lab404/laravel-impersonate` package fires `TakeImpersonation` and `LeaveImpersonation` events — two listeners in the integration layer handle them.

**New listener** at `app/Listeners/LogImpersonationStarted.php`:

```php
// Listens to Lab404\Impersonate\Events\TakeImpersonation
public function handle(TakeImpersonation $event): void
{
    activity('impersonation')
        ->causedBy($event->impersonator)
        ->performedOn($event->impersonated)
        ->withProperties(['action' => 'start'])
        ->log('Started impersonating ' . $event->impersonated->name);
}
```

**New listener** at `app/Listeners/LogImpersonationEnded.php`:

```php
// Listens to Lab404\Impersonate\Events\LeaveImpersonation
public function handle(LeaveImpersonation $event): void
{
    activity('impersonation')
        ->causedBy($event->impersonator)
        ->performedOn($event->impersonated)
        ->withProperties(['action' => 'stop'])
        ->log('Stopped impersonating ' . $event->impersonated->name);
}
```

Both listeners live in `app/Listeners/` (integration layer — they react to a package event and write to the activity log, not domain logic owned by any module). Register them in `EventServiceProvider`.

These entries surface automatically anywhere the activity log is displayed, such as a user detail activity feed in the Staff panel.

---

## What changes

| Area | Change |
|---|---|
| `UsersTable.php` | Simplify `redirectTo` to hardcoded `/member` path |
| `ViewUser.php` | Add `Impersonate::make()` to header actions |
| `BlockImpersonationInStaffPanel` (new) | Middleware that redirects impersonating sessions away from `/staff` |
| `StaffPanelProvider.php` | Register the new middleware |
| `LogImpersonationStarted` (new) | Listener that logs impersonation start to activity log |
| `LogImpersonationEnded` (new) | Listener that logs impersonation stop to activity log |
| `EventServiceProvider` | Register the two new listeners |

## What doesn't change

| Area | Notes |
|---|---|
| Permission model | `impersonate users` permission and `canImpersonate()` on UserPolicy unchanged |
| `lab404/laravel-impersonate` | No package config changes needed |
| `stechstudio/filament-impersonate` | No package config changes; banner and leave route work as-is |
| Member and Band panels | No changes — impersonation sessions are already visible in both (same `web` guard, banner shows on both) |
| Activity logging | Now in scope — uses existing `spatie/laravel-activitylog`, no new tables |

## Deferred

**Notification to impersonated user.** Flagged in the action audit. Some orgs notify users when their account is accessed by staff. Not in scope here.

**Restricting destructive actions during impersonation.** The action audit asks whether an impersonator can take destructive actions (cancel reservations, etc.). This would require a broader policy review and isn't part of this overhaul.

## Open questions

None — scope is fully defined.
