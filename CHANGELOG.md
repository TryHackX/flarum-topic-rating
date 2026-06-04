# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.2.0] - 2026-06-03

> Big feature release: pick **how the rating looks on the discussion list**
> (six per-device display styles), make the compact single star **clickable**
> with an optional **rate-from-the-modal** control, and manage the author
> **avatar** from a new **shared** Desktop/Mobile section that stays in sync
> with `flarum-thumb-sliders`. Also tightens **per-tag control** — standalone
> secondary tags, tagless discussions, hiding empty widgets from non-voters, and
> hiding "frozen" ratings on topics that became non-rateable. Plus a batch of
> ratings-modal fixes. No breaking changes, and **no new migrations in this
> extension** — new keys default safely on their own.
>
> ⚠️ **Behaviour note:** discussions tagged with **only a secondary tag (no
> primary)** now default to **not** rateable (previously they were always
> rateable). Re-enable specific ones under *Secondary tags used on their own*.

### Added
- **Per-device discussion-list rating styles.** New settings *Display style —
  desktop* (`list_style_desktop`) and *Display style — mobile*
  (`list_style_mobile`), each one of six styles:
  - *Full stars — in place* (the original meta column, above views / replies —
    **default, unchanged**);
  - *One star + score* (e.g. `★4.6`) *— in place*;
  - *One star (empty / half / full by score) + score — in place*;
  - *Full stars — after the title*;
  - *One star + score — after the title*;
  - *One star (empty / half / full) + score — after the title*.
  Graded-star buckets use the 0–10 scale: empty `0–3.33`, half `3.33–6.67`,
  full `>6.67` (10 = 5.0). Compact and after-title styles are **display-only**.
  Serialized as `tryhackxTopicRatingListStyleDesktop` / `…Mobile`.
- **Clickable single star + in-modal rating** (cascading admin toggles, shown
  only when a single-star style is selected on either device):
  - *Make the single star clickable* (`single_clickable`, default **on**) —
    the whole star + number opens the ratings modal (even after the title,
    where the click is captured so it doesn't follow the title link). When
    **off**, the star is display-only and topics with no rating yet show
    nothing at all.
  - *Allow rating inside the modal* (`single_modal_rate`, default **off**) —
    adds an interactive, centred rating control at the top of the ratings
    modal so users can rate (or change their rating) without leaving the list.
  Serialized as `tryhackxTopicRatingSingleClickable` /
  `tryhackxTopicRatingSingleModalRate`.
- **Shared avatar section — Desktop & Mobile** (also present in the
  `flarum-thumb-sliders` admin; same underlying setting). Per device choose
  *Show avatar* / *Replace with thumbnail when the topic has an image* /
  *Always replace with thumbnail* / **Hide avatar**. The new **Hide** mode
  drops the avatar entirely for a lighter list (handy on mobile) and works
  even without thumbnails. Both extensions write the **same** neutral
  `tryhackx-avatars.mode_desktop` / `…_mobile` keys (serialized as
  `tryhackxAvatarModeDesktop` / `…Mobile`), so changing it in one is reflected
  in the other. The "replace" modes self-detect Thumb Sliders — with it absent
  they behave as *Show*.
- **"Secondary tags used on their own (no primary tag)"** — a new list in the
  per-tag admin (below the primary tree) that controls discussions tagged with
  **only a secondary tag and no primary**. Each standalone secondary tag is
  *Enabled* / *Disabled*; **default is Disabled** (opt-in), with collapse +
  *Enable all* / *Disable all*. A secondary-only discussion is rateable if at
  least one of its secondary tags is enabled here. Stored as bare keys in the
  existing `tag_config`.
- **"Allow rating on discussions with no tags"** (`untagged_enabled`, default
  **on**). When off, tagless discussions show no rating widget and can't be
  rated (`ratingDisplayMode` returns `hidden` for them).
- **"Hide empty stars from users who can't rate"** (`hide_empty_for_nonvoters`,
  default **off**). On the discussion list, topics with **no ratings yet** don't
  render the widget for viewers who can't rate (guests, restricted groups).
  Already-rated topics still show. Serialized as
  `tryhackxTopicRatingHideEmptyForNonVoters`.
- **"Hide ratings on rating-disabled topics"** (`hide_disabled_ratings`, default
  **on**). When a topic can no longer be rated because **all of its tags are
  disabled** (e.g. its tag was switched to a disabled one *after* it was rated),
  the widget — including any existing ratings — is hidden for viewers who can't
  rate, instead of showing them read-only. Prevents "rate it, then lock in the
  score" abuse. Viewers who *can* rate (e.g. bypass groups) still see it. This
  wires up the previously-dormant `isRatingDisabled()` check.
- **Rating moderation items follow the rating's visibility.** *Disable Rating*
  and *Reset All Ratings* now appear in the discussion's ⋮ controls dropdown
  only when the rating is actually shown on that topic — they're hidden when the
  topic's tags / untagged config hide the rating (managing a hidden rating does
  nothing). The per-topic moderator toggle is exempt, so a topic disabled from
  the menu can still be re-enabled there. A new **"Always show rating controls
  in the discussion menu"** setting (`show_moderation_controls`, default **off**)
  overrides this to always show them — handy for managing ratings where the
  widget is hidden. Still gated by the moderation permissions. Serialized as
  `tryhackxTopicRatingShowModerationControls`.

### Changed
- **Shared discussion-list layout module → `LAYOUT_VERSION = 5`**
  (byte-identical with `flarum-thumb-sliders`). It now resolves the per-device
  avatar mode and the rating placement (meta column vs end of the title), and
  still re-flows on the phone-breakpoint flip.
- **Ratings modal** restyle: the row separator is darkened to `#3c3c41`, and
  the optional in-modal rate control is centred with no label.
- **Admin selects** (rating-style + avatar) now render at their natural width
  instead of stretching edge-to-edge, and clip long labels with an ellipsis
  (`max-width: 100%`) so they never overflow on narrow / mobile widths — the
  full label still shows when the native picker opens.
- The **"Secondary tags used on their own"** admin list is rendered as a
  vertical stack (matching the primary tree) and is **collapsed by default**.

### Fixed
- **Ratings modal title showed `Ratings ({undefined})`** when the list
  response had no `meta.total`. The count now falls back to the discussion's
  own `ratingCount`, so the heading is always correct — on the list *and* the
  discussion page.
- **Compact single empty star** rendered in a washed-out grey/white; it now
  uses the star colour (yellow) to match the half / full states.
- **Discussions tagged with only a secondary tag (no primary) were always
  rateable**, ignoring the per-tag settings — because the policy defaulted an
  unconfigured standalone secondary to *enabled* and the admin had no way to
  disable it. Such discussions now default to **Disabled** and are controlled
  by the new "Secondary tags used on their own" list. Tagless discussions get
  their own switch (above). (Discovered via a per-tag report where only one
  primary tag was enabled yet announcement-style, secondary-only topics still
  showed ratings.)

## [2.1.2] - 2026-06-01

> Discussion-list layout polish for the shared restructured layout
> (mirrored with `flarum-thumb-sliders`). Tags get a proper mobile home,
> the desktop meta column stops colliding with the controls dropdown, and
> the layout now re-flows reliably on resize / orientation change. Pure
> frontend/layout — no new settings, no migrations, no API changes, no
> change to the rating policy or permissions.

### Changed
- **Mobile tag placement.** On phones the discussion's tags now render
  inside `.DiscussionListItem-main`, on their own line right below the
  author/info line, wrapping across the full row width (`flex-wrap`)
  instead of stacking one-per-line in the narrow right-hand meta column
  (which made tag-heavy rows very tall). Tablet/desktop is unchanged —
  tags stay in the meta column. The rating stars stay in the meta column
  on every viewport.
- **Mobile tag size pinned** to `font-size: 11px` on
  `.DiscussionListItem-mobileTags` (≈9px labels with `TagLabel`'s own
  `0.85em`), so tags on mobile match or sit just below the desktop tag
  size instead of inheriting the larger body font.
- Mobile `.DiscussionListItem-main` right padding dropped from `4px` to
  `0` — the flex `gap` already separates the main column from the meta
  column, so the extra padding was redundant.
- Shared layout module bumped to `LAYOUT_VERSION = 4` (byte-identical to
  the copy in `flarum-thumb-sliders`).

### Fixed
- **Desktop meta column no longer slides under the controls (⋮) dropdown.**
  On tablet+ the meta column (tags / rating / views / replies) now reserves
  `28px` on its right so it clears the absolutely-positioned controls icon.
  Previously, on rows without a thumbnail or rating, the meta column reached
  far enough right to overlap the ⋮ (most visible on hover/active rows).
- **Tag layout re-flows reliably when the viewport crosses the phone
  breakpoint** (window resize, device rotation). The previous build
  recorded the "last breakpoint" inside `onbeforeupdate`, which never runs
  on mount, so the *first* desktop↔mobile switch after page load was
  silently dropped (tags didn't move until a second toggle). The flag is
  now written in `contentView` (runs on every render incl. mount), and an
  `onbeforeupdate` override forces a one-off rebuild on the render where
  the breakpoint flips — beating core's `SubtreeRetainer`, which otherwise
  pins each row to whichever layout it first rendered with.

## [2.1.1] - 2026-05-31

### Fixed
- README screenshots now load from the local `assets/` folder
  (relative paths) instead of GitHub raw URLs. This means they render
  correctly on `composer require` installs that mirror the package to
  their own infrastructure, and they keep working offline.
- Updated the `Topic_Rating.png` caption to reflect the 2.1 admin
  panel (per-tag permission tree, bypass-group picker, display-mode
  selector).

> No code changes versus 2.1.0 — same migrations, same PHP/JS bundles,
> same API surface. Pure docs/asset reference fix.

## [2.1.0] - 2026-05-31

> Major feature release: **per-tag rating permissions** with a dedicated
> bypass-group picker. Activates automatically when the bundled
> `flarum/tags` extension is enabled, otherwise the extension behaves
> exactly as before (no behaviour change for tag-less installs).

### Added
- **Admin API endpoint** `GET /api/tryhackx-topic-rating/tag-config`
  (admin-only). Returns a structured JSON snapshot of every
  extension setting (parsed, not raw strings) — handy for migration
  scripts, audits, external dashboards.
- **Orphan cleanup** — listener on `Flarum\Tags\Event\Deleting` strips
  `{tagId}` and any compound `*_{tagId}` / `{tagId}_*` entries from
  `tag_config` when a tag is deleted in Tags admin (no more dead IDs
  growing in the JSON).
- **Bypass-group picker quality-of-life** — *Select all* / *Clear*
  toolbar buttons above the pills.
- **Override badge** on each primary-tag card — small `✏ N` chip
  showing how many secondary tags under that primary have explicit
  non-`inherit` state.
- **Per-tag rating permissions** (`tryhackx-topic-rating.tag_config`,
  visible only when `flarum/tags` is enabled). Two-level admin tree:
  - **Primary tags** are the cards in the top grid. Each one picks one of
    three states — *Enabled* (default, anyone with the global Rate
    permission can rate), *Disabled* (nobody can rate, unless they hold
    bypass), *Allow groups* (only the picked groups can rate).
  - **Secondary tags** are listed under each primary card (expand the
    card). Their default state is *Inherit* (follow the parent), and
    they can override their parent with *Enabled / Disabled / Allow
    groups* independently per primary.
  - Toolbar: **Expand all / Collapse all** buttons appear when at least
    one primary card has expandable children.
- **Bypass-group picker** (`tryhackx-topic-rating.bypass_groups`,
  default `["1"]` — Admin only). Group-based pill selector replaces the
  old permission-grid bypass row. Admins remain default-bypass but can
  be removed by deselecting the Admin pill (testing tag rules without
  switching accounts). Empty list = nobody bypasses (only Flarum-core
  admin-of-everything still applies outside this extension's policy).
- **Display mode for restricted users**
  (`tryhackx-topic-rating.display_when_restricted`,
  default `readonly`): pick what users without rating permission see —
  *Read-only stars* (default visual, no interaction), *Hidden* (widget
  removed entirely), or *Message* (short note instead of stars).
- **New `ratingDisplayMode` field** on the Discussion API resource —
  one of `rate | readonly | hidden | message`. Frontend renders accordingly.
- Polish & English locale strings for everything above, including an
  in-UI admin note explaining how Flarum's hard-coded "admin has every
  permission" interacts with the bypass picker.

### Changed
- **Centralised bypass logic** — single public
  `RatingPolicy::actorBypassesGlobal(User)` is the only place that
  parses the bypass-group list. `extend.php` (serializer fields) and
  any downstream code call it instead of re-implementing the JSON
  decode + intersect dance.
- **Per-request memoization in `RatingPolicy`** — `tag_config`,
  `bypass_groups` and the actor → group-ids list are parsed at most
  once per request. On a 20-discussion list page that's a
  measurable drop in `SettingsRepositoryInterface::get` calls.
- **Email-activation gate now applies consistently on both paths**
  (legacy and tag-aware). Pre-2.1, the legacy path
  short-circuited to *allow* when the user already held
  `discussion.rate`, even with an unconfirmed email — that loophole
  is closed. Existing forums where unconfirmed users were rating
  through the loophole should turn *Allow unactivated accounts* on
  if they want to preserve the old effect.
- **Empty-state UX in the tag tree** — distinguishes "no tags at all"
  from "only secondary tags exist; mark at least one as primary".
- **Dead code removed** in `RatingPolicy` (`isDescendantOf()` and
  `resolveTagState()` — leftovers from an earlier parent_id-based
  iteration that was superseded by `resolveDiscussionStates()`).
- **Unused locale keys removed** (`tag_inherits_from`,
  `tag_allow_groups_label`).
- `RatingPolicy::rate()` now resolves a discussion's effective state
  across **all** of its tags (primary × secondary combinations):
  - *Most-permissive wins*: if any tag combination would allow the actor
    to rate, rating is allowed.
  - *Disabled is overridable*: a primary tag set to *Disabled* can still
    permit rating in a secondary that's individually overridden to
    *Enabled* or *Allow groups*.
  - *Bypass short-circuits everything*: any tag for which the actor
    bypasses counts as allowed.
- `canRateRequiresActivation` API field now respects the bypass-group
  picker (no false "activate your account" tooltip for bypass holders).
- `RatingPolicy::isRatingDisabled(Discussion)` is a new public helper
  that returns `true` only when **every** resolved (primary, secondary)
  combination is *Disabled*. Used by `ratingDisplayMode` so the
  hidden/message modes kick in only for "truly closed" discussions.
- Admin tree layout: card grid (`grid-template-columns:
  repeat(auto-fill, minmax(360px, 1fr))`) instead of a single
  vertical list, so the per-tag table uses horizontal space and
  doesn't sprawl downward.

### Fixed
- **Tag relation lookup never returned anything.** The previous
  `method_exists($discussion, 'tags')` guard always returned `false`
  because Flarum/Tags registers `tags` dynamically via `Extend\Model`
  (resolved through `__call`, not as a real method). As a result the
  per-tag policy branch was silently skipped and the legacy "any user
  with `discussion.rate` can rate everywhere" path always won. Replaced
  with a direct relation access wrapped in `try/catch` — falls back to
  an empty collection if Tags isn't installed.
- `canRate` / `ratingDisplayMode` now stay in sync on the discussion
  list after editing tag-config (frontend reads them on each refresh).

### Migrations
- `2026_05_31_000000_add_rating_bypass_permission.php` — historical
  no-op for fresh installs; left in place so existing installs that
  ran it remain consistent.
- `2026_05_31_000001_revoke_default_bypass_permission.php` — clears
  any default `discussion.rate.bypass` grant from `group_permission`
  (the row is no longer used; the bypass group list is now a setting).

### Notes for administrators
- **Test as a non-admin account** to see the real effect of *Disabled*
  / *Allow groups*. Flarum hard-grants admins every permission; the
  bypass-group picker is the only place where you can opt the Admin
  group out of bypass for this extension.
- After upgrading, run `php flarum migrate` and `php flarum cache:clear`.

## [2.0.6] - 2026-05-30

### Added
- Admin setting **Show rating in discussion list**
  (`tryhackx-topic-rating.show_on_list`, default on) — visibility toggle
  for the stars on the homepage list. When off, the rating still appears
  on the discussion page.
- Admin setting **Allow rating from the discussion list**
  (`tryhackx-topic-rating.rate_on_list`, default on) — controls whether
  the list stars are interactive (click + hover preview); when off, the
  list is read-only.
- Polish (`pl.yml`) strings for the two new list-related settings.

### Changed
- The rating in the discussion list is now interactive by default — users
  can rate a topic without opening it.
- The list rating is rendered in the right meta column (sibling of the
  discussion link), so clicking a star no longer navigates to the topic.
- Desktop content padding of the restructured discussion list aligned
  to the same horizontal rhythm as mobile: `12px 6px 12px 6px` (was
  `12px 16px 12px 0`), so the row breathes evenly on both sides.
  Mirror of the same edit in `flarum-thumb-sliders` — both extensions
  ship the identical structural CSS for the shared layout.
- Override of core's tablet+ `.DiscussionListItem` padding from
  `25 / 15` to a symmetric `12 / 12` so the outer row padding matches
  the inner content padding. Mirrored in `flarum-thumb-sliders`.
- Cap the right meta column at `max-width: 18%` on the restructured
  layout, so a discussion with many tags wraps them down inside the
  narrow column instead of pushing the row wider. Mirrored in
  `flarum-thumb-sliders`.

### Fixed
- **Cancel button in core's "Reset extension settings" modal** now
  uses Flarum's standard `Button--inverted` style so it doesn't render
  as a plain borderless button. Implemented with a small
  `MutationObserver` that adds the `Button--inverted` class to the
  Cancel button when the modal appears in the DOM (the modal class
  is lazy-loaded by core and not statically importable, so we can't
  extend its prototype directly). Each TryHackX extension registers
  this independently; repeated `classList.add` of the same class is
  a no-op.
- **Hover preview now actually shows on the discussion list.** Flarum's
  `DiscussionListItem` uses a `SubtreeRetainer` that swallows Mithril
  redraws unless `discussion.freshness` changes — which meant the
  `hoveredValue` state of `StarRating` never reached the DOM and the
  stars stayed at the average. `StarRating` now paints the star classes
  directly on the DOM in its `onmouseenter` / `onmouseleave` handlers
  (the same pattern `flarum-thumb-sliders` uses), so the preview appears
  on both the list and the discussion page.

## [2.0.2] - 2026-04-09

### Changed
- Moved support button to the top of the admin settings page.
- Removed margin-top / padding-top / border-top CSS from the support button section.

## [2.0.1] - Initial tracked release

### Added
- 5-star rating system with half-star precision (0.5 to 5.0).
- Real-time rating average updates via polling.
- Ratings modal showing all individual ratings with user info.
- Moderator controls to toggle ratings on/off per discussion and reset all ratings.
- Permission-based rating, toggling, and resetting.
- Optional setting to allow unactivated users to rate.
- English and Polish translations.
