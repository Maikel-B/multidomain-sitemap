<?php

namespace MaikelB\MultidomainSitemap\Sitemaps\Urls;

use Aerni\AdvancedSeo\Models\Defaults;
use Aerni\AdvancedSeo\Support\Helpers;
use Illuminate\Support\Facades\Cache;
use MaikelB\MultidomainSitemap\Support\UrlNormalizer;
use Statamic\Contracts\Taxonomies\Taxonomy;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteInstance;

class TaxonomySitemapUrl
{
    public function __construct(protected Taxonomy $taxonomy, protected SiteInstance $site) {}

    public function loc(): string
    {
        Site::setCurrent($this->site->handle());

        return UrlNormalizer::normalize($this->taxonomy->absoluteUrl());
    }

    public function alternates(): ?array
    {
        if (! Site::multiEnabled()) {
            return null;
        }

        $sites = $this->taxonomy->sites();

        if ($sites->count() < 2) {
            return null;
        }

        $hreflang = $sites->map(function ($siteHandle) {
            Site::setCurrent($siteHandle);

            return [
                'href' => UrlNormalizer::normalize($this->taxonomy->absoluteUrl()),
                'hreflang' => Helpers::parseLocale(Site::current()->locale()),
            ];
        });

        $originSite = $this->taxonomy->sites()->first();
        $xDefaultSite = $sites->contains($originSite) ? $originSite : $this->site->handle();

        Site::setCurrent($xDefaultSite);

        return $hreflang->push([
            'href' => UrlNormalizer::normalize($this->taxonomy->absoluteUrl()),
            'hreflang' => 'x-default',
        ])->values()->all();
    }

    public function lastmod(): string
    {
        $term = $this->taxonomy->queryTerms()
            ->where('site', $this->site->handle())
            ->orderByDesc('last_modified')
            ->first();

        if ($term) {
            Cache::forget("maikel-b.multidomain-sitemap.taxonomy.{$this->taxonomy->handle()}.{$this->site->handle()}.lastmod");

            return $term->lastModified()->format('Y-m-d\TH:i:sP');
        }

        return Cache::rememberForever(
            "maikel-b.multidomain-sitemap.taxonomy.{$this->taxonomy->handle()}.{$this->site->handle()}.lastmod",
            fn () => now()->format('Y-m-d\TH:i:sP')
        );
    }

    public function changefreq(): ?string
    {
        return Defaults::data('taxonomies')->get('seo_sitemap_change_frequency');
    }

    public function priority(): ?string
    {
        return Defaults::data('taxonomies')->get('seo_sitemap_priority');
    }

    public function site(): string
    {
        return $this->site->handle();
    }

    public function canonicalTypeIsCurrent(): bool
    {
        return true;
    }

    public function toArray(): array
    {
        return [
            'loc' => $this->loc(),
            'alternates' => $this->alternates(),
            'lastmod' => $this->lastmod(),
            'changefreq' => $this->changefreq(),
            'priority' => $this->priority(),
            'site' => $this->site(),
        ];
    }
}
