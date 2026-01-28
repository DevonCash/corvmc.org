# Subdomain-Per-Band Tenancy for Band Panel

## Overview

Change band panel URLs from path-based to subdomain-based tenancy:
- **Current**: `corvmc.org/band/{band-slug}/dashboard`
- **Proposed**: `{band-slug}.corvmc.org/dashboard`

---

## Pros

| Benefit | Details |
|---------|---------|
| **Memorable URLs** | Bands get shareable URLs like `therockers.corvmc.org` |
| **Cleaner paths** | `/dashboard` instead of `/band/therockers/dashboard` |
| **Brand identity** | Each band feels like they have their own "site" |
| **Future flexibility** | Could later support custom domains (`therockers.com`) |
| **Isolated analytics** | Easy to track per-band usage by subdomain |

## Cons

| Challenge | Details |
|-----------|---------|
| **Wildcard DNS** | Need `*.corvmc.org` DNS record |
| **Wildcard SSL** | Need wildcard certificate (Let's Encrypt supports this) |
| **Local dev setup** | Developers need Valet or `/etc/hosts` entries |
| **Session cookies** | Must configure `.corvmc.org` cookie domain |
| **Cross-panel links** | Links from member panel to band panel need full URLs |
| **Known Filament bug** | Tenant switcher dropdown may have URL issues ([#17635](https://github.com/filamentphp/filament/issues/17635)) |

---

## Implementation

### 1. Panel Configuration Change

**File**: `app/Providers/Filament/BandPanelProvider.php`

```php
// Before
->path('band')
->tenant(Band::class, slugAttribute: 'slug')

// After
->path('/')
->tenant(Band::class, slugAttribute: 'slug')
->tenantDomain('{tenant:slug}.' . config('app.band_domain', 'corvmc.org'))
```

### 2. Add Config Values

**File**: `config/app.php`

```php
'band_domain' => env('BAND_DOMAIN', 'corvmc.org'),
```

**File**: `.env`

```
BAND_DOMAIN=corvmc.org
# Local dev:
# BAND_DOMAIN=corvmc.test
```

### 3. Session Cookie Configuration

**File**: `config/session.php`

```php
'domain' => env('SESSION_DOMAIN', '.corvmc.org'),
```

### 4. Infrastructure (External)

- DNS: Add `*.corvmc.org` A record pointing to server
- SSL: Obtain wildcard certificate for `*.corvmc.org`
- Nginx: Ensure server block handles `*.corvmc.org`

### 5. Local Development

For Valet users:
```bash
cd corvmc-redux
valet link corvmc
# Access via myband.corvmc.test
```

For others, add to `/etc/hosts`:
```
127.0.0.1 corvmc.test
127.0.0.1 testband.corvmc.test
```

---

## Files to Modify

1. `app/Providers/Filament/BandPanelProvider.php` - Add tenantDomain()
2. `config/app.php` - Add band_domain config
3. `config/session.php` - Update cookie domain
4. `.env` / `.env.example` - Add BAND_DOMAIN and SESSION_DOMAIN

---

## Verification

1. Create a test band with slug "testband"
2. Visit `testband.corvmc.test/dashboard` (local) or `testband.corvmc.org/dashboard` (prod)
3. Verify authentication persists between main site and band subdomain
4. Test band registration creates new subdomain correctly
5. Test tenant switcher dropdown works (watch for known bug)

---

## Risk Assessment

**Low risk** - Filament natively supports this pattern. Main risks are:
- Infrastructure setup (DNS/SSL) - solvable
- Known tenant switcher bug - may need workaround
- Breaking existing bookmarks - old URLs will 404

## Recommendation

This is a nice-to-have feature that provides real value to bands. However, it requires infrastructure coordination. Consider:
1. Implementing in a feature branch first
2. Testing thoroughly in staging with proper DNS/SSL
3. Communicating URL change to existing band users
