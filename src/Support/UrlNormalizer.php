<?php

namespace MaikelB\MultidomainSitemap\Support;

use Statamic\Facades\Site;

class UrlNormalizer
{
    /**
     * Normalize a URL according to the `multidomain-sitemap.trailing_slash`
     * and `multidomain-sitemap.strip_locale_prefix` config flags. Only the
     * path component is touched; query string and fragment are preserved.
     */
    public static function normalize(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = $user !== '' ? $user.$pass.'@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        $path = self::stripLocalePrefix($path);
        $path = self::normalizePath($path, $host !== '');

        return $scheme.$auth.$host.$port.$path.$query.$fragment;
    }

    protected static function stripLocalePrefix(string $path): string
    {
        if (! (bool) config('multidomain-sitemap.strip_locale_prefix', false)) {
            return $path;
        }

        $prefix = parse_url(Site::current()->url(), PHP_URL_PATH) ?? '';
        $prefix = '/'.trim($prefix, '/');

        if ($prefix === '/') {
            return $path;
        }

        // Only treat $prefix as a real segment boundary — `/uk` must match
        // `/uk/anything` or exactly `/uk`, never `/ukraine-policy/`.
        $exact = $path === $prefix;
        $segmentMatch = str_starts_with($path, $prefix.'/');

        if (! $exact && ! $segmentMatch) {
            return $path;
        }

        $remainder = substr($path, strlen($prefix));

        return $remainder === '' ? '/' : $remainder;
    }

    protected static function normalizePath(string $path, bool $hasHost): string
    {
        $trailingSlash = (bool) config('multidomain-sitemap.trailing_slash', true);

        if ($trailingSlash) {
            if ($path === '') {
                return $hasHost ? '/' : '';
            }

            return str_ends_with($path, '/') ? $path : $path.'/';
        }

        if (strlen($path) > 1 && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }
}
