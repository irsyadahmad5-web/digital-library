# Ladunni Design System v2

Target: v1.1.0

This document is the implementation companion to `UI_UX_BLUEPRINT_V2.md`.

## Visual gate

Every migrated screen must be:

- modern;
- responsive;
- professional;
- premium;
- compact;
- consistent.

Compact means efficient, not cramped. Mobile touch targets remain at least 44 px where practical.

## Semantic tokens

Use semantic tokens from `resources/css/app.css` instead of one-off colors.

Primary surface tokens:

- `canvas`
- `surface`
- `surface-subtle`
- `surface-elevated`
- `ink`
- `ink-soft`
- `ink-faint`
- `line`
- `line-strong`
- `brand`
- `brand-soft`
- `success`, `warning`, `danger`, `info`

Legacy `background`, `foreground`, `muted`, `border`, and `primary` aliases remain temporarily so v1 pages can migrate without a flag-day rewrite.

## Density

- Small control: 36 px.
- Default desktop control: 40 px.
- Large/primary control: 44 px.
- Coarse-pointer/mobile icon actions: 44 px.
- Default card radius: 14 px.
- Feature/modal radius: 20–24 px only when hierarchy warrants it.
- Page gutters use `--page-gutter`.
- Long text uses `--reading-measure`.

## Foundation components

Located under `resources/js/components/ui/`:

- button / icon-button
- input / textarea / select
- checkbox / radio / switch
- field / form-message
- card / divider
- badge / chip
- alert / toast
- skeleton / empty-state
- page-header / breadcrumb
- stat / table-shell
- dialog / sheet
- tooltip / popover / dropdown-menu
- tabs / pagination
- confirm-dialog

Do not duplicate these patterns directly inside page components once a screen is migrated.

## Focus and accessibility

- Use visible focus rings.
- Icon-only actions require an accessible label.
- Error text uses `role="alert"` where appropriate.
- Dialogs/sheets rely on Reka UI focus management.
- Reduced-motion rules are global.
- Status must never be communicated by color alone.

## Migration policy

UX-01 establishes the foundation. Existing pages are intentionally not mass-restyled in this stage. UX-02 onward migrates screens in controlled batches so review can detect regressions and consistency drift.
