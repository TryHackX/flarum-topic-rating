# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
