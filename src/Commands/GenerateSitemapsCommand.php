<?php

namespace MaikelB\MultidomainSitemap\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use MaikelB\MultidomainSitemap\Sitemaps\LocaleSitemapIndex;
use Statamic\Facades\Site;

class GenerateSitemapsCommand extends Command
{
    protected $signature = 'multidomain-sitemap:generate
        {--site= : Limit generation to a specific site handle}';

    protected $description = 'Pre-generate per-locale sitemaps and write them to the configured storage path';

    public function handle(): int
    {
        // aerni/advanced-seo gates IncludeInSitemap behind
        // advanced-seo.crawling.environments — which is also the list that
        // controls whether pages emit noindex,nofollow robots meta. We MUST
        // NOT widen that list project-wide on staging (it would let crawlers
        // index staging pages). Instead, widen it only for the duration of
        // this command so the in-memory generator can produce real XML; the
        // generated files are then served as static cache for HTTP requests.
        // Page rendering on the same process keeps the original list.
        $exitCode = self::SUCCESS;

        $this->withCrawlingEnvironmentWidened(function () use (&$exitCode) {
            $this->withCurrentSiteRestored(function () use (&$exitCode) {
                $exitCode = $this->runGeneration();
            });
        });

        $this->info('Done.');

        return $exitCode;
    }

    protected function runGeneration(): int
    {
        $sites = $this->option('site')
            ? collect([Site::get($this->option('site'))])->filter()
            : Site::all();

        if ($sites->isEmpty()) {
            $this->error('No sites found.');

            return self::FAILURE;
        }

        $failed = [];

        foreach ($sites as $site) {
            $this->info("Generating sitemap for site [{$site->handle()}]...");

            try {
                Site::setCurrent($site->handle());

                $index = new LocaleSitemapIndex($site);

                $index->save();

                foreach ($index->sitemaps() as $sitemap) {
                    $this->line('  - '.$sitemap->filename());
                    $sitemap->save();
                }
            } catch (\Throwable $e) {
                $failed[$site->handle()] = $e->getMessage();
                $this->error("  failed: {$e->getMessage()}");

                // Tagged log line so grep-by-site works in laravel.log and
                // the site handle shows up as searchable context in Sentry.
                // The raw exception (with full stack trace) still goes via
                // report() so it groups normally in the exception tracker.
                Log::error("multidomain-sitemap generation failed for site [{$site->handle()}]: {$e->getMessage()}", [
                    'site' => $site->handle(),
                    'exception' => $e,
                ]);
                report($e);
            }
        }

        if (! empty($failed)) {
            $this->warn('Sitemap generation completed with failures on '.count($failed).' site(s): '.implode(', ', array_keys($failed)));

            // Non-zero exit so Laravel's scheduler logs this as a failed run
            // and any cron-health monitor (uptime checks, healthchecks.io,
            // schedule:list watcher) flags it. Sites that succeeded still
            // have fresh files in storage; only the failed sites' caches
            // remain stale, which degrades gracefully.
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Site::setCurrent() mutates a process-global. When the cron loops over
     * every locale and then exits, the last site's handle persists — any
     * subsequent code in the same PHP process (queue worker, another
     * scheduled command sharing the runtime, an Octane worker between
     * requests) sees the wrong current site. Snapshot and restore so the
     * command leaves no trace.
     */
    protected function withCurrentSiteRestored(callable $callback): void
    {
        $original = Site::current()->handle();

        try {
            $callback();
        } finally {
            Site::setCurrent($original);
        }
    }

    protected function withCrawlingEnvironmentWidened(callable $callback): void
    {
        $key = 'advanced-seo.crawling.environments';
        $original = Config::get($key, []);
        $current = app()->environment();

        if (! in_array($current, $original, true)) {
            Config::set($key, array_values(array_unique([...$original, $current])));
        }

        try {
            $callback();
        } finally {
            Config::set($key, $original);
        }
    }
}
