<?php

namespace MaikelB\MultidomainSitemap\Support;

class UrlNormalizer
{
    /**
     * Normalize a URL according to the `multidomain-sitemap.trailing_slash`
     * config flag. Only the path component is touched; query string and
     * fragment are preserved untouched.
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

        $path = self::normalizePath($path, $host !== '');

        return $scheme.$auth.$host.$port.$path.$query.$fragment;
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
