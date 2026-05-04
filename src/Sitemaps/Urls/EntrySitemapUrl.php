<?php

namespace MaikelB\MultidomainSitemap\Sitemaps\Urls;

use Aerni\AdvancedSeo\Actions\IncludeInSitemap;
use Aerni\AdvancedSeo\Support\Helpers;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;

class EntrySitemapUrl
{
    public function __construct(protected Entry $entry) {}

    public function loc(): string
    {
        return $this->entry->absoluteUrl();
    }

    public function alternates(): ?array
    {
        if (! Site::multiEnabled()) {
            return null;
        }

        $sites = $this->entry->sites();

        if ($sites->count() < 2) {
            return null;
        }

        $hreflang = $sites
            ->map(fn ($locale) => $this->entry->in($locale))
            ->filter()
            ->filter(IncludeInSitemap::run(...));

        if ($hreflang->count() < 2) {
            return null;
        }

        $hreflang = $hreflang->map(fn ($model) => [
            'href' => $model->absoluteUrl(),
            'hreflang' => Helpers::parseLocale($model->site()->locale()),
        ]);

        $origin = $this->entry->origin() ?? $this->entry;

        $xDefault = IncludeInSitemap::run($origin) ? $origin : $this->entry;

        return $hreflang->push([
            'href' => $xDefault->absoluteUrl(),
            'hreflang' => 'x-default',
        ])->values()->all();
    }

    public function lastmod(): ?string
    {
        return $this->entry->lastModified()?->format('Y-m-d\TH:i:sP');
    }

    public function changefreq(): ?string
    {
        return $this->entry->seo_sitemap_change_frequency;
    }

    public function priority(): ?string
    {
        $priority = $this->entry->seo_sitemap_priority;

        if ($priority === null) {
            return null;
        }

        $value = is_object($priority) && method_exists($priority, 'value') ? $priority->value() : $priority;

        return number_format((float) $value, 1);
    }

    public function site(): string
    {
        return $this->entry->locale();
    }

    public function canonicalTypeIsCurrent(): bool
    {
        $value = $this->entry->seo_canonical_type;

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
