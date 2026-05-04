<?php

namespace MaikelB\MultidomainSitemap\Sitemaps;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Facades\URL;
use Statamic\Sites\Site;

abstract class BaseLocaleSitemap implements Arrayable, Renderable, Responsable
{
    protected Site $site;

    abstract public function urls(): Collection;

    abstract public function handle(): string;

    abstract public function type(): string;

    public function site(): Site
    {
        return $this->site;
    }

    public function id(): string
    {
        return Str::slug("{$this->type()}-{$this->handle()}");
    }

    public function filename(): string
    {
        return "{$this->id()}.xml";
    }

    /**
     * Public URL of this sitemap, served at the site's URL prefix.
     */
    public function url(): string
    {
        return URL::tidy(rtrim($this->site->absoluteUrl(), '/')."/sitemaps/{$this->filename()}");
    }

    public function lastmod(): ?string
    {
        return $this->urls()
            ->sortByDesc(fn ($url) => $url->lastmod())
            ->first()?->lastmod();
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url(),
            'lastmod' => $this->lastmod(),
            'urls' => $this->urls()->map->toArray()->values()->all(),
        ];
    }

    public function render(): string
    {
        return view('multidomain-sitemap::show', [
            'urls' => $this->toArray()['urls'],
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

    /**
     * Path of the XSL stylesheet relative to the site's URL prefix so it
     * resolves correctly under both root and prefixed sites.
     */
    protected function xslPath(): string
    {
        $path = parse_url($this->site->url(), PHP_URL_PATH) ?? '';

        return rtrim($path, '/').'/sitemap.xsl';
    }
}
