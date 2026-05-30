# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
