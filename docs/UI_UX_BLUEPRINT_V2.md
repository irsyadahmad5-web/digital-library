# Ladunni UI/UX Blueprint v2

Status: **Approved design direction — implementation blueprint**

Target release: **v1.1.0**

Baseline production: **v1.0.2**

Scope: presentation, interaction, information hierarchy, accessibility, responsive behavior, and reusable frontend design system. Existing backend/domain behavior, security controls, installer, storage, reader engine, analytics semantics, backup/restore, and public access rules remain intact unless a UI requirement explicitly needs a compatible API addition.

---

## 1. Product intent

Ladunni should feel like a deliberate digital reading product, not a generic admin template with a public catalog attached.

The experience must communicate four qualities:

1. **Editorial** — books, authors, and collections are the visual focus.
2. **Calm** — restrained color, whitespace, minimal visual noise, and predictable interaction.
3. **Fast** — the shortest path from discovery to reading is always obvious.
4. **Trustworthy** — typography, spacing, states, feedback, accessibility, and security-sensitive actions are consistent.

The visual direction is **Modern Editorial Digital Library** with six non-negotiable visual qualities: **modern, responsive, professional, premium, compact, and consistent**.

“Compact” means efficient information density and short interaction paths, not cramped spacing. The interface should avoid oversized headers, excessive empty vertical space, unnecessarily tall cards, and controls that consume more space than their importance requires. Desktop should feel efficient; mobile should remain comfortable and touch-friendly.

This is not an e-commerce storefront, not a school portal, and not a conventional SaaS dashboard. Public pages should resemble a modern library/catalog experience. Admin should feel like a focused workspace. Reader should disappear behind the content.

---

## 2. Current-state findings

The current v1.0.2 frontend is technically sound but visually generic.

### 2.1 Strengths to preserve

- Vue 3 + Inertia + TypeScript structure is already clear.
- Tailwind CSS 4 tokens are centralized in `resources/css/app.css`.
- Plus Jakarta Sans is already the primary UI typeface.
- Public, reader, and admin have separate layout components.
- Public catalog already supports filters, sorting, pagination, search, taxonomy, detail, reading, and download.
- Reader already supports continuous/single/book modes, zoom, rotation, search, thumbnails, fullscreen, themes, saved preference, and reading progress.
- Homepage Builder already allows section ordering/configuration.
- PWA and responsive safe-area handling already exist.
- Accessibility basics such as labels, touch targets, and reduced-motion support are present in multiple areas.

### 2.2 Problems to solve

- Many pages repeat the same `rounded + border + surface + padding` treatment, making unrelated content blocks look equally important.
- Only a small portion of the interface is represented by reusable visual primitives; direct utility composition dominates.
- Public pages lack strong editorial hierarchy.
- Search is functionally available but not visually treated as a primary discovery action.
- Book cards are clean but visually flat and do not create enough catalog identity.
- Book detail metadata is fragmented into many small boxes and reads like a dashboard.
- Admin desktop navigation is one flat level; mobile admin navigation is incomplete.
- Admin KPI cards have nearly equal visual weight and limited operational prioritization.
- Reader functionality is strong but controls compete for attention.
- Spacing, radii, elevation, field treatment, empty states, loading states, and feedback do not yet feel like one coherent system.
- Homepage sections can become a stack of visually similar rectangles.
- Responsive behavior works, but several experiences are scaled-down desktop rather than mobile-first interaction patterns.

---

## 3. Design principles

### 3.1 Content first

Book cover, title, author, description, reading action, and discovery should dominate. Decorative UI must never overpower collection content.

### 3.2 Fewer containers

Not every group needs a card. Use whitespace, typography, divider lines, and section rhythm before introducing a bordered surface.

### 3.3 One primary action

Every screen must have one visually obvious primary task.

Examples:

- Homepage: Search / explore.
- Book detail: Read.
- Reader: Continue reading.
- Ebook admin form: Save.
- Admin dashboard: Understand status / enter current task.

### 3.4 Progressive disclosure

Secondary controls stay available without being permanently visible.

Especially important for:

- Reader tools.
- Advanced catalog filters.
- Destructive admin actions.
- Secondary ebook metadata.

### 3.5 Quiet premium

Premium comes from proportion, rhythm, typography, and restraint. Avoid:

- unnecessary gradients;
- heavy drop shadows;
- excessive badges;
- glassmorphism as a default surface;
- oversized rounded corners everywhere;
- dense decorative iconography.

### 3.6 Mobile is a distinct interaction mode

Mobile is not desktop compressed. Use:

- bottom sheets;
- sticky bottom actions where appropriate;
- larger touch targets;
- reduced simultaneous controls;
- thumb-reachable primary actions;
- shorter navigation depth.

### 3.7 Compact by design, never cramped

The whole interface must maintain a deliberate compact density.

Rules:

- Page headers should be concise and avoid excessive top/bottom padding.
- Cards should only be used when they provide grouping or interaction value.
- Repeated metadata should prefer rows/lists/chips over stacks of large cards.
- Table toolbars, filters, actions, and pagination should align into efficient horizontal groups on desktop.
- Forms should use consistent field heights and grouped sections instead of large vertical gaps.
- Mobile keeps 44 px touch targets even when the visual system is compact.
- Compactness must never reduce legibility, focus visibility, or error clarity.

### 3.8 Consistency is a release requirement

A page-specific visual shortcut is not acceptable if an equivalent global primitive exists. Public, reader, admin, and auth may have different contextual personalities, but they must share the same tokens, spacing logic, control anatomy, focus treatment, feedback patterns, and responsive rules.

---

## 4. Visual identity

### 4.1 Core palette

Public light theme:

| Token | Value | Purpose |
| --- | --- | --- |
| `canvas` | `#F7F6F2` | warm paper background |
| `surface` | `#FFFFFF` | focused content surfaces |
| `surface-subtle` | `#F2F1EC` | quiet secondary blocks |
| `ink` | `#172033` | primary text |
| `ink-soft` | `#667085` | secondary text |
| `line` | `#E6E3DC` | subtle separation |
| `brand` | `#3157D5` | primary action/link |
| `brand-hover` | `#2849B8` | primary hover |
| `brand-soft` | `#EEF2FF` | selected/soft brand background |
| `success` | `#267A55` | success |
| `warning` | `#A76512` | warning |
| `danger` | `#B42318` | destructive/error |

Admin canvas may use `#F6F8FB` while preserving the same ink/brand system.

Reader themes:

- Light: neutral paper around PDF.
- Sepia: warm, low-contrast paper.
- Dark: deep slate, not pure black.

### 4.2 Typography

Primary UI font: **Plus Jakarta Sans**.

Do not introduce another font globally in v2. A serif accent may be evaluated later only for controlled editorial headings; it is not required for v1.1.0.

Type scale:

| Role | Desktop | Mobile | Weight |
| --- | --- | --- | --- |
| Display | 56–64 | 38–44 | 600 |
| H1 | 40–48 | 32–36 | 600 |
| H2 | 28–32 | 24–28 | 600 |
| H3 | 20–22 | 18–20 | 600 |
| Body large | 18 | 17 | 400 |
| Body | 16 | 16 | 400 |
| Small | 14 | 14 | 400/500 |
| Caption | 12 | 12 | 500 |

Rules:

- Main body line-height: 1.6–1.75.
- Long editorial text maximum measure: 68–74 characters.
- Metadata labels use small/caption size; avoid excessive uppercase.
- Book titles may use slightly tighter tracking than body copy.

### 4.3 Radius system

- Controls: 8–10 px.
- Default cards: 12–16 px.
- Large feature panels: 20 px.
- Modal/sheet: 20–24 px.
- Pill only for chips, tags, and intentional status elements.

Avoid applying 24 px radius to every block.

### 4.4 Elevation

Default surfaces should rely on border + background.

Use shadow only for:

- floating toolbar;
- dropdown/popover;
- dialog/sheet;
- actively elevated book cover on hover;
- sticky mobile actions if separation requires it.

### 4.5 Motion

- Micro-interaction: 120–180 ms.
- Surface/dialog transitions: 180–240 ms.
- No decorative long animation.
- Respect `prefers-reduced-motion`.
- Hover movement maximum 2–4 px or subtle scale; no bouncing cards.

---

## 5. Design tokens and CSS architecture

Refactor `resources/css/app.css` into semantic token groups while keeping Tailwind 4 integration.

Required semantic groups:

- canvas/surface/elevated
- foreground/secondary/subtle
- border/strong-border
- brand/brand-hover/brand-soft
- success/warning/danger/info
- focus
- reader canvas/control/paper
- radius
- shadow
- content widths
- header heights
- safe-area values

No page should hard-code a one-off hex color unless it is content-derived.

---

## 6. Component system

The current global UI component set is too small. v2 must introduce reusable primitives before page redesign.

### 6.1 Foundation components

Required:

- `UiButton`
- `UiIconButton`
- `UiInput`
- `UiTextarea`
- `UiSelect`
- `UiCheckbox`
- `UiRadio`
- `UiSwitch`
- `UiField`
- `UiFormMessage`
- `UiCard`
- `UiDivider`
- `UiBadge`
- `UiChip`
- `UiAvatar`
- `UiSkeleton`
- `UiEmptyState`
- `UiAlert`
- `UiToast`
- `UiTooltip`
- `UiDropdownMenu`
- `UiPopover`
- `UiDialog`
- `UiSheet`
- `UiTabs`
- `UiPagination`
- `UiBreadcrumb`
- `UiPageHeader`
- `UiStat`
- `UiTableShell`
- `UiConfirmDialog`

Use Reka UI/shadcn-vue primitives where suitable, but visual output must follow Ladunni tokens rather than default demo styling.

### 6.2 Public components

- `PublicHeader`
- `PublicMobileNav`
- `PublicFooter`
- `GlobalLibrarySearch`
- `BookCover`
- `BookCard`
- `BookRow`
- `BookShelf`
- `SectionHeader`
- `FilterChipBar`
- `CatalogFilterPanel`
- `CatalogFilterSheet`
- `MetadataList`
- `DirectoryCard`
- `ReadingProgressBadge`

### 6.3 Admin components

- `AdminSidebar`
- `AdminMobileSheet`
- `AdminTopbar`
- `AdminBreadcrumb`
- `AdminPageHeader`
- `AdminStatCard`
- `AdminTableToolbar`
- `AdminFilterBar`
- `AdminFormSection`
- `AdminStickyActions`
- `AdminStatusBadge`
- `AdminEmptyState`

### 6.4 Reader components

- `ReaderTopbar`
- `ReaderMobileBar`
- `ReaderProgress`
- `ReaderToolGroup`
- `ReaderMoreMenu`
- `ReaderSettingsSheet`
- `ReaderSearchPanel`
- `ReaderThumbnailPanel`
- `ReaderResumePrompt`
- `ReaderLoadingState`
- `ReaderErrorState`

---

## 7. Public information architecture

Desktop header primary navigation:

- Katalog
- Kategori
- Koleksi
- Penulis
- Tentang

Penerbit remains discoverable through taxonomy/catalog/detail and may live in secondary navigation rather than permanently occupying primary header space.

Global search must be clearly visible on desktop. Search icon-only treatment is not sufficient as the primary discovery affordance.

Mobile navigation:

- logo/name;
- search trigger;
- menu trigger;
- full-height or near-full-height sheet;
- primary sections;
- secondary information/contact links;
- large touch targets.

Header behavior:

- 72–80 px normal state.
- May compact slightly after scroll, without dramatic animation.
- Sticky but visually light.
- Search results or search page must preserve current query context.

---

## 8. Homepage v2

Homepage should tell a visual story rather than render repeated boxed sections.

Recommended hierarchy:

### 8.1 Hero

Two-column desktop, single-column mobile.

Left:

- eyebrow/tagline;
- strong editorial H1;
- concise subtitle;
- large search field;
- optional secondary CTA to catalog.

Right:

- real featured covers/curated collection;
- no decorative fake books;
- use actual content data;
- responsive composition that disappears/reduces gracefully on mobile.

Search placeholder:

> Cari judul, penulis, topik, kategori, atau ISBN…

### 8.2 Quick discovery

A lightweight horizontal row or chip group:

- categories;
- latest;
- popular;
- curated collections.

Do not wrap each item in a large card.

### 8.3 Featured / curated shelf

Book covers become the visual anchor.

Desktop: 5–6 cards depending width.

Tablet: 3–4.

Mobile: 2-column grid or deliberate horizontal shelf depending section purpose.

### 8.4 Latest additions

Compact shelf with clear “Lihat semua”.

### 8.5 Popular books

Use download/reader signals already available in product logic. Presentation should not imply social popularity if data is not present.

### 8.6 Browse by category

Category cards should be simple and typographic. Avoid stock imagery unless curated assets become a managed feature.

### 8.7 Collection statistics

Use as a calm editorial band, not KPI dashboard cards.

Possible values:

- total public ebooks;
- authors;
- categories;
- collections.

### 8.8 Optional institutional block

About/mission/contact teaser near footer, editable through existing settings where possible.

---

## 9. Catalog / library v2

### 9.1 Desktop

Structure:

```
Breadcrumb
Page heading + result count
Large search / active query
Quick filter chips
--------------------------------
Sticky filter rail | Result toolbar
                   | Book grid
                   | Pagination
```

Filter rail:

- category;
- author;
- publisher;
- collection;
- tag;
- year/language if already supported;
- reset action.

Use native selects only where they remain the most efficient control. Common filters should be scannable.

### 9.2 Mobile

Do not render a narrow desktop sidebar.

Use:

- sticky compact toolbar;
- “Filter” button with count badge;
- `UiSheet` from bottom/full-height;
- active filters displayed as removable chips;
- sort separated from filter if that improves thumb usage.

### 9.3 Result feedback

Always show:

- result count;
- current query if present;
- active filters;
- empty state;
- reset/recovery action.

No-result copy should suggest actionable alternatives.

---

## 10. Book card v2

Cover is primary.

Card anatomy:

1. cover;
2. optional category/collection eyebrow;
3. title;
4. author;
5. minimal supporting metadata.

Rules:

- Avoid permanent border around the entire card unless context requires it.
- Cover may use soft shadow and border.
- Use consistent `3:4` frame.
- Placeholder cover should have a branded editorial treatment, not only a centered icon.
- Title line clamp: 2–3 lines depending context.
- Hover: subtle cover elevation/scale and title color.
- Reading progress may appear as a thin progress line or compact label.
- Download/page count should not clutter every card.

---

## 11. Book detail v2

Primary desktop structure:

```
Breadcrumb

Cover          Category / Collection
               Title
               Subtitle
               Authors
               concise key metadata
               [ Baca sekarang ] [ Unduh PDF ]
               reading progress / resume

               Description
               Detailed metadata list
               Tags
```

Changes from v1:

- Remove the grid of many equal metadata cards.
- Present publisher/year/language/pages/ISBN/edition as one structured metadata list.
- Keep “Read” dominant.
- “Download” is secondary.
- If reading progress exists, make “Lanjutkan membaca” context prominent.
- Related books remain a shelf at the bottom.
- Mobile may use sticky bottom primary read action after the main CTA scrolls off-screen, provided it does not interfere with browser/PWA controls.

---

## 12. Reader v2

Reader is the highest-focus environment.

### 12.1 Desktop

Visible by default:

- back;
- book title (truncated);
- current page / total;
- previous/next where relevant;
- zoom;
- fullscreen;
- More.

Move advanced controls into More/settings:

- rotation;
- theme;
- layout mode;
- fit mode;
- reset preferences.

Thumbnails and search remain dedicated drawers/panels.

### 12.2 Mobile

Use two-tier interaction:

Top bar:

- back;
- short title;
- more.

Bottom bar:

- previous;
- page indicator;
- next;
- zoom/fit context where needed.

Advanced tools: bottom sheet.

### 12.3 Reading focus

- Controls auto-hide only when predictable.
- Tap/click canvas can reveal controls.
- Loading state shows progress without layout jump.
- Failed PDF load presents Retry + Back to book.
- Resume prompt should be visually concise.
- Reading progress must persist exactly as v1 behavior.

### 12.4 Gestures

Where technically reliable and accessible:

- horizontal swipe for single/book mode;
- pinch/zoom only if PDF implementation can support it without breaking browser zoom/accessibility;
- do not intercept vertical scroll in continuous mode.

---

## 13. Admin shell v2

### 13.1 Navigation hierarchy

Group navigation:

**Overview**
- Dashboard
- Analytics

**Library**
- Ebook
- Master Data

**Presentation**
- Homepage Builder
- Pengaturan

**System**
- Audit Log
- Profil & Keamanan

Desktop sidebar:

- collapsible;
- icon + label expanded;
- icon + tooltip collapsed;
- active parent/section clearly visible;
- footer user menu.

Mobile:

- proper hamburger/off-canvas sheet;
- all permitted navigation available;
- current page title remains visible;
- profile is not a substitute for navigation.

### 13.2 Topbar

Desktop topbar may include:

- breadcrumb;
- page context;
- optional global quick action;
- user menu.

Avoid duplicating page title if page header already carries it.

---

## 14. Admin dashboard v2

Hierarchy:

1. Page greeting/context.
2. 3–4 primary KPIs.
3. 30-day trend/activity.
4. popular/active content.
5. operational health/storage block.
6. quick actions.

Not all KPI cards should look equally important.

Suggested primary KPI:

- Public ebooks
- Reader opens (30d)
- Downloads (30d)
- Total collection size

Secondary:

- total ebooks;
- all-time opens/downloads;
- storage bytes.

Operational states should surface when unhealthy; privacy explanation belongs in a quieter informational area rather than competing with operational metrics.

---

## 15. Admin ebook management

### 15.1 List

Toolbar:

- search;
- status filter;
- category/filter;
- sort;
- create ebook action.

Table/list should include:

- cover thumbnail;
- title/author;
- status;
- storage/source;
- updated date;
- actions.

Mobile may switch from table to structured cards.

### 15.2 Ebook form

Split long form into logical sections or tabs:

1. Identity
2. Metadata
3. Classification
4. Cover
5. PDF / Storage
6. Publishing

Use:

- persistent page heading;
- clear required fields;
- help text;
- inline validation;
- sticky save action on long forms;
- unsaved-change protection.

Storage/upload processing state must be explicit.

---

## 16. Master data, settings, homepage builder, analytics

### Master data

- consistent list + edit pattern;
- count badges only where useful;
- clear empty states;
- destructive confirmation;
- no ambiguous icon-only destructive actions.

### Settings

- left-side section nav desktop;
- section selector/mobile tabs on small screens;
- grouped fields;
- save status;
- dirty-state indication;
- preserve current server-side validation.

### Homepage Builder

- section cards show type, visibility, order, and compact preview summary;
- drag handle only where drag sorting is actually implemented;
- otherwise explicit move up/down remains acceptable;
- live “View homepage” remains;
- future preview mode can be considered after v1.1.0, not required.

### Analytics

- consistent period selector;
- trend visualization only when data supports it;
- separate overview vs top-content tables;
- preserve privacy-first semantics.

---

## 17. Authentication

Login should be visually branded but restrained.

Desktop:

- editorial brand/context panel;
- focused login panel.

Mobile:

- single focused card/surface;
- logo/site name;
- no unnecessary marketing copy above the form.

Required UX:

- visible processing;
- clear server-side validation;
- show/hide password;
- keyboard-safe input behavior;
- forgot-password flow consistency;
- success state after reset request without leaking account existence.

---

## 18. Responsive specification

Reference widths for QA:

- 360
- 390
- 430
- 768
- 1024
- 1280
- 1440
- 1600

Primary Tailwind breakpoints may remain, but QA is performed at the reference widths.

### Mobile rules

- minimum touch target: 44 × 44 px;
- primary action reachable without precision tapping;
- no horizontal page overflow;
- tables adapt or scroll deliberately;
- sheets/dialogs honor safe-area inset;
- sticky actions never obscure content;
- reader controls remain usable in portrait and landscape.

### Desktop rules

- content width remains configurable;
- long text never stretches to full content width;
- dashboard tables can use wider workspace;
- public editorial content stays visually centered.

---

## 19. UX state matrix

Every interactive module must define the following when applicable:

| State | Required behavior |
| --- | --- |
| Initial | clear hierarchy and actionable next step |
| Hover | subtle, non-essential enhancement |
| Focus | visible keyboard focus |
| Active | immediate interaction feedback |
| Loading | skeleton/progress without large layout shift |
| Processing | prevent accidental duplicate submission |
| Success | concise confirmation |
| Validation error | field-level message + summary when needed |
| Server error | understandable recovery action |
| Empty | explain why empty + suggested action |
| No search result | query/filter recovery |
| Disabled | visually and semantically disabled |
| Destructive | confirmation with exact consequence |
| Offline/PWA | explicit offline state where relevant |
| Permission denied | clear explanation, no broken control |
| Unsaved changes | warn before destructive navigation when feasible |

---

## 20. Accessibility acceptance

Target: WCAG 2.2 AA practical compliance for product UI.

Required:

- semantic heading order;
- keyboard navigation;
- visible focus;
- labels for all inputs;
- icon-only controls with accessible names;
- contrast compliant text/actions;
- error messages associated with inputs;
- reduced motion;
- no color-only status communication;
- modal/sheet focus trap;
- escape closes non-destructive overlays;
- correct `aria-expanded`, `aria-controls`, and dialog semantics;
- reader controls keyboard-operable;
- touch targets 44 px minimum where practical.

---

## 21. Performance rules

UI refinement must not make v1.1.0 heavier without justification.

Rules:

- keep Lucide icons;
- no large UI framework added merely for styling;
- lazy-load book covers below fold;
- preserve responsive image behavior where available;
- minimize font variants;
- avoid JS animation libraries unless native/CSS cannot meet requirement;
- virtualize only where data scale proves it necessary;
- maintain PWA/service-worker behavior;
- use skeletons rather than blocking full-page spinners.

---

## 22. Content stress cases

UI must be tested with:

- title > 120 characters;
- 5+ authors;
- very long publisher name;
- missing author;
- missing cover;
- missing description;
- missing ISBN/year/pages;
- 20+ categories/tags where domain allows it;
- portrait covers with unusual dimensions;
- very large PDF;
- single-page PDF;
- hundreds of pages;
- empty taxonomy;
- zero search results;
- 1 result;
- hundreds of results.

---

## 23. Non-goals for v1.1.0

Do not expand scope into unrelated product features during visual refinement.

Not part of this release unless required for UI correctness:

- accounts for public readers;
- recommendation AI;
- OCR/full-text indexing;
- social features;
- marketplace integrations;
- backend architectural rewrite;
- reader engine rewrite;
- new authentication model;
- new analytics tracking semantics.

---

## 24. Implementation strategy

The redesign must be implemented foundation-first.

Sequence:

1. Design tokens and primitives.
2. Public shell/navigation.
3. Homepage.
4. Catalog/taxonomy.
5. Book detail.
6. Reader.
7. Admin shell.
8. Admin pages/forms/tables.
9. Auth.
10. Responsive/accessibility/state pass.
11. Visual regression/manual UAT.
12. GitHub Actions QA + release candidate.
13. Production deployment only after acceptance.

Do not independently polish pages with one-off styles before the component foundation exists.

---

## 25. Release policy

Target release: **v1.1.0**.

Development happens in branches/PRs.

CPU-heavy work:

- TypeScript check;
- Vite production build;
- PHPUnit;
- Pint;
- browser smoke;
- release package;

must remain on GitHub Actions, not the production server.

Production server receives only a verified release artifact and runs deployment-safe commands.

---

## 26. Definition of done

v1.1.0 UI/UX work is complete only when:

- public, reader, admin, and auth use the same token/component language;
- every primary screen visibly meets the six qualities: modern, responsive, professional, premium, compact, and consistent;
- compact density is achieved without cramped spacing or sub-44 px mobile touch targets;
- no critical page feels like an isolated Tailwind prototype;
- mobile admin has full usable navigation;
- public search is visually first-class;
- catalog filters have desktop and mobile-specific interaction;
- book detail hierarchy prioritizes reading over metadata;
- reader controls use progressive disclosure;
- all major interactive states are covered;
- responsive QA passes at reference widths;
- keyboard/focus/contrast checks pass;
- no backend/security regression;
- PWA behavior remains valid;
- full GitHub Actions QA passes;
- production smoke/health/security acceptance passes.

This blueprint is the design contract for the v1.1.0 refinement cycle.
