<?php

namespace MaikelB\MultidomainSitemap\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use MaikelB\MultidomainSitemap\Sitemaps\LocaleSitemapIndex;
use Statamic\Exceptions\NotFoundHttpException;
use Statamic\Facades\Site;

class SitemapController extends Controller
{
    public function index(Request $request)
    {
        $site = $this->resolveSite($request);

        return new LocaleSitemapIndex($site);
    }

    public function show(Request $request, string $id)
    {
        $site = $this->resolveSite($request);

        $sitemap = (new LocaleSitemapIndex($site))->find($id);

        throw_unless($sitemap, NotFoundHttpException::class);

        return $sitemap;
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
