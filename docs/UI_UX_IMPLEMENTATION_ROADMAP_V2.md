# Ladunni UI/UX v2 Implementation Roadmap

Target stable release: **v1.1.0**

The roadmap is deliberately foundation-first to prevent page-by-page rework.

## UX-01 — Design system foundation

Deliverables:

- semantic CSS tokens;
- explicit visual-density rules for a compact-but-comfortable interface;
- six-quality visual gate: modern, responsive, professional, premium, compact, consistent;
- radius/elevation/spacing policy;
- form controls;
- button/icon button;
- field/error/help pattern;
- card/divider/chip/badge;
- dialog/sheet/dropdown/tooltip;
- skeleton/empty/alert/toast;
- breadcrumb/page header/pagination;
- reusable state patterns.

Exit criteria:

- component primitives documented in source;
- no visual regression in current pages before migration;
- accessibility basics covered.

## UX-02 — Public shell

Deliverables:

- redesigned header;
- first-class global search;
- simplified desktop navigation;
- mobile navigation sheet;
- refined footer;
- page container/section spacing system;
- breadcrumb pattern.

Exit criteria:

- shell works from 360–1600 px;
- current routes preserved;
- PWA controls remain accessible.

## UX-03 — Homepage editorial redesign

Deliverables:

- editorial hero;
- hero search;
- real featured-book composition;
- quick discovery;
- shelf section;
- category exploration;
- calm statistics band;
- homepage-builder compatibility.

Exit criteria:

- every existing homepage section type still renders;
- layout remains useful when sections are disabled/reordered.

## UX-04 — Catalog, taxonomy, discovery

Deliverables:

- catalog page header;
- query/result summary;
- active filter chips;
- desktop sticky filter panel;
- mobile filter sheet;
- refined sort/per-page controls;
- new book card;
- taxonomy/directory consistency;
- empty/no-result states.

Exit criteria:

- all current filters remain functional;
- URL query behavior preserved;
- keyboard/mobile acceptance pass.

## UX-05 — Book detail

Deliverables:

- redesigned cover/content hierarchy;
- prominent Read / Continue Reading;
- secondary Download;
- metadata list;
- description treatment;
- related shelf;
- optional sticky mobile primary action.

Exit criteria:

- download/read permission logic unchanged;
- local progress compatibility preserved.

## UX-06 — Reader focus mode

Deliverables:

- desktop topbar simplification;
- mobile reading controls;
- More/settings progressive disclosure;
- progress feedback;
- redesigned drawer/sheet treatment;
- loading/error/retry states;
- orientation QA.

Exit criteria:

- continuous/single/book modes pass;
- zoom/rotation/search/thumbnails/fullscreen/themes pass;
- saved preferences/progress pass.

## UX-07 — Admin shell

Deliverables:

- grouped navigation;
- collapsible desktop sidebar;
- complete mobile navigation sheet;
- topbar/breadcrumb;
- page header/action slots;
- user menu.

Exit criteria:

- every permission-filtered menu remains reachable;
- active navigation works for nested admin routes;
- mobile no longer relies on Profile as the only header action.

## UX-08 — Admin workspace pages

Deliverables:

- dashboard hierarchy;
- ebook list;
- ebook form;
- master data;
- homepage builder;
- settings;
- analytics;
- audit log;
- profile/security;
- consistent tables/forms/filter bars/empty states.

Exit criteria:

- all forms preserve backend validation;
- CRUD/permission flows pass;
- long-content/mobile stress pass.

## UX-09 — Authentication and system states

Deliverables:

- login;
- forgot/reset password;
- public maintenance;
- not found;
- offline/PWA;
- global errors and confirmations.

Exit criteria:

- auth semantics/security unchanged;
- consistent visual language.

## UX-10 — Accessibility, responsive, content stress pass

Deliverables:

- keyboard pass;
- focus audit;
- contrast audit;
- reduced motion;
- 360/390/430/768/1024/1280/1440/1600 pass;
- long/missing content matrix;
- touch ergonomics;
- reader portrait/landscape.

Exit criteria:

- Acceptance Matrix v2 completed.

## UX-11 — GitHub Actions release candidate

Deliverables:

- full PHP tests;
- Pint;
- TS typecheck;
- Vite production build;
- browser smoke;
- release verify/package;
- v1.1.0-rc artifact.

No heavy build/QA is run on production.

## UX-12 — Production deployment and final acceptance

Deliverables:

- verified backup;
- deploy signed/verified artifact;
- migrations if any;
- cache clear;
- health;
- public/admin/reader smoke;
- PWA;
- security header/sensitive path checks;
- rollback snapshot.

Stable release becomes **v1.1.0** only after final acceptance passes.

---

## Change control

During UX-01 through UX-10:

- no unrelated backend feature expansion;
- no one-off production edits;
- no direct main commits;
- each implementation batch uses a focused branch/PR;
- design decisions that affect multiple pages are resolved at token/component level first;
- production remains on v1.0.2 until release acceptance.

This sequence is the execution contract for the UI/UX refinement.
