# Local Resources Rework

## Problem statement

The Local Resources page serves as a directory of businesses and services useful to the Corvallis music community. Two things need fixing: the staff authoring experience has friction (clunky publish workflow, no bulk operations), and the public display wastes space with oversized cards that make the page hard to scan.

Members and visitors use this page to quickly find a studio, shop, or repair service. The current card-grid layout forces them to scroll through large blocks of whitespace to compare options within a category. Staff who maintain the directory hit small annoyances repeatedly — manually entering a datetime to publish, no way to re-categorize or publish several resources at once.

## Goals

1. Public page is scannable at a glance — a visitor can find a resource in a category without scrolling past cards of empty fields.
2. Staff can publish, unpublish, and re-categorize resources in bulk from the list view.
3. The publish workflow uses a simple toggle (draft / publish now / schedule) instead of a raw datetime picker.
4. The page retains its current information architecture (categories with anchor nav, suggestion form at the bottom) — this is a visual/UX pass, not a restructure.

## Non-goals

- **Data model changes.** The current schema (name, description, contact fields, website, address, published_at, sort_order) stays as-is. No new columns, no removed columns.
- **Category restructure.** ResourceList model and its relationship to LocalResource are unchanged.
- **Suggestion form redesign.** The Livewire `ResourceSuggestionForm` component and its notification flow stay as they are.
- **Member/Band panel exposure.** Local Resources remain staff-authored content — no self-service editing by members or bands.
- **Search or filtering on the public page.** The directory is small enough that anchor nav and visual scanning are sufficient for now.

## User stories

**As a visitor**, I want to scan a category and see every resource's name, key contact info, and website in a compact layout so I can compare options without excessive scrolling.

**As a staff admin**, I want to select multiple resources and publish, unpublish, or move them to a different category in one action so I don't have to edit each one individually.

**As a staff admin**, I want a clear draft/published/scheduled toggle when editing a resource so I don't have to think about what "leave empty to save as draft" means or type a datetime by hand.

## Requirements

### P0 — Must have

#### Public display: dense columnar layout

Replace the current 2-column card grid with a compact, tabular layout within each category section. Think "hotel activity card" — information arranged in columns, easy to scan vertically.

Each category section displays its resources in a dense list or table where each row shows: name (linked to website if present), description (truncated or omitted if empty), address, and contact info — all on one or two lines. Contact fields that are empty are simply not rendered (this already happens, but cards still take up vertical space due to padding).

Acceptance criteria:

- Each resource occupies roughly one row of content height, not a full card.
- Empty optional fields (description, address, contact_name, contact_email, contact_phone) don't produce blank space — the row collapses to fit only populated fields.
- The layout remains readable on mobile — rows can stack fields vertically at small breakpoints instead of forcing a horizontal table.
- Category headings, anchor nav, and the overall page structure (hero, nav bar, category sections, suggestion CTA) stay the same.
- Visual hierarchy is clear: category name is the dominant heading, resource names are scannable within it.

#### Authoring: publish workflow improvement

Replace the raw `DateTimePicker` for `published_at` in `ResourceListForm` with a segmented control or radio group: **Draft**, **Publish now**, **Schedule**. Selecting "Schedule" reveals a date/time picker. "Publish now" sets `published_at` to `now()` on save. "Draft" nulls it out.

Acceptance criteria:

- New resources default to "Draft."
- Editing an already-published resource shows "Publish now" as the active state (since `published_at` is in the past).
- Editing a scheduled resource shows "Schedule" with the existing datetime pre-filled.
- The underlying `published_at` column behavior is unchanged — this is purely a form UX improvement.

#### Authoring: bulk actions

Add bulk actions to the staff list table for: **Publish now**, **Unpublish (revert to draft)**, and **Move to category** (with a category select in the bulk action modal).

Acceptance criteria:

- Bulk publish sets `published_at = now()` on all selected resources that are currently drafts or scheduled.
- Bulk unpublish sets `published_at = null` on all selected resources.
- Bulk move updates `resource_list_id` on all selected resources and resets their `sort_order` to append at the end of the target category.
- All three actions show a confirmation count ("Publish 4 resources?") before executing.

### P1 — Nice to have

#### Public display: subtle category styling

Give each category section a slight visual distinction — a left border accent, a tinted background, or an icon. This makes the page feel less like a plain list and more like the "hotel card" aesthetic without adding weight.

#### Authoring: inline editing on list view

Allow editing name, category, and published status directly in the table row (Filament's inline editing) so staff can make quick corrections without opening the full edit form.

### P2 — Future considerations

#### Public display: search/filter

If the directory grows significantly, add a client-side search box that filters across all categories. Not needed now — the directory is small.

#### Resource logos/images

Add an optional image per resource (via Spatie Media Library). Would require a schema change and complicates the dense layout, so deferring.

## Success metrics

This is an internal-facing improvement for a small nonprofit. Formal metrics aren't practical, but the rework succeeds if:

- Staff who maintain the directory report that bulk operations and the publish toggle save them time.
- The public page fits more resources on screen without scrolling (rough target: 2–3x as many visible resources per viewport compared to the card layout).
- No regressions in the existing test suite (`PublicLocalResourcesTest`).

## Open questions

- **Design: exact dense layout format** — Should this be a literal `<table>`, a CSS grid with defined columns, or a styled definition list? The "hotel card" reference suggests columns with clear alignment, but the best implementation depends on how many contact fields are typically populated. *Owner: Devon (design decision).*
- **Mobile breakpoint behavior** — At what point do the columns stack? Should mobile show a simplified card-per-resource, or keep the dense list with fields wrapping? *Owner: Devon.*

## Affected files

These are the files that will need changes:

- `resources/views/public/local-resources.blade.php` — public display template
- `app/Filament/Staff/Resources/LocalResources/Schemas/ResourceListForm.php` — publish workflow
- `app/Filament/Staff/Resources/LocalResources/Tables/ResourceListsTable.php` — bulk actions
- `tests/Feature/LocalResources/PublicLocalResourcesTest.php` — update assertions for new markup
