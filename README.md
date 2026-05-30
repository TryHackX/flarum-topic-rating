# Topic Rating for Flarum

A [Flarum](https://flarum.org/) extension that adds a 5-star rating system to
discussions with half-star precision — usable from **the discussion page
*and* the discussion list**, with a live hover preview, polling refresh, and
fine-grained admin / permission controls.

> **Latest:** Stars on the discussion list are now interactive by default
> (click to rate, hover for a live preview). Two new admin toggles let you
> independently control visibility and clickability on the list. The
> long-standing "hover preview doesn't show on the list" issue (caused by
> Flarum's `SubtreeRetainer`) is fixed with direct-DOM painting.

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
- **Permission-based** — three independent permissions for rate / toggle
  / reset.
- **Unactivated accounts** — optional admin toggle that lets users without
  email confirmation rate.
- **Localised** — English (`en.yml`) and Polish (`pl.yml`).

## Screenshots

![Mobile view of the discussion list across multiple TryHackX layout combinations](assets/ALL_MOBILE.png)

*Mobile view — discussion list rendered with different combinations of TryHackX extensions (thumbnails + ratings + views, thumbnails + views, thumbnails only, ratings only, views only, vanilla Flarum).*

![Topic Rating admin settings — list visibility / interactivity, unactivated accounts and permissions](assets/Topic_Rating.png)

*Topic Rating admin panel — master switch, *Show rating in discussion list*, *Allow rating from the discussion list*, *Allow unactivated accounts to rate*, plus the *Rate discussions* / *Enable/Disable rating* / *Reset all ratings* permission rows.*

![Desktop discussion list with the full TryHackX stack — thumbnail sliders, star ratings and the magnet button](assets/ALL_VIA_MAGNETS.png)

*Desktop discussion list with the full TryHackX stack — star ratings sit in the right-hand meta column next to thumbnail sliders and the magnet button, click-to-rate and hover preview both active.*

![Desktop discussion list — magnet tooltip mid-load on a topic](assets/ALL_VIA_MAGNETS_v2.png)

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

The two list-related settings are independent axes:

- *Show* off → no stars on the list.
- *Show* on + *Rate* off → stars visible but read-only (no hover preview).
- *Show* on + *Rate* on → click + hover preview, matching the discussion page.

### Permissions

Set in **Admin Panel → Permissions**:

| Permission | Section | Default |
| --- | --- | --- |
| Rate discussions | Reply | Members |
| Enable/Disable rating | Moderate | Mods |
| Reset all ratings | Moderate | Mods |

## API surface

The extension exposes the following on the Discussion API resource:

`ratingAverage`, `ratingCount`, `lastRatedAt`, `userRating`,
`ratingDisabled`, `canRate`, `canRateRequiresActivation`,
`canToggleRating`, `canResetRatings`.

It registers these endpoints:

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/discussion-ratings` | Submit / update a rating. |
| `DELETE` | `/discussion-ratings` | Remove your rating. |
| `POST` | `/discussions/{id}/toggle-rating` | Toggle rating on a discussion. |
| `POST` | `/discussions/{id}/reset-ratings` | Reset all ratings on a discussion. |
| `GET` | `/discussion-ratings/poll` | Poll for fresh average / count / your-rating. |

## Compatibility

- Flarum `>=1.8.0 <3.0.0`
- The `2.x` branch targets Flarum 2.0+.

## Links

- [GitHub](https://github.com/TryHackX/flarum-topic-rating)
- [Packagist](https://packagist.org/packages/tryhackx/flarum-topic-rating)
- [Report Issues](https://github.com/TryHackX/flarum-topic-rating/issues)

## License

MIT License. See [LICENSE](LICENSE) for details.
