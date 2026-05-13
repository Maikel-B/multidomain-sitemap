# Pending upstream patches (branch: `fix/audit-patches`)

Draft PR description against `main` (currently at `v1.1.0`). Seven independent commits, each addressing one concern. See the individual commit messages for full reasoning.

## Patches

1. **fix: return null lastmod for taxonomy sitemaps with no terms**
   `Cache::rememberForever(... now())` froze the lastmod at first generation; once the taxonomy got its first term the stamp never updated.

2. **feat: strip_locale_prefix option to preview production URLs from staging**
   Optional config flag for path-prefix staging environments so the sitemap output matches the URL shape a per-domain production will emit. Includes segment-boundary safety so a `/uk` prefix does not strip `/ukraine-policy/`.

3. **feat: widen crawling.environments only during sitemap operations**
   Decouples sitemap generation from aerni's noindex-meta gate. The command and controller `Config::set` the current env into `advanced-seo.crawling.environments` for the scope of the sitemap operation only, then restore. Staging can serve real sitemaps without removing noindex from page responses.

4. **fix: dedupe sitemap entries by normalized <loc>**
   Two entries that resolve to the same URI used to emit duplicate `<url>` blocks. Google treats duplicates as a quality signal and splits crawl budget. Dedupe by the normalized loc after the canonical-type filter.

5. **fix: restore Site::current after generate command exits**
   `Site::setCurrent()` is process-global; the loop ended with the last site as current and leaked into queue workers, follow-up scheduled commands, and Octane workers. Snapshot + restore in a finally block.

6. **fix: per-term template check in LocaleTaxonomySitemap**
   Previously the first term's template was checked for the entire batch — one unviewable term dropped every other term in the sitemap. Move the template check into the per-term filter chain.

7. **fix: isolate per-site failures in generate command**
   One site that threw used to kill the loop and prevent every later site from regenerating. Wrap each iteration in try/catch, log + report, summarize at the end. Failed sites degrade gracefully via their previous cache file.

## Known issues not addressed in this PR

- The addon couples directly to aerni internals (`IncludeInSitemap`, `Models\Defaults`, `Support\Helpers::parseLocale`). Composer's `require` does not upper-pin `aerni/advanced-seo`. A minor aerni bump can change those internals and break the addon. Pinning is recommended.
- `EntrySitemapUrl::alternates` derives `x-default` via `$entry->origin()`, which returns the immediate parent localization in a multi-level chain, not the root. For flat localizations (every translation's origin is the root) this is fine; for chained localizations the x-default may point at the wrong site.
- `lastModified()` returns filesystem mtime, which `git pull` resets on deploy. Search engines see "every URL changed at deploy time" and recrawl everything. Inherited from Statamic, not the addon.
- No test suite. Project that consumes this fork has integration coverage in `tests/Feature/Sitemap/`; the addon itself should bootstrap orchestra/testbench + statamic/cms test stack for `tests/Unit/UrlNormalizerTest.php` and `tests/Unit/TaxonomySitemapUrlTest.php`. Left as TODO pending discussion.
