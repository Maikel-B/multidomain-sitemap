<?php

namespace MaikelB\MultidomainSitemap\Sitemaps;

use Aerni\AdvancedSeo\Actions\IncludeInSitemap;
use Illuminate\Support\Collection;
use MaikelB\MultidomainSitemap\Sitemaps\Urls\EntrySitemapUrl;
use Statamic\Contracts\Entries\Collection as EntriesCollection;
use Statamic\Facades\Blink;
use Statamic\Sites\Site;

class LocaleCollectionSitemap extends BaseLocaleSitemap
{
    public function __construct(protected EntriesCollection $collection, protected Site $site) {}

    public function handle(): string
    {
        return $this->collection->handle();
    }

    public function type(): string
    {
        return 'collection';
    }

    public function urls(): Collection
    {
        return Blink::once($this->blinkKey(), function () {
            return $this->entries()
                ->map(fn ($entry) => new EntrySitemapUrl($entry))
                ->filter(fn (EntrySitemapUrl $url) => $url->canonicalTypeIsCurrent())
                ->unique(fn (EntrySitemapUrl $url) => $url->loc())
                ->values();
        });
    }

    /**
     * Query entries for this collection, restricted to the current site.
     * This is the core fix vs. aerni/advanced-seo, which queries entries
     * across all sites.
     */
    protected function entries(): Collection
    {
        return $this->collection->queryEntries()
            ->where('site', $this->site->handle())
            ->where('published', true)
            ->whereNotNull('uri')
            ->get()
            ->filter(fn ($entry) => IncludeInSitemap::run($entry));
    }

    protected function blinkKey(): string
    {
        return "maikel-b.multidomain-sitemap.collection.{$this->site->handle()}.{$this->collection->handle()}";
    }
}
