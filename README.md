# Better Portal 0.1.0

Better Portal is a flexible page builder and portal system for SMF 2.1.x. It supports multiple pages, row/column layouts, audience controls, Forum Mode, interactive forum blocks, and optional integrations with Better Gallery, Better Chat, Better Downloads, and Better Reactions.

## Stable release highlights

- Multiple independently configured Portal pages with selectable Home page.
- Drag-and-drop rows and blocks with responsive one-, two-, and three-column layouts.
- Page navigation ordering and audience controls.
- Forum Mode and interactive board/topic experiences while preserving SMF permissions.
- Preset blocks for forum content and BetterSMF integrations.
- Better Gallery album/browser blocks with native access checks and lightbox support.
- Better Downloads cards/browser with native permission, validation, storage, and submission APIs.
- Better Chat and Better Reactions integration when those MODs are installed.
- Theme Lab-aware presentation with safe fallbacks for other themes.
- Normal uninstall preserves Portal pages/layouts/settings; Package Manager Remove data performs destructive cleanup.
- Direct upgrade support from all listed 0.1.0 development builds through dev32q.

## Development history

# Better Portal 0.1.0-dev32q — Theme-Aware Better Downloads Cards

- Removes the hard-coded translucent white surfaces from Better Downloads cards rendered inside Portal blocks.
- Portal download cards, covers, badges, categories, filters, browser details, empty states, and submission panels now consume Theme Lab's published `--tl-*` palette variables when available.
- Non-Theme-Lab themes fall back to inherited/transparent surfaces instead of assuming a light theme.
- No Better Downloads data/provider behavior changed; this is a Portal presentation-layer fix.

## 0.1.0-dev32p

- Adds direct page/menu ordering to Better Portal > Pages.
- Drag non-Home page rows with the new grab handle to reorder them.
- Adds Up/Down arrow controls as a precise fallback.
- Rewrites menu order values into clean 10-point increments after each move.
- The Home page remains pinned at the top; hidden pages can still be positioned so their order is ready if they are later shown in navigation.
- Drag saves through SMF session/token validation and returns with a clean GET refresh.
- No database schema change is required.

## 0.1.0-dev32o

- Fixed Page Builder drag-and-drop refreshes re-submitting the previous POST request and triggering SMF token verification errors.
- Successful cross-column block moves and row reorders now return to the Page Builder with a clean GET navigation.
- Failed AJAX reorder recovery also returns with a clean GET instead of replaying a stale form/token.

## 0.1.0-dev32n

- Polishes the **Selected Gallery Album** block for landing-page screenshot showcases.
- Displays one larger screenshot card per row instead of a multi-column thumbnail grid.
- Adds a clean white card, border, padding, rounded corners, subtle shadow, and restrained hover treatment.
- Enlarges screenshot thumbnails for better readability in narrow Portal side columns while keeping the existing Better Gallery lightbox behavior.
- No database schema changes.

## 0.1.0-dev32m

- Adds a new **Selected Gallery Album** Better Gallery block for landing pages and screenshot showcases.
- Administrators can choose one accessible Better Gallery album and the number of items to display.
- Renders a compact responsive image grid that works in narrow or wide Portal columns.
- Clicking an item opens Better Gallery's existing native lightbox/viewer.
- Includes a **View Full Album** action and continues to respect Better Gallery's own album/media access rules.
- No database schema changes.

## 0.1.0-dev32l

- Enlarges the visible Portal main-menu icon artwork while preserving SMF's native 16px menu slot and text alignment.
- Uses a cache-busted main-menu icon filename so the size change appears immediately.
- No Portal behavior or admin icon changes.

## 0.1.0-dev32k

- Forces current Better Gallery wording even when a generated theme has an older Better Portal language snapshot.
- Migrates only exact legacy Better Media default block titles to Better Gallery titles; custom titles are preserved.

# Better Portal 0.1.0-dev32i — Recent Time Markup Fix

## dev32i — Recent Topics / Posts Time Markup Fix

- Fixes SMF formatted dates such as `<strong>Today</strong>` being printed literally in the Latest Topics and Latest Forum Posts blocks.
- User-controlled subject/poster text remains escaped; only SMF's already-formatted time output is rendered as markup.
- Adds direct package-manager upgrade support from dev32h.


This is a focused Forum Mode / Board Shell polish pass.

## Fix

On boards that contain child boards, SMF's native MessageIndex template renders:

- a `Sub-Boards` `.cat_bar`
- immediately followed by the first child-board `.up_contain` row.

Under the active Theme Lab theme, that first row was retaining standalone top
spacing and upper corner rounding, leaving a visible white strip between the
blue Sub-Boards header and the child-board content cell.

dev32h scopes the correction specifically to child-board containers inside
Better Portal's Board Shell:

- removes the Sub-Boards header bottom separation,
- removes top margin from only the first child-board row,
- removes the first row's upper corner radii so it joins the header cleanly,
- leaves normal board-index, topic-list, and non-Portal SMF layouts untouched.

No database changes.
No icon changes.
No behavior changes outside the Portal board shell.

# Better Portal 0.1.0-dev32g — Cache-Busted Portal Icon Fix

This pass uses the exact Portal/layout icon supplied by the user, crops its transparent padding for SMF's 16px/32px slots, and changes the actual icon filenames referenced by Better Portal. The new filenames deliberately bypass any browser/SMF cache that could keep showing the former cloud/download icon in Configuration.

It also repairs the upgrade path so dev32d, dev32e, and dev32f can all upgrade directly to dev32g.

# Better Portal 0.1.0-dev32f — Downloads Icon Admin Pass

This build is based directly on the supplied dev32c package. It changes only the Better Portal admin/configuration icon to the selected glossy blue Downloads cloud icon, using native SMF 16px and 32px admin icon slots. Portal behavior is otherwise unchanged.

# Better Portal 0.1.0-dev32f — Downloads Browser Action Icon Outline Polish

- Removes Add Download / Back to Downloads controls from the blue block header.
- Places contextual actions in the upper-right of the block body instead.
- Uses compact icon-only controls: + for Add Download and a left arrow for Back.
- Adds `title` and `aria-label` text so the icons remain understandable and accessible.
- Existing in-block browser, details, gallery, filtering and submission behavior is unchanged.
- No database schema change.

# Better Portal 0.1.0-dev32 — Interactive Downloads Browser Polish + In-Block Submission

Polishes the Downloads Browser and adds permission-aware in-block submissions through Better Downloads dev8. Category filtering, details/gallery browsing, direct downloads and submission now form one Portal experience. Better Downloads remains authoritative for permissions, validation, storage and approval.

# Better Portal 0.1.0-dev30b — Better Downloads Responsive Card Layouts

## 0.1.0-dev30

- Adds Better Downloads integration when Better Downloads is installed and enabled.
- New blocks: Latest Downloads, Recently Updated, Most Downloaded, Download Categories, Featured Download, and Better Downloads Browser.
- Featured Download can select a live approved download and shows its cover, version, compatibility, description and direct actions.
- Better Downloads Browser provides live category filtering inside the Portal block and direct Details/Download actions.
- Better Downloads remains authoritative for visibility, category access and download permission; Better Portal does not read or write Better Downloads tables directly.
- All Better Downloads blocks disappear safely from the picker if Better Downloads is unavailable or disabled.
- No database schema change is required.


## dev30b
- Featured Download is now column-aware: horizontal showcase in a wide/main column, stacked image-first card in narrow rails.
- Stacked mode preserves the full title, metadata, description, Details, and Download actions instead of squeezing text beside the cover.


## dev30b
- Applies stacked narrow-column behavior to all Better Downloads card-based blocks, including Latest Downloads, Recently Updated, Most Downloaded, Featured Download, and Downloads Browser cards.
- Wide/main columns keep the richer horizontal card layout.
- Narrow rails preserve all content but stack cover, metadata, description (where applicable), and actions vertically.
- Download Categories remains naturally compact and unchanged.


## dev31 — Interactive Downloads Browser Completion
- Completes Better Downloads Browser as a mini Downloads app inside a Portal block.
- Category buttons filter cards in place without leaving Portal.
- Clicking a card image/title/Details opens a full in-block details view with description, release metadata, changelog, support link, direct download action, and standalone-page fallback.
- Screenshot galleries render inside the details view; thumbnails switch the hero screenshot in place.
- A Back to Downloads control appears in the block header while viewing details and returns to the preserved browser list/filter state.
- Direct Download continues to use Better Downloads' native download action, so permission checks and download counters remain authoritative there.
- Uses Better Downloads dev7's read-only integration APIs; with an older provider installed, the browser safely falls back to normal standalone Details links.
- Narrow columns retain the stacked card behavior from dev30b; detail layouts also collapse naturally on small screens.
- No Better Portal database schema change is required.


## dev32c
- Added a subtle circular outline around the Better Downloads Browser body action icons at rest.
- Hover/focus keeps the existing filled-circle interaction, with no layout shift.
- Applies to both Add Download and Back to Downloads controls.
