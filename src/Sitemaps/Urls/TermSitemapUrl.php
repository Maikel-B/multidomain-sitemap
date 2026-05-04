<?php

namespace MaikelB\MultidomainSitemap\Sitemaps\Urls;

use Aerni\AdvancedSeo\Actions\IncludeInSitemap;
use Aerni\AdvancedSeo\Support\Helpers;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Facades\Site;

class TermSitemapUrl
{
    public function __construct(protected Term $term) {}

    public function loc(): string
    {
        return $this->term->absoluteUrl();
    }

    public function alternates(): ?array
    {
        if (! Site::multiEnabled()) {
            return null;
        }

        $terms = $this->term->term()
            ->localizations()
            ->filter(IncludeInSitemap::run(...));

        if ($terms->count() < 2) {
            return null;
        }

        $hreflang = $terms->map(fn ($term) => [
            'href' => $term->absoluteUrl(),
            'hreflang' => Helpers::parseLocale($term->site()->locale()),
        ]);

        $origin = $this->term->origin();

        $xDefault = $origin && IncludeInSitemap::run($origin) ? $origin : $this->term;

        return $hreflang->push([
            'href' => $xDefault->absoluteUrl(),
            'hreflang' => 'x-default',
        ])->values()->all();
    }

    public function lastmod(): ?string
    {
        return $this->term->lastModified()?->format('Y-m-d\TH:i:sP');
    }

    public function changefreq(): ?string
    {
        return $this->term->seo_sitemap_change_frequency;
    }

    public function priority(): ?string
    {
        $priority = $this->term->seo_sitemap_priority;

        if ($priority === null) {
            return null;
        }

        $value = is_object($priority) && method_exists($priority, 'value') ? $priority->value() : $priority;

        return number_format((float) $value, 1);
    }

    public function site(): string
    {
        return $this->term->locale();
    }

    public function canonicalTypeIsCurrent(): bool
    {
        $value = $this->term->seo_canonical_type;

        if (is_object($value) && method_exists($value, 'value')) {
            $value = $value->value();
        }

        return (string) $value === 'current';
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
