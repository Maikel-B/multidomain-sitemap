<?php

namespace MaikelB\MultidomainSitemap\Sitemaps;

use Aerni\AdvancedSeo\Actions\IncludeInSitemap;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Taxonomies\Taxonomy as TaxonomyContract;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\URL;
use Statamic\Sites\Site;

class LocaleSitemapIndex implements Arrayable, Renderable, Responsable
{
    public function __construct(protected Site $site) {}

    public function site(): Site
    {
        return $this->site;
    }

    public function sitemaps(): Collection
    {
        return $this->collectionSitemaps()
            ->merge($this->taxonomySitemaps())
            ->filter(fn (BaseLocaleSitemap $sitemap) => $sitemap->urls()->isNotEmpty())
            ->values();
    }

    public function find(string $id): ?BaseLocaleSitemap
    {
        return $this->allSitemaps()->firstWhere(fn (BaseLocaleSitemap $sitemap) => $sitemap->id() === $id);
    }

    /**
     * All sitemaps, including those without URLs. Used when looking up a
     * sitemap by id so empty ones still 404 cleanly via the urlset rather
     * than via the index filter.
     */
    protected function allSitemaps(): Collection
    {
        return $this->collectionSitemaps()->merge($this->taxonomySitemaps());
    }

    protected function collectionSitemaps(): Collection
    {
        return CollectionFacade::all()
            ->filter($this->shouldProcessCollection(...))
            ->map(fn (CollectionContract $collection) => new LocaleCollectionSitemap($collection, $this->site))
            ->values();
    }

    protected function taxonomySitemaps(): Collection
    {
        return Taxonomy::all()
            ->filter($this->shouldProcessTaxonomy(...))
            ->map(fn (TaxonomyContract $taxonomy) => new LocaleTaxonomySitemap($taxonomy, $this->site))
            ->values();
    }

    protected function shouldProcessCollection(CollectionContract $collection): bool
    {
        if (! $collection->sites()->contains($this->site->handle())) {
            return false;
        }

        return IncludeInSitemap::run($collection, $this->site->handle());
    }

    protected function shouldProcessTaxonomy(TaxonomyContract $taxonomy): bool
    {
        if (! $taxonomy->sites()->contains($this->site->handle())) {
            return false;
        }

        return IncludeInSitemap::run($taxonomy, $this->site->handle());
    }

    public function lastmod(): ?string
    {
        return $this->sitemaps()
            ->map(fn (BaseLocaleSitemap $sitemap) => $sitemap->lastmod())
            ->filter()
            ->sortDesc()
            ->first();
    }

    public function url(): string
    {
        return URL::tidy(rtrim($this->site->absoluteUrl(), '/').'/sitemap.xml');
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url(),
            'lastmod' => $this->lastmod(),
            'sitemaps' => $this->sitemaps()->map(fn (BaseLocaleSitemap $sitemap) => [
                'url' => $sitemap->url(),
                'lastmod' => $sitemap->lastmod(),
            ])->all(),
        ];
    }

    public function render(): string
    {
        return view('multidomain-sitemap::index', [
            'sitemaps' => $this->toArray()['sitemaps'],
            'site' => $this->site,
            'xslPath' => $this->xslPath(),
        ])->render();
    }

    public function toResponse($request)
    {
        return response(
            $this->file() ?? $this->render(),
            200,
            [
                'Content-Type' => 'text/xml',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        );
    }

    public function filename(): string
    {
        return 'sitemap.xml';
    }

    public function path(): string
    {
        return rtrim(config('multidomain-sitemap.path', storage_path('statamic/multidomain-sitemaps')), '/').'/'.$this->site->handle().'/'.$this->filename();
    }

    public function file(): ?string
    {
        if (! config('multidomain-sitemap.serve_from_cache', true)) {
            return null;
        }

        return File::exists($this->path()) ? File::get($this->path()) : null;
    }

    public function save(): self
    {
        File::ensureDirectoryExists(dirname($this->path()));

        File::put($this->path(), $this->render());

        return $this;
    }

    protected function xslPath(): string
    {
        $path = parse_url($this->site->url(), PHP_URL_PATH) ?? '';

        return rtrim($path, '/').'/sitemap.xsl';
    }
}
