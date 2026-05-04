@php
echo '<?xml version="1.0" encoding="utf-8"?>';
echo '<?xml-stylesheet type="text/xsl" href="'.e($xslPath).'"?>';
@endphp

<sitemapindex
    xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
    multisite-sitemap-site="{{ $site->handle() }}"
>
    @foreach ($sitemaps as $sitemap)
        <sitemap>
            <loc>{{ $sitemap['url'] }}</loc>
            @isset($sitemap['lastmod'])
                <lastmod>{{ $sitemap['lastmod'] }}</lastmod>
            @endisset
        </sitemap>
    @endforeach
</sitemapindex>
