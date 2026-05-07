<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | When disabled, the per-locale sitemap routes will not be registered.
    |
    */

    'enabled' => env('MULTIDOMAIN_SITEMAP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | The application environments in which sitemaps are served. In all other
    | environments the routes return a 404. Set to an empty array to allow
    | all environments.
    |
    */

    'environments' => ['production', 'staging'],

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The directory where the generated sitemap files (when pre-rendered via
    | the multidomain-sitemap:generate command) are stored. Per-site files
    | are written to a subdirectory matching the site handle.
    |
    */

    'path' => storage_path('statamic/multidomain-sitemaps'),

    /*
    |--------------------------------------------------------------------------
    | Cache Pre-rendered Files
    |--------------------------------------------------------------------------
    |
    | When true, the controller will serve pre-rendered files from the storage
    | path if they exist instead of rendering on each request. Use the
    | `multidomain-sitemap:generate` command to (re)generate them.
    |
    */

    'serve_from_cache' => true,

    /*
    |--------------------------------------------------------------------------
    | Trailing Slash
    |--------------------------------------------------------------------------
    |
    | When true, every entry, term and taxonomy URL emitted in the sitemap
    | (both <loc> values and hreflang alternates) is normalized to end with
    | a trailing slash. When false, any trailing slash is stripped (except
    | for the root "/"). Use this to match the canonical URL style of your
    | site so search engines index the same URL form they crawl.
    |
    */

    'trailing_slash' => true,

];
