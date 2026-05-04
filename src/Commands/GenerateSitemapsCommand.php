<?php

namespace MaikelB\MultidomainSitemap\Commands;

use Illuminate\Console\Command;
use MaikelB\MultidomainSitemap\Sitemaps\LocaleSitemapIndex;
use Statamic\Facades\Site;

class GenerateSitemapsCommand extends Command
{
    protected $signature = 'multidomain-sitemap:generate
        {--site= : Limit generation to a specific site handle}';

    protected $description = 'Pre-generate per-locale sitemaps and write them to the configured storage path';

    public function handle(): int
    {
        $sites = $this->option('site')
            ? collect([Site::get($this->option('site'))])->filter()
            : Site::all();

        if ($sites->isEmpty()) {
            $this->error('No sites found.');

            return self::FAILURE;
        }

        foreach ($sites as $site) {
            $this->info("Generating sitemap for site [{$site->handle()}]...");

            Site::setCurrent($site->handle());

            $index = new LocaleSitemapIndex($site);

            $index->save();

            foreach ($index->sitemaps() as $sitemap) {
                $this->line("  - ".$sitemap->filename());
                $sitemap->save();
            }
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
