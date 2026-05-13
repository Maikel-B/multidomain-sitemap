<?php

namespace MaikelB\MultidomainSitemap\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use MaikelB\MultidomainSitemap\Sitemaps\LocaleSitemapIndex;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

class SitemapController extends Controller
{
    public function index(Request $request)
    {
        $site = $this->resolveSite($request);

        return $this->withCrawlingEnvironmentWidened(
            fn () => (new LocaleSitemapIndex($site))->toResponse($request)
        );
    }

    public function show(Request $request, string $id)
    {
        $site = $this->resolveSite($request);

        return $this->withCrawlingEnvironmentWidened(function () use ($site, $id, $request) {
            $sitemap = (new LocaleSitemapIndex($site))->find($id);
            throw_unless($sitemap, NotFoundHttpException::class);

            return $sitemap->toResponse($request);
        });
    }

    /**
     * Run $callback with the current app environment temporarily added to
     * aerni's crawling.environments list. Required because aerni gates the
     * IncludeInSitemap evaluator on that list — which is also the list that
     * controls noindex,nofollow robots meta on rendered pages. Widening it
     * project-wide on staging would let crawlers index staging pages, so we
     * widen it only for the duration of this sitemap request.
     */
    protected function withCrawlingEnvironmentWidened(callable $callback): mixed
    {
        $key = 'advanced-seo.crawling.environments';
        $original = Config::get($key, []);
        $current = app()->environment();

        if (! in_array($current, $original, true)) {
            Config::set($key, array_values(array_unique([...$original, $current])));
        }

        try {
            return $callback();
        } finally {
            Config::set($key, $original);
        }
    }

    public function xsl(): Response
    {
        return response(
            file_get_contents(__DIR__.'/../../../resources/views/sitemap.xsl'),
            200,
            [
                'Content-Type' => 'text/xsl',
                'X-Robots-Tag' => 'noindex, nofollow',
            ]
        );
    }

    protected function resolveSite(Request $request): \Statamic\Sites\Site
    {
        $handle = $request->route('site');

        $site = $handle ? Site::get($handle) : null;

        throw_unless($site, NotFoundHttpException::class);

        Site::setCurrent($site->handle());

        return $site;
    }
}
