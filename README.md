# SamJUK_Performance

> [!WARNING]
> **Experimental. Not for production. Use at your own risk.**
>
> This is a sandbox for storefront TTFB experiments on my demo stores, published so the code behind the numbers can be read. It hooks plugins into core collections, resource models and swatch helpers, and `preload:build` rewrites Composer's autoload files. It has no automated tests and has only run on a handful of demo stores. Any release can change or break behaviour with no upgrade path, and no support is offered.
>
> If something here looks useful, read the code, lift the idea into your own module, and measure it on a copy of your store first.

Storefront TTFB for pages Varnish can't serve (misses, section load, cart, checkout).
The numbers below were measured on my Hyvä and Mage-OS demo stores, not on real traffic.

## Deploy

After `setup:di:compile` and the last `composer dump-autoload`, as root:

```sh
if bin/magento help preload:build >/dev/null 2>&1; then
    bin/magento preload:build --ini=/usr/local/etc/php/conf.d/98-preload.ini
else
    echo "[i] preload:build command missing, skipping"
fi
```

It only touches the filesystem, so it works in a Docker build with no `env.php` or database. Order matters:

- `setup:di:compile` replaces the DI stubs, so rerun `preload:build` after it.
- `composer dump-autoload` undoes the registration manifest. FPM logs
  `preload: registration manifest missing` when that happened.

`opcache.preload_user` only matters when the FPM master starts as root: PHP then refuses to start if the user
doesn't exist. A non-root master (the CI images run as 65532 with no passwd) ignores it.

FPM must restart after a deploy (it already must with `opcache.validate_timestamps=0`).
Preload needs about 70MB more `opcache.memory_consumption`.

Skipping the step, or the output going missing, leaves a stock store: `preload.php` fails open.

## What `preload:build` does

| Step | Saves (uncached page) | Output |
|---|---|---|
| Registration manifest: one file instead of ~400 `registration.php` includes | ~6ms | `vendor/composer/autoload_registrations.php`, edits `autoload_static.php` and `autoload_real.php` |
| Compiled DI for `global`, `frontend`, `webapi_rest` as preloaded class constants | ~1.8ms | `generated/metadata/<area>.const.php`, `<area>.full.php`, and a stub at `<area>.php` |
| Preload list, filtered to classes that exist and have no top-level code | the rest of the gain | `generated/metadata/preload-classes.php` |
| `--ini`: `opcache.preload` and `opcache.preload_user` (`--user`, default `www-data`) | | the ini path given |

## Class list

`app/etc/preload-classes.txt`, committed per project. Refresh it after upgrades and big module changes:

```sh
bin/magento preload:capture start
# browse in production mode: home, category, product (simple and configurable), search, CMS,
# add to cart, cart, checkout, login, account
bin/magento preload:capture stop
```

Stale names are harmless (`preload:build` drops classes it can't find). The list only grows: classes
already preloaded show up in the next capture too.

## Toggles

Stores > Configuration > Advanced > Performance. All on by default.

| Toggle | Effect | Trade-off |
|---|---|---|
| Skip repeated queries | Warm, block cache on: PDP 76 → 58 queries, PLP 106 → 40, PLP filtered by a swatch 261 → 92, search 96 → 35. Listing swatches, salable checks and swatch-filtered images are loaded once per listing, not per product | Plugins on core collections, resource models and swatch helpers. HTML checked identical, out-of-stock configurables included |
| Cache directory-data section | 8.5 → 0.75ms per page | ~60KB Redis per store view and locale. Region table edits need a config cache clean |
| Cache view.xml | ~1.5ms per read | 9.4KB per theme |
| Skip duplicate price histogram search | OpenSearch calls 4 → 3 on PLP and search | Elasticsearch and OpenSearch only |
| Build CSP header once | ~1ms per response | |

## Metrics

Stores > Configuration > Advanced > Performance > Metrics. All off by default.

| Toggle | Output |
|---|---|
| Server-Timing header | Every metric below, shown in browser dev tools under Timing |
| X-Render-Time header | `X-Render-Time: 52.3ms` |
| Show render time in footer | `Rendered in 52.3ms (FPC miss)` at the far right of the copyright line (Luma, Blank and Hyvä block names). Click it for the breakdown |

```
Server-Timing: app;desc="PHP total";dur=169.5, boot;desc="Boot";dur=44.8, dispatch;desc="Routing and controller";dur=54.1,
  render;desc="Render";dur=66.2, layout;desc="Layout build";dur=17.1, db;desc="Database (94 queries)";dur=14.8,
  cache;desc="Cache (80 calls, 0 misses)";dur=1.4, mem;desc="Peak memory 18.1MB", fpc;desc="FPC miss"
```

| Metric | Covers |
|---|---|
| `app` | PHP receiving the request to sending the response. Leaves out nginx and the network |
| `boot` | Up to the front controller: autoload, DI, area config |
| `dispatch` | Routing and the controller, less any render a legacy controller does itself |
| `render` | `renderResult()`: blocks to HTML, and the full-page cache save |
| `layout` | Layout XML load and merge plus block generation, inside dispatch or render |
| `db` | Every query on the default connection after boot (stock `Zend_Db_Profiler`) |
| `cache` | Cache loads and saves after boot, full-page cache included. Misses are loads that found nothing: many after a flush or deploy, few once warm |
| `search` | `SearchEngineInterface::search()`, so any engine. Only pages that search (PLP, search results) |
| `mem` | `memory_get_peak_usage()` |
| `fpc` | `FPC hit` (served from the built-in full-page cache), `FPC miss` (full render, cacheable) or `Not cacheable`. Also in the footer after the time |

`boot`, `dispatch` and `render` add up to roughly `app`. The rest happen inside them.

Varnish caches the numbers with the page: a cached page shows how long its miss took, and `FPC miss`. The built-in full-page
cache fills them in on every response, so a hit shows the hit's own time. JSON responses (section load, add to
cart) get the headers too.

Cost: with both toggles off, the plugins return straight away and the DB profiler is never attached. On, each cache
call costs about 1 to 2µs more (0.1 to 0.4ms over 80 to 185 calls a page, measured on the Mage-OS demo).

## Notes

Always on: store scopes cached in the config cache (3 queries per page; store group saves clean it).
Also sets core `dev/caching/cache_user_defined_attributes` to 1: PLP 83 → 40 queries for about 20 more cache calls.

Mage-OS 3.6 applies cache frontend decorators twice, so the cache timer skips the outer copy. It also maps only short
backend names (`redis`, `file`) to its Symfony adapters: `'backend' => 'Magento\\Framework\\Cache\\Backend\\Redis'` in
`env.php` silently falls back to the filesystem. Use `'backend' => 'redis'`.
