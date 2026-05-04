<?php

namespace MaikelB\MultidomainSitemap\Sitemaps;

use Aerni\AdvancedSeo\Actions\IncludeInSitemap;
use Illuminate\Support\Collection;
use MaikelB\MultidomainSitemap\Sitemaps\Urls\TaxonomySitemapUrl;
use MaikelB\MultidomainSitemap\Sitemaps\Urls\TermSitemapUrl;
use Statamic\Contracts\Taxonomies\Taxonomy as TaxonomyContract;
use Statamic\Facades\Blink;
use Statamic\Sites\Site;

class LocaleTaxonomySitemap extends BaseLocaleSitemap
{
    public function __construct(protected TaxonomyContract $taxonomy, protected Site $site) {}

    public function handle(): string
    {
        return $this->taxonomy->handle();
    }

    public function type(): string
    {
        return 'taxonomy';
    }

    public function urls(): Collection
    {
        return Blink::once($this->blinkKey(), function () {
            return $this->taxonomyUrls()
                ->merge($this->termUrls())
                ->merge($this->collectionTermUrls())
                ->filter(fn ($url) => $url->canonicalTypeIsCurrent())
                ->values();
        });
    }

    protected function taxonomyUrls(): Collection
    {
        if (! IncludeInSitemap::run($this->taxonomy, $this->site->handle())) {
            return collect();
        }

        if (! view()->exists($this->taxonomy->template())) {
            return collect();
        }

        return collect([new TaxonomySitemapUrl($this->taxonomy, $this->site)]);
    }

    protected function termUrls(): Collection
    {
        $terms = $this->taxonomy->queryTerms()
            ->where('site', $this->site->handle())
            ->where('published', true)
            ->whereNotNull('uri')
            ->get()
            ->filter(fn ($term) => IncludeInSitemap::run($term));

        $first = $terms->first();
        if (! $first || ! view()->exists($first->template())) {
            return collect();
        }

        return $terms->map(fn ($term) => new TermSitemapUrl($term))->values();
    }

    /**
     * Terms scoped to a collection (e.g. /blog/tags/foo). Filtered to the
     * current site.
     */
    protected function collectionTermUrls(): Collection
    {
        $terms = $this->taxonomy->queryTerms()
            ->where('site', $this->site->handle())
            ->get();

        return $this->taxonomy->collections()
            ->filter(fn ($collection) => $collection->sites()->contains($this->site->handle()))
            ->flatMap(function ($collection) use ($terms) {
                return $terms->map(fn ($term) => $term->fresh()->collection($collection));
            })
            ->filter(fn ($term) => view()->exists($term->template()))
            ->filter(function ($term) {
                return $term->taxonomy()->sites()
                    ->merge($term->collection()->sites())
                    ->duplicates()
                    ->contains($term->locale());
            })
            ->filter(fn ($term) => IncludeInSitemap::run($term->taxonomy(), $term->locale()))
            ->map(fn ($term) => new TermSitemapUrl($term))
            ->values();
    }

    protected function blinkKey(): string
    {
        return "maikel-b.multidomain-sitemap.taxonomy.{$this->site->handle()}.{$this->taxonomy->handle()}";
    }
}
