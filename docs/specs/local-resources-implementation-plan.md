# Local Resources Rework — Implementation Plan

Sequenced for a solo developer working through it PR by PR. Four epics, no migrations, no model changes. The staff authoring, public display, and seeder epics are independent of each other — any can go first.

---

## Epic 1: Staff authoring — modal form and publish toggle

Consolidates create/edit into modals on the list page, replaces the raw datetime picker with a publish toggle, and cleans up dead code. After this epic, staff can create and edit resources without leaving the list, and publishing is a clear three-way choice.

### 1.1 Convert create/edit to modal actions

**Modify:** `app/Filament/Staff/Resources/LocalResources/Pages/ListResourceLists.php`

The existing `CreateAction::make()` in `getHeaderActions()` already opens a modal by default on list pages — confirm this works with the current form schema and that no custom logic in `CreateResourceList` is lost (it's a vanilla `CreateRecord` page, so nothing to preserve).

Add an `EditAction` to the table's `recordActions` in `ResourceListsTable.php` — Filament's `EditAction` on a table row opens a modal form by default.

**Modify:** `app/Filament/Staff/Resources/LocalResources/ResourceListResource.php`

Remove `'create'` and `'edit'` from `getPages()`, leaving only `'index'`.

**Delete:**
- `app/Filament/Staff/Resources/LocalResources/Pages/CreateResourceList.php`
- `app/Filament/Staff/Resources/LocalResources/Pages/EditResourceList.php`
- `app/Filament/Staff/Resources/LocalResources/RelationManagers/ResourcesRelationManager.php` (unused, stale duplicate form)

**Test:** Manually verify create and edit modals open from the list page, save correctly, and the list refreshes. Existing Filament test patterns (if any) can be extended, but this is primarily a wiring change.

### 1.2 Add publish toggle to form

**Modify:** `app/Filament/Staff/Resources/LocalResources/Schemas/ResourceListForm.php`

Replace the `DateTimePicker::make('published_at')` in the Display Settings section with:

1. A `ToggleButtons` (or `Radio`) virtual field named `publish_status` with options: Draft, Publish now, Schedule. Default: Draft.
2. A `DateTimePicker::make('published_at')` that is `->visible(fn (Get $get) => $get('publish_status') === 'schedule')`.

Add `mutateFormDataUsing` (or `afterStateHydrated` on the toggle) to derive the initial toggle state from the existing `published_at`:
- `null` → Draft
- past → Publish now
- future → Schedule (with datetime pre-filled)

Add `mutateFormDataBeforeCreate` / `mutateFormDataBeforeSave` (on the list page actions) to translate back:
- Draft → `published_at = null`
- Publish now → `published_at = now()`
- Schedule → keep the `published_at` value from the picker

Note: since the form is now rendered in a modal action context (not a page), the mutation hooks live on the `CreateAction` and `EditAction` in `ListResourceLists`, or as `dehydrateStateUsing` on the form field itself.

**Rename** the `description` Textarea label to "Note" with helper text: "Short context shown on the public page (e.g., 'Third Thursday at Common Fields')."

**Remove** the Contact Information section from the form entirely (contact_name, contact_email, contact_phone fields). The columns remain in the database but are hidden from the UI. Existing data is preserved.

**Test (feature):**
- Create a resource with "Draft" → assert `published_at` is null.
- Create a resource with "Publish now" → assert `published_at` is approximately now.
- Create a resource with "Schedule" and a future date → assert `published_at` matches.
- Edit a published resource → assert toggle shows "Publish now."
- Edit a scheduled resource → assert toggle shows "Schedule" with the date pre-filled.

### 1.3 Add bulk actions

**Modify:** `app/Filament/Staff/Resources/LocalResources/Tables/ResourceListsTable.php`

Add three bulk actions inside the existing `BulkActionGroup`:

**BulkPublishAction:** `BulkAction::make('publish')` — sets `published_at = now()` on all selected records. `->requiresConfirmation()` with a dynamic label showing the count.

**BulkUnpublishAction:** `BulkAction::make('unpublish')` — sets `published_at = null` on all selected records. Requires confirmation.

**BulkMoveAction:** `BulkAction::make('moveToCategory')` — form with a `Select` for ResourceList (searchable, preloaded). On action: updates `resource_list_id` on all selected records, sets `sort_order` to `LocalResource::where('resource_list_id', $targetId)->max('sort_order') + 1` for each (or a sequential offset so they don't all get the same value).

**Test (feature):**
- Select 3 draft resources, bulk publish → all have `published_at` set.
- Select 2 published resources, bulk unpublish → all have `published_at = null`.
- Select 2 resources in Category A, bulk move to Category B → both have `resource_list_id` of B, `sort_order` appended after existing B resources.

---

## Epic 2: Public display — dense list layout

Replaces the card grid with the dense columnar layout and adds category border accents. Contact fields are removed from the display entirely. After this epic, the public page is scannable and compact.

### 2.1 Rewrite the public template

**Modify:** `resources/views/public/local-resources.blade.php`

Keep the existing page structure: hero section, sticky anchor nav, category sections, suggestion CTA. Replace the inner card grid with a dense list layout.

**Category section:** Add a thick `border-left` with a color from a cycling palette. Define the palette as a Blade array at the top of the template (or in the route closure):

```php
@php $palette = ['#6366f1', '#0891b2', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777', '#0d9488']; @endphp
```

Each category section gets `border-left: 4px solid {{ $palette[$loop->index % count($palette)] }}` via inline style.

**Resource row (desktop):** A flex row containing:
- Left: name (as `<a>` if website exists, with external-link icon), with note as a `<small>` or secondary line below if populated.
- Right: address (if populated).

Contact fields (`contact_name`, `contact_email`, `contact_phone`) are not rendered. Remove all contact-related markup from the template.

**Mobile:** At small breakpoints, the flex row stacks — name/note on top, address below. Each resource still significantly more compact than the current card.

**Empty states:** Keep the per-category "No resources listed yet" and the global "No Resources Available" empty state.

### 2.2 Update public display tests

**Modify:** `tests/Feature/LocalResources/PublicLocalResourcesTest.php`

The test "displays resource contact information" currently asserts `contact_name`, `contact_email`, and `contact_phone` are visible on the page. Update this test: rename it to something like "displays resource details" and remove assertions for contact fields. Keep assertions for name, address, and website.

Add a test for the category border accent: given two categories, assert both sections have `border-left` styles with different colors.

**Test (feature):**
- All existing tests in `PublicLocalResourcesTest` still pass (with the contact test updated).
- New: category sections have colored left borders.
- Contact fields are NOT rendered even when populated on the model.

---

## Epic 3: Update seeder for local development

The existing `LocalResourcesSeeder` has realistic Corvallis data but needs updates: it references a `published_at` column on `ResourceList` that was removed in a later migration, it's not registered in `DatabaseSeeder`, and it doesn't exercise the layout edge cases the rework introduces.

### 3.1 Update LocalResourcesSeeder

**Modify:** `database/seeders/LocalResourcesSeeder.php`

Fix the broken `published_at` references on `ResourceList::create()` calls (remove them — that column no longer exists on the table).

Expand the data to exercise the dense layout:

**Vary field population across resources:**
- Some with all display fields (name, website, address, note)
- Some sparse — name + website only, or name + address only, or name only
- A few with notes in the new short-context style: "Third Thursday at Common Fields", "By appointment only", "Seasonal — May through September"

**Vary category sizes:**
- One category with 8–10 resources (tests that the dense list handles longer lists)
- One category with just 1 resource
- Keep the existing 3–5 resource categories

**Mix publish states:**
- Most published (as today)
- 2–3 drafts spread across categories (visible in staff panel, hidden on public page)
- 1–2 scheduled for a future date (tests the "Scheduled" badge in staff panel)

**Add a "Local Jams & Open Mics" category** with note-heavy resources to verify the note display:
- "Corvallis Open Mic" — note: "Every Wednesday at Bombs Away Café"
- "Old World Deli Jam" — note: "First and third Sundays, all instruments welcome"
- "Common Fields Bluegrass Jam" — note: "Third Thursday, 7–9pm"

### 3.2 Register in DatabaseSeeder

**Modify:** `database/seeders/DatabaseSeeder.php`

Add `LocalResourcesSeeder::class` to the `$this->call()` array.

---

## Epic 4: Verify and clean up

### 4.1 Full test suite pass

Run `composer test` and confirm no regressions. The main risk areas are:
- The public display tests, if any assertions depended on specific card CSS classes that were removed.
- Any Filament tests that navigated to the now-deleted create/edit pages.

Grep for references to `CreateResourceList` and `EditResourceList` across the test suite and app code to confirm nothing still references them.

### 4.2 Run Pint

Run `vendor/bin/pint` on all modified files.

---

## Dependency graph

```
Epic 1 (staff authoring)     Epic 2 (public display)     Epic 3 (seeder)
  1.1 modal conversion         2.1 template rewrite        3.1 update data
    └─▶ 1.2 publish toggle      └─▶ 2.2 test updates      3.2 register
          └─▶ 1.3 bulk actions

              All three ──▶ Epic 4 (verify + clean up)
```

Epics 1, 2, and 3 are independent — they can be done in any order or in parallel.

---

## Smoke tests

**Staff workflow:** Create a new resource via the modal with "Schedule" publish status and a future date. Verify it appears as "Scheduled" in the list. Select it and two other draft resources, bulk publish them. Verify all three show "Published." Select one, bulk move to a different category. Verify it appears in the new category at the end of the sort order.

**Public display:** Load `/local-resources` with at least two categories, each with 3+ published resources. Verify: categories have distinct colored left borders, resources display as dense rows (name, note, address — no contact fields), empty fields produce no blank space, anchor nav scrolls to the right section, suggestion form at the bottom still works.

---

## Out of scope for this plan

- **Client-side search/filter** — revisit if directory exceeds ~50 resources.
- **Resource images/logos** — requires migration + media library integration.
- **Inline table editing** — additive layer on top of modal approach.
- **Category color picker** — auto-assigned palette for now.
- **Contact fields** — columns retained in DB but hidden from UI. Re-expose with better labeling if a use case emerges.
