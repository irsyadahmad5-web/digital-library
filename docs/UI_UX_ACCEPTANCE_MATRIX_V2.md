# Ladunni UI/UX Acceptance Matrix v2

Target: **v1.1.0**

This matrix converts the UI/UX Blueprint v2 into final acceptance checks. A screen is not considered finished because it “looks good”; it must pass its interaction, responsive, accessibility, and state checks.

## Global

- [ ] Every primary screen is modern, responsive, professional, premium, compact, and consistent.
- [ ] Compact means efficient density, not cramped spacing; mobile touch targets remain at least 44 px where practical.
- [ ] No page uses oversized headers, excessive empty vertical space, or oversized cards without clear hierarchy value.
- [ ] Semantic design tokens are the only default source for color/radius/elevation.
- [ ] Reusable UI primitives are used instead of repeated one-off field/button/card patterns.
- [ ] Plus Jakarta Sans remains the standard UI typeface.
- [ ] Focus is visible for keyboard navigation.
- [ ] Reduced motion is respected.
- [ ] Touch targets are at least 44 px where practical.
- [ ] No horizontal overflow at 360 px.
- [ ] No new runtime dependency is introduced without a documented reason.
- [ ] Loading, empty, error, and success states are visually consistent.
- [ ] Destructive actions use explicit confirmation.
- [ ] Long text and missing metadata do not break layout.

## Public shell

- [ ] Header hierarchy is clear.
- [ ] Search is first-class on desktop and mobile.
- [ ] Primary navigation is reduced to essential items.
- [ ] Mobile navigation exposes every public section.
- [ ] Active page state is obvious but restrained.
- [ ] Footer remains useful without excessive density.
- [ ] Header/footer honor site appearance settings.

## Homepage

- [ ] Hero reads clearly at 360/390/768/1280/1440.
- [ ] Hero uses real content rather than decorative fake-book imagery.
- [ ] Primary search is above the fold.
- [ ] Section hierarchy is varied; page is not a stack of identical cards.
- [ ] “View all” links are predictable.
- [ ] Empty sections do not leave awkward gaps.
- [ ] Homepage Builder ordering remains respected.

## Catalog

- [ ] Result count is always understandable.
- [ ] Query and active filters are visible.
- [ ] Filters are sticky/usable on desktop.
- [ ] Filters use a sheet on mobile.
- [ ] Active filters can be removed individually.
- [ ] Reset filters works.
- [ ] Sort is distinct from filter.
- [ ] No-results state offers recovery.
- [ ] Pagination remains keyboard accessible.
- [ ] Book grid adapts across reference widths.

## Book card

- [ ] Cover is visually dominant.
- [ ] Missing cover has a deliberate branded placeholder.
- [ ] Long title clamps cleanly.
- [ ] Multiple authors do not overflow.
- [ ] Hover enhancement is subtle.
- [ ] Reading progress is understandable when present.
- [ ] Metadata does not clutter the card.

## Book detail

- [ ] Read action is primary.
- [ ] Download is secondary.
- [ ] Resume reading state is obvious when present.
- [ ] Metadata is grouped into a structured list, not many equal cards.
- [ ] Missing optional metadata does not leave holes.
- [ ] Description remains readable at comfortable measure.
- [ ] Related books use standard book card/shelf patterns.
- [ ] Mobile action behavior is comfortable and non-obstructive.

## Reader

- [ ] Core reading controls are immediately available.
- [ ] Advanced controls are progressively disclosed.
- [ ] Desktop controls do not dominate the document.
- [ ] Mobile top/bottom controls are thumb-friendly.
- [ ] Auto-hide is predictable.
- [ ] Tap/activity restores controls.
- [ ] Continuous, single, and book modes remain functional.
- [ ] Search, thumbnails, theme, zoom, rotate, fit, fullscreen remain functional.
- [ ] Resume progress remains compatible with v1 storage.
- [ ] Loading progress is visible.
- [ ] PDF failure has Retry and Back recovery.
- [ ] Portrait and landscape mobile orientations are usable.
- [ ] Reduced motion is honored.

## Admin shell

- [ ] Sidebar navigation is grouped.
- [ ] Desktop sidebar can collapse without losing discoverability.
- [ ] Collapsed items expose accessible tooltips/names.
- [ ] Mobile has complete off-canvas navigation.
- [ ] Current section is obvious.
- [ ] User/account actions are separated from primary work navigation.
- [ ] Breadcrumb/page context is consistent.

## Dashboard

- [ ] KPI hierarchy is intentional.
- [ ] 30-day activity is distinguishable from all-time totals.
- [ ] Operational warnings surface only when meaningful.
- [ ] Quick actions match common admin tasks.
- [ ] Empty/new-install dashboard is useful.

## Ebook list/form

- [ ] List toolbar provides search/filter/create.
- [ ] Table/card representation works at mobile widths.
- [ ] Long titles do not break rows.
- [ ] Status is scannable.
- [ ] Form is divided into logical sections/tabs.
- [ ] Required fields are clear.
- [ ] Inline validation is consistent.
- [ ] Upload/processing state is explicit.
- [ ] Sticky save action does not obscure fields.
- [ ] Unsaved change handling is defined.

## Master data/settings/homepage builder

- [ ] List/edit patterns are consistent.
- [ ] Destructive actions require confirmation.
- [ ] Settings grouping is understandable.
- [ ] Dirty/saved states are visible.
- [ ] Homepage Builder clearly shows enabled/order/type.
- [ ] Admin links to public preview/open site remain available.

## Authentication

- [ ] Login is branded but minimal.
- [ ] Show/hide password is available.
- [ ] Processing prevents duplicate submit.
- [ ] Validation messages are accessible.
- [ ] Forgot/reset password screens share the same design language.
- [ ] Account existence is not leaked by reset feedback.

## PWA and SEO

- [ ] Manifest remains valid.
- [ ] Service worker remains valid.
- [ ] Offline page matches v2 design language.
- [ ] Canonical/SEO metadata is unchanged semantically.
- [ ] Dynamic robots and sitemap remain correct.
- [ ] PWA install/status UI does not obstruct mobile reading/navigation.

## Final QA

- [ ] 360 px
- [ ] 390 px
- [ ] 430 px
- [ ] 768 px
- [ ] 1024 px
- [ ] 1280 px
- [ ] 1440 px
- [ ] 1600 px
- [ ] keyboard-only pass
- [ ] reduced-motion pass
- [ ] long-content stress pass
- [ ] missing-content stress pass
- [ ] GitHub Actions full QA pass
- [ ] production smoke pass
- [ ] production health pass
- [ ] security headers/sensitive-path pass
