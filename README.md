# Multidomain Sitemap

Statamic addon that generates a separate, valid XML sitemap per locale/domain
in a multisite setup. Each site's sitemap is served at the URL prefix or domain
of that site, so search engines see correctly scoped sitemaps instead of one
global file mixing URLs from every locale.

## Why

`aerni/advanced-seo` ships with a sitemap feature, but in a multisite setup it
registers a single `/sitemap.xml` route and aggregates entries from every site
into one file. Search engines treat that as invalid because a sitemap may only
contain URLs from the host that serves it.

This addon takes over the sitemap HTTP routes and registers a route group per
Statamic site. For each site it filters entries and terms by `site` handle and
renders a sitemap that contains only URLs of that locale, while keeping
`hreflang` alternates pointing to the equivalent URLs in the other sites.

## Routes

For every site returned by `Statamic::Site::all()` the addon registers:

| Route                              | Name                                       | Purpose                |
| ---------------------------------- | ------------------------------------------ | ---------------------- |
| `{prefix}/sitemap.xml`             | `multidomain-sitemap.{handle}.index`       | Per-locale index       |
| `{prefix}/sitemaps/{id}.xml`       | `multidomain-sitemap.{handle}.show`        | Sub-sitemap by id      |
| `{prefix}/sitemap.xsl`             | `multidomain-sitemap.{handle}.xsl`         | Stylesheet             |

`{prefix}` is derived from the site's URL: empty for a root site, a path
segment (e.g. `/uk`, `/de`) for prefixed sites, and a `Route::domain()`
constraint for sites on a different host than `config('app.url')`.

## Compatibility with aerni/advanced-seo

Keep `advanced-seo.sitemap.enabled` set to `true` in
[`config/advanced-seo.php`](../../config/advanced-seo.php). The addon overrides
aerni's HTTP routes via `Statamic::pushWebRoutes` (its routes register last and
therefore win over aerni's in Laravel's `RouteCollection`), but the per-entry
gating logic in `Aerni\AdvancedSeo\Features\Sitemap::enabled()` — called from
`Aerni\AdvancedSeo\Actions\IncludeInSitemap` — also reads that same flag. If
the flag is set to `false` every entry, term, collection and taxonomy is
excluded and the generated sitemaps will be empty.

The addon intentionally reuses the existing `aerni/advanced-seo` data so the
SEO tab in the control panel keeps working as before.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=multidomain-sitemap-config
```

This writes [`config/multidomain-sitemap.php`](../../config/multidomain-sitemap.php)
with the following options:

- `enabled` — defaults to `env('MULTIDOMAIN_SITEMAP_ENABLED', true)`. When
  false, no routes and no console command are registered.
- `environments` — array of application environments in which the routes are
  served. Defaults to `['production', 'staging']`. Empty array means all
  environments.
- `path` — directory where pre-rendered sitemaps are stored. Defaults to
  `storage_path('statamic/multidomain-sitemaps')`. Per-site files live in a
  subdirectory matching the site handle.
- `serve_from_cache` — when true (default), the controller serves a
  pre-rendered file from `path` if it exists, otherwise it renders on the fly.

## Pre-generation

```bash
php artisan multidomain-sitemap:generate            # all sites
php artisan multidomain-sitemap:generate --site=nl_NL  # one site
```

Files are written to `{path}/{site}/sitemap.xml` and
`{path}/{site}/{type}-{handle}.xml`. With `serve_from_cache` enabled, HTTP
requests to the sitemap URLs are served straight from disk.

## Per-entry SEO data

Per-entry / per-term filters and metadata are read from the existing
`aerni/advanced-seo` fields:

- `seo_sitemap_enabled`
- `seo_canonical_type`
- `seo_sitemap_priority`
- `seo_sitemap_change_frequency`

The addon does not introduce its own blueprint extension. Anything you
configure in the SEO tab continues to apply.
