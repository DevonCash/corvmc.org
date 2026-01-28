# Authorization & Policies

This document describes the authorization architecture used in this application.

## Core Principles

1. **Integration layer owns authorization** - Policies live in `app/Policies/`, not in modules. This keeps authorization centralized and allows cross-module concerns.

2. **Modules own domain knowledge** - Models expose relationship helpers (e.g., `isOrganizedBy()`, `isOwnedBy()`) that policies can use. Models should NOT contain authorization logic directly.

3. **Policies use roles + context** - No permission strings. Use `$user->hasRole('role name')` combined with model relationship methods. This is simpler and more explicit than managing granular permissions.

4. **Domain verbs, not just CRUD** - Define policy methods that match domain actions: `publish`, `cancel`, `postpone`, `reschedule`, `managePerformers`. Standard CRUD methods (`view`, `create`, `update`, `delete`) are still used but supplemented with domain-specific methods.

## Policy Pattern

```php
// app/Policies/EventPolicy.php
class EventPolicy
{
    // Role-based management check
    public function manage(User $user): bool
    {
        return $user->hasRole('production manager');
    }

    // Context-based: manager OR owner
    public function update(User $user, Event $event): bool
    {
        return $this->manage($user) || $event->isOrganizedBy($user);
    }

    // Domain verb with business rule
    public function publish(User $user, Event $event): bool
    {
        return $this->update($user, $event) && $event->canPublish();
    }
}
```

## Manager Roles by Domain

| Domain | Manager Role | Ownership Helper |
|--------|--------------|------------------|
| Events | `production manager` | `isOrganizedBy()` |
| Reservations | `practice space manager` | `isOwnedBy()` |

## Model Ownership Helpers

Models should provide simple boolean helpers for ownership checks:

```php
// In Event model
public function isOrganizedBy(User $user): bool
{
    return $this->organizer_id === $user->id;
}

// In Reservation model
public function isOwnedBy(User $user): bool
{
    return $this->reservable_type === User::class
        && $this->reservable_id === $user->id;
}
```

## Policy Location & Auto-Discovery

Policies in `app/Policies/` are auto-discovered by Laravel using naming conventions:
- `App\Models\Event` → `App\Policies\EventPolicy`
- `CorvMC\Events\Models\Event` → `App\Policies\EventPolicy`

Do NOT register policies manually with `Gate::policy()` in service providers.

## Testing Policies

Policy tests live in `tests/Feature/Policies/` and directly instantiate the policy class:

```php
beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->policy = new EventPolicy();
});

it('allows production manager to manage events', function () {
    $manager = User::factory()->withRole('production manager')->create();

    expect($this->policy->manage($manager))->toBeTrue();
});

it('allows organizer to update their own event', function () {
    $organizer = User::factory()->create();
    $event = Event::factory()->create(['organizer_id' => $organizer->id]);

    expect($this->policy->update($organizer, $event))->toBeTrue();
});
```

## Checking Authorization in Code

### In Actions

```php
if (! User::me()?->can('publish', $event)) {
    throw new AuthorizationException('You are not authorized to publish this event.');
}
```

### In Filament Resources

Filament automatically uses policies for resource authorization. Define which abilities map to which actions in your resource.

### In Controllers/Livewire

```php
$this->authorize('update', $event);
// or
if ($user->can('update', $event)) { ... }
```

## Migration from Permission Strings

When refactoring from permission-based to role-based authorization:

1. Create new policy in `app/Policies/`
2. Replace `$user->can('permission string')` with `$user->hasRole('role name')`
3. Replace `$user->givePermissionTo('...')` in tests with `$user->assignRole('...')`
4. Remove `Gate::policy()` registration from module service providers
5. Delete old policy file from module
6. Update test descriptions to reflect role-based checks
