# Topic Rating for Flarum

A [Flarum](https://flarum.org/) extension that adds a 5-star rating system to
discussions with half-star precision — usable from **the discussion page
*and* the discussion list**, with a live hover preview, polling refresh, and
fine-grained admin / permission controls.

> **Latest (2.1.0):** Per-tag rating permissions. When the bundled
> `flarum/tags` extension is enabled, you get a card-grid admin tree that
> lets you set rating to *Enabled / Disabled / Allow groups* per primary
> tag, with secondary tags individually overridable per primary
> (*Inherit / Enabled / Disabled / Allow groups*). A separate
> bypass-group picker decides who can rate restricted tags anyway —
> with the Admin group included by default but deselectable for testing.

> **Note:** Recent updates target the **2.x** line only. The **1.x** branch
> (Flarum 1.8+) is **no longer actively developed** — it stays available
> for legacy installs but won't receive new features.

## Features

- **Half-star precision** — rate from 0.5 to 5.0 (stored as 1–10).
- **Interactive on both the page and the list** — same component, same
  hover preview, same click-to-rate semantics. Clicking on the list does
  *not* open the topic, because the stars sit in a sibling node of the
  discussion link.
- **Live hover preview that works under `SubtreeRetainer`** — the rating
  component paints star classes directly on the DOM in its
  `onmouseenter` / `onmouseleave` handlers, so the preview shows even
  where Flarum blocks Mithril redraws.
- **Real-time updates** — short-poll on the discussion page keeps the
  average and "your rating" tooltip current without reloading.
- **Ratings modal** — click the count to see who rated, what they gave,
  and when they last changed it.
- **Moderator controls** — toggle rating on/off per topic, reset all
  ratings on a topic.
- **Per-tag rating permissions** *(requires `flarum/tags`)* — card-grid
  admin tree controls who can rate where. Three states per primary tag
  (*Enabled / Disabled / Allow groups*) with per-(primary × secondary)
  overrides. **Most-permissive wins** across a discussion's tags.
- **Bypass-group picker** — pick which groups can rate even on
  restricted tags (Admin by default; deselect Admin to subject admins
  to the same rules for testing).
- **Display mode for restricted users** — choose what users without
  rating permission see: *Read-only stars*, *Hidden widget*, or *Short
  message*.
- **Permission-based** — global *Rate discussions* permission (Reply
  section), plus moderator-only *Enable/Disable* and *Reset all*.
- **Unactivated accounts** — optional admin toggle that lets users without
  email confirmation rate.
- **Localised** — English (`en.yml`) and Polish (`pl.yml`).

## Screenshots

![Mobile view of the discussion list across multiple TryHackX layout combinations](https://github.com/TryHackX/flarum-topic-rating/blob/main/assets/ALL_MOBILE.png?raw=true)

*Mobile view — discussion list rendered with different combinations of TryHackX extensions (thumbnails + ratings + views, thumbnails + views, thumbnails only, ratings only, views only, vanilla Flarum).*

![Topic Rating admin settings — list visibility / interactivity, unactivated accounts and permissions](https://github.com/TryHackX/flarum-topic-rating/blob/main/assets/Topic_Rating.png?raw=true)

*Topic Rating admin panel — master switch, *Show rating in discussion list*, *Allow rating from the discussion list*, *Allow unactivated accounts to rate*, plus the *Rate discussions* / *Enable/Disable rating* / *Reset all ratings* permission rows.*

![Desktop discussion list with the full TryHackX stack — thumbnail sliders, star ratings and the magnet button](https://github.com/TryHackX/flarum-topic-rating/blob/main/assets/ALL_VIA_MAGNETS.png?raw=true)

*Desktop discussion list with the full TryHackX stack — star ratings sit in the right-hand meta column next to thumbnail sliders and the magnet button, click-to-rate and hover preview both active.*

![Desktop discussion list — magnet tooltip mid-load on a topic](https://github.com/TryHackX/flarum-topic-rating/blob/main/assets/ALL_VIA_MAGNETS_v2.png?raw=true)

*Desktop discussion list — hover state showing the magnet tooltip loading inline alongside the interactive star ratings.*

## Support Development

If you find this extension useful, consider supporting its development:

- **Monero (XMR):** `45hvee4Jv7qeAm6SrBzXb9YVjb8DkHtFtFh7qkDMxS9zYX3NRi1dV27MtSdVC5X8T1YVoiG8XFiJkh4p9UncqWGxHi4tiwk`
- **Bitcoin (BTC):** `bc1qncavcek4kknpvykedxas8kxash9kdng990qed2`
- **Ethereum (ETH):** `0xa3d38d5Cf202598dd782C611e9F43f342C967cF5`

You can also find the donation option in the extension's admin settings panel.

## Installation

```bash
composer require tryhackx/flarum-topic-rating
php flarum migrate
php flarum cache:clear
```

## Updating

```bash
composer update tryhackx/flarum-topic-rating
php flarum migrate
php flarum cache:clear
```

## Configuration

Enable the extension in **Admin Panel → Extensions → Topic Rating**.

### Settings

| Setting | Default | What it does |
| --- | --- | --- |
| **Enable Topic Rating** | On | Master switch for the extension. |
| **Allow unactivated accounts to rate** | Off | Users without email confirmation can submit ratings. |
| **Show rating in discussion list** | On | Visibility toggle for the stars on the homepage list. When off, ratings stay on the discussion page. |
| **Allow rating from the discussion list** | On | Make the list stars interactive (click + hover preview). When off, the list shows the rating read-only. |
| **Per-tag rating permissions** ¹ | empty / all enabled | Card-grid tree. Per-primary-tag state (*Enabled / Disabled / Allow groups*), per-(primary × secondary) override (*Inherit* by default). |
| **Bypass tag restrictions** ¹ | Admin group | Groups whose members can rate even where a tag is *Disabled* or *Allow groups*. Empty = nobody bypasses. |
| **What to show users without rating permission** ¹ | Read-only stars | One of: *Read-only stars* (default), *Hidden*, *Message*. |

¹ Visible only when `flarum/tags` is enabled.

### Per-tag permissions — how the policy decides

When `flarum/tags` is enabled and at least one tag is configured, every
rating attempt walks the discussion's tags this way:

1. **Bypass short-circuits everything.** If the actor's group is in the
   bypass-group list, allow.
2. For every (primary, secondary) tag combination on the discussion,
   resolve the effective state:
   - The compound key `primaryId_secondaryId` overrides if set;
   - Otherwise the secondary's default (`inherit`) follows the primary;
   - The primary's own state (or `enabled` if unset) applies.
3. **Most-permissive wins**: if any combination grants the actor access
   (*enabled* + has the global Rate permission, or *groups* + actor's
   group is allowed, or bypass on that tag), the rating is allowed.
4. Otherwise the rating is denied.

**Special case — every tag effectively *Disabled*:** the
`ratingDisplayMode` API field returns the configured "restricted display
mode" (e.g. *Hidden*), so the widget can be removed entirely on truly
closed discussions.

### Permissions

Set in **Admin Panel → Permissions**:

| Permission | Section | Default |
| --- | --- | --- |
| Rate discussions | Reply | Members |
| Enable/Disable rating | Moderate | Mods |
| Reset all ratings | Moderate | Mods |

> Bypass for tag restrictions is **not** in the permission grid — it
> lives in the extension settings as a group-picker (see *Bypass tag
> restrictions* above). This avoids the Flarum-core admin-always-has-it
> behaviour and lets you remove Admin from the bypass list for testing.

## API surface

The extension exposes the following on the Discussion API resource:

`ratingAverage`, `ratingCount`, `lastRatedAt`, `userRating`,
`ratingDisabled`, `canRate`, `canRateRequiresActivation`,
`canToggleRating`, `canResetRatings`, `ratingDisplayMode`.

`ratingDisplayMode` is one of `rate | readonly | hidden | message` and
tells the frontend how to render the widget for the current user.

It registers these endpoints:

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/discussion-ratings` | Submit / update a rating. |
| `DELETE` | `/discussion-ratings` | Remove your rating. |
| `POST` | `/discussions/{id}/toggle-rating` | Toggle rating on a discussion. |
| `POST` | `/discussions/{id}/reset-ratings` | Reset all ratings on a discussion. |
| `GET` | `/discussion-ratings/poll` | Poll for fresh average / count / your-rating. |
| `GET` | `/tryhackx-topic-rating/tag-config` | **Admin only.** Structured JSON of every Topic Rating setting (parsed) — `{settings, bypassGroups, tagConfig}`. |

## Compatibility

- Flarum `>=1.8.0 <3.0.0`
- The `2.x` branch targets Flarum 2.0+.
- Per-tag features auto-activate only when `flarum/tags` is enabled.

## Links

- [GitHub](https://github.com/TryHackX/flarum-topic-rating)
- [Packagist](https://packagist.org/packages/tryhackx/flarum-topic-rating)
- [Report Issues](https://github.com/TryHackX/flarum-topic-rating/issues)

## License

MIT License. See [LICENSE](LICENSE) for details.
