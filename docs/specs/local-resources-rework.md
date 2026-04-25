# Local Resources Rework

The Local Resources page is a public directory of businesses and services useful to the Corvallis music community. This rework replaces the oversized card-grid display with a dense, scannable list layout, moves staff authoring into modals with a cleaner publish workflow, and adds bulk operations. No schema changes — the existing `local_resources` and `resource_lists` tables stay as-is.

---

## Why this rework

Two problems, both friction:

**Public display is bloated.** Each resource renders as a full card in a 2-column grid. Cards have fixed padding regardless of how many fields are populated, so a resource with just a name and website takes as much vertical space as one with every field filled. Visitors scanning for a rehearsal space or repair shop have to scroll through a lot of whitespace. The layout looks generic rather than curated.

**Staff authoring has unnecessary friction.** Publishing requires typing into a raw datetime picker, or leaving it blank for "draft" (which isn't obvious). There are no bulk operations — re-categorizing or publishing several resources means editing each one individually. Creating and editing navigate to separate pages, losing context of the list.

---

## The "note" field

The existing `description` column gets reframed as a "note" in the UI — short supplementary context, not a paragraph. Example: a recurring jam listing might have the note "Third Thursday at Common Fields." The column name stays `description` in the database; only the label changes in the form and display.

---

## Domain model

No changes. The existing models stay as-is:

### LocalResource
The directory entry. Key fields: `name`, `description` (displayed as "note"), `website`, `address`, `contact_name`, `contact_email`, `contact_phone`, `published_at`, `sort_order`, `resource_list_id`.

### ResourceList
The category grouping. Key fields: `name`, `slug`, `description`, `display_order`.

A LocalResource belongs to a ResourceList. ResourceList has many LocalResources. The `published_at` field controls visibility: null = draft, future datetime = scheduled, past datetime = published.

---

## Public browsing flow

1. Visitor lands on `/local-resources`. The page loads all ResourceLists that have published resources, eager-loaded and ordered by `display_order`.

2. **Sticky anchor nav** at the top lists category names (same as today). Clicking one smooth-scrolls to that section.

3. Each **category section** has the category name as an `<h2>`, optional category description below it, then a dense list of resources.

4. Each **resource row** shows:
   - **Name** — linked to `website` if present (opens in new tab), plain text otherwise.
   - **Note** — the `description` field, rendered as a secondary line under the name. Only shown if populated.
   - **Address** — right-aligned or in a second column. Only shown if populated.

   Contact fields (`contact_name`, `contact_email`, `contact_phone`) are hidden from the public display. The columns remain in the database but are not rendered. The label "Contact" was ambiguous — it wasn't clear whether it referred to the business or a person at the collective — and for a directory listing, the website and address are sufficient.

5. **Suggestion form** at the bottom stays exactly as-is — the `ResourceSuggestionForm` Livewire component and its notification flow are untouched.

### Category border accent

Each category section has a colored left border for visual distinction. Colors are auto-assigned from a fixed palette of 6–8 muted tones, cycling by loop index. No staff configuration, no schema change. Adjacent categories will get different colors as long as the palette is larger than 2.

### Layout structure (desktop)

```
  ┌──────────────────────────────────────────────────┐
  │  Category Name                                   │
▌ │  Optional category description                   │
▌ ├──────────────────────────────────────────────────┤
▌ │  Resource Name ↗    Note text here       123 Main St │
▌ │  Another Place ↗                         456 Oak Ave │
  │  Third Resource     Every other Friday               │
  └──────────────────────────────────────────────────┘
```

The left border (`▌`) is a thick `border-left` on the category section container, colored per-category from the palette.

Each row is roughly one line of content height. Empty fields don't produce blank space — the row collapses around what's populated.

### Layout structure (mobile)

On small screens, each resource stacks vertically within a compact block:

```
Resource Name ↗
  Every other Friday
  123 Main St
```

Still significantly denser than the current card layout.

### Implementation approach

Use a CSS grid or flex layout — not a `<table>`. The number of populated fields varies per row, so a rigid table would create awkward empty cells. A flex row with consistent spacing handles sparse data more gracefully and is easier to make responsive.

---

## Staff authoring flow

### Modal-based create/edit

Replace the separate Create and Edit pages with modal actions on the list page. Staff stays in context of the list while creating or editing resources.

**Create:** A header action on the list page opens a modal with the resource form. On save, the new resource appears in the list.

**Edit:** Clicking the edit action on a row opens the same form in a modal, pre-filled with the resource's data.

The `CreateResourceList` and `EditResourceList` page classes are removed. The `ResourceListResource::getPages()` method returns only the `index` route.

### Form schema

The form stays in `ResourceListForm` with these changes:

**Publish toggle.** Replace the raw `DateTimePicker` for `published_at` with a `ToggleButtons` (or `Radio`) component with three options: **Draft**, **Publish now**, **Schedule**. Selecting "Schedule" reveals a `DateTimePicker`. The toggle is a virtual field — the `mutateFormDataBeforeCreate` and `mutateFormDataBeforeSave` hooks translate it to the actual `published_at` value:

- Draft → `published_at = null`
- Publish now → `published_at = now()`
- Schedule → `published_at = <selected datetime>`

When editing, the initial state is derived from the current `published_at` value:
- null → Draft
- past → Publish now
- future → Schedule (with the datetime pre-filled)

**Description label.** The `description` Textarea label changes to "Note" with helper text: "Short context shown on the public page (e.g., 'Third Thursday at Common Fields')."

**Category select.** Already has `->searchable()->preload()` and inline creation. No changes needed.

### Bulk actions

Three new bulk actions added to `ResourceListsTable`:

**Publish now.** Sets `published_at = now()` on all selected resources. Confirmation modal shows count: "Publish 4 resources?"

**Unpublish.** Sets `published_at = null` on all selected resources. Confirmation modal: "Unpublish 3 resources? They will be saved as drafts."

**Move to category.** Modal with a `Select` for the target ResourceList (searchable, preloaded). On confirm, updates `resource_list_id` on all selected resources and sets their `sort_order` to `max(sort_order) + 1` within the target category (appended at end). Confirmation: "Move 5 resources to [Category Name]?"

All three are added to the existing `BulkActionGroup`.

### Category editing

Stays as-is — the modal from the group header that edits category name and display_order.

---

## Module boundaries

This feature doesn't touch any module — LocalResource and ResourceList live in `app/Models/` (integration layer), not in an app-module. The Filament resource lives in `app/Filament/Staff/`. The public view is a plain Blade template served by a route closure in `routes/web.php`. No cross-module communication is involved.

---

## Schema

No migrations. The existing tables are unchanged:

### local_resources
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| resource_list_id | bigint FK nullable | → resource_lists.id |
| name | varchar(255) | |
| description | text nullable | Displayed as "Note" in UI |
| contact_name | varchar(255) nullable | Hidden from UI (column retained) |
| contact_email | varchar(255) nullable | Hidden from UI (column retained) |
| contact_phone | varchar(255) nullable | Hidden from UI (column retained) |
| website | varchar(255) nullable | |
| address | varchar(255) nullable | |
| published_at | timestamp nullable | null=draft, past=published, future=scheduled |
| sort_order | integer default 0 | |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp nullable | Soft deletes |

### resource_lists
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | varchar(255) | |
| slug | varchar(255) unique | Auto-generated via HasSlug |
| description | text nullable | Category description |
| display_order | integer default 0 | |
| created_at | timestamp | |
| updated_at | timestamp | |
| deleted_at | timestamp nullable | Soft deletes |

---

## Staff UI summary

**Location:** Staff panel → Content → Local Resources (`/staff/local-resources`)

### List page

| Element | Behavior |
|---|---|
| Header action: Create | Opens modal form |
| Row action: Edit | Opens modal form for that resource |
| Row actions: Move up/down | Same as today — reorder within category |
| Bulk: Publish now | Sets published_at = now() |
| Bulk: Unpublish | Sets published_at = null |
| Bulk: Move to category | Select target category, moves + resets sort_order |
| Group header: Edit | Modal to edit category name/display_order (unchanged) |
| Filters | Status (Published/Draft/Scheduled), Trashed (unchanged) |
| Grouping | By category with display_order sort (unchanged) |

### Form fields (in modal)

| Field | Type | Notes |
|---|---|---|
| Name | TextInput | Required |
| Category | Select (relationship) | Searchable, preloaded, inline creation |
| Website | TextInput (url) | |
| Note | Textarea (2 rows) | Label "Note", was "Description" |
| Address | TextInput | |
| Sort Order | TextInput (numeric) | |
| Publish status | ToggleButtons | Draft / Publish now / Schedule |
| Publish date | DateTimePicker | Visible only when "Schedule" selected |

---

## Permissions

No changes. The existing `LocalResourcePolicy` stays as-is:
- `viewAny`, `view`: public (allows null user)
- `create`, `update`, `delete`, `restore`: admin role only
- `forceDelete`: never

---

## Notifications

No changes. The `ResourceSuggestionNotification` (sent when a visitor submits the suggestion form) stays as-is.

---

## What changes

| Area | Change |
|---|---|
| `resources/views/public/local-resources.blade.php` | Replace card grid with dense list layout, remove contact fields from display |
| `app/Filament/Staff/Resources/LocalResources/Schemas/ResourceListForm.php` | Add publish toggle, rename description → note label, remove contact fields from form |
| `app/Filament/Staff/Resources/LocalResources/Tables/ResourceListsTable.php` | Add bulk publish/unpublish/move actions |
| `app/Filament/Staff/Resources/LocalResources/ResourceListResource.php` | Remove create/edit page routes, add modal create/edit actions |
| `app/Filament/Staff/Resources/LocalResources/Pages/CreateResourceList.php` | Delete — replaced by modal create |
| `app/Filament/Staff/Resources/LocalResources/Pages/EditResourceList.php` | Delete — replaced by modal edit |
| `app/Filament/Staff/Resources/LocalResources/RelationManagers/ResourcesRelationManager.php` | Delete — unused (not in `getRelations()`), has a stale duplicate form |
| `app/Filament/Staff/Resources/LocalResources/Pages/ListResourceLists.php` | Add create/edit modal action registration |
| `tests/Feature/LocalResources/PublicLocalResourcesTest.php` | Update assertions for new markup structure |

## What doesn't change

| Area | Notes |
|---|---|
| `local_resources` migration/schema | No columns added, removed, or renamed |
| `resource_lists` migration/schema | No changes |
| `app/Models/LocalResource.php` | No changes |
| `app/Models/ResourceList.php` | No changes |
| `app/Policies/LocalResourcePolicy.php` | No changes |
| `app/Livewire/ResourceSuggestionForm.php` | Suggestion form untouched |
| `app/Notifications/ResourceSuggestionNotification.php` | Notification untouched |
| `routes/web.php` route closure | Same query, same route name, same controller logic |
| Category edit modal on group header | Stays as-is |

---

## Deferred

**Client-side search/filter.** If the directory grows past ~50 resources, a search box that filters across categories would help. Not needed at current scale. When picked up: a simple JS filter that hides non-matching rows, no server involvement.

**Resource images/logos.** An optional image per resource via Spatie Media Library. Would require a migration (media library polymorphic), complicate the dense layout, and add authoring friction. Revisit if there's demand.

**Inline table editing.** Editing name, category, and publish status directly in the list table row. Filament supports this but it's additive on top of the modal approach — can layer it in later if staff find the modal too heavy for quick corrections.

**Contact fields.** The `contact_name`, `contact_email`, and `contact_phone` columns are retained in the database but hidden from both the public display and the authoring form. If a clear use case emerges (e.g., "contact person at the collective who can introduce you"), they can be re-exposed with better labeling.

**Category color picker.** Letting staff choose a specific color per category (instead of auto-assigned). Would require a `color` column on `resource_lists` and a color picker in the category edit modal. Not worth the complexity unless the auto-assigned palette proves unsatisfying.

---

## Open questions

None currently blocking. The layout approach (CSS grid/flex, not `<table>`) and interaction pattern (native popover, modal authoring) are settled.
