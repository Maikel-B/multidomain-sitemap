<?php

namespace MaikelB\MultidomainSitemap\Support;

use Statamic\Sites\Site;

class SiteResolver
{
    /**
     * Return [host, pathPrefix] for a Statamic site.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function hostAndPrefix(Site $site): array
    {
        $url = $site->url();

        $host = parse_url($url, PHP_URL_HOST) ?: null;
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return [$host, trim($path, '/')];
    }
}
