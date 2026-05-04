<?php

namespace MaikelB\MultidomainSitemap;

use Illuminate\Support\Facades\Route;
use MaikelB\MultidomainSitemap\Commands\GenerateSitemapsCommand;
use MaikelB\MultidomainSitemap\Http\Controllers\SitemapController;
use MaikelB\MultidomainSitemap\Support\SiteResolver;
use Statamic\Facades\Site;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;

class MultidomainSitemapServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        GenerateSitemapsCommand::class,
    ];

    /**
     * We register routes manually below because we need a separate route
     * group per Statamic site (each with its own domain or path prefix).
     */
    protected $routes = false;

    public function bootAddon(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../resources/config/multidomain-sitemap.php',
            'multidomain-sitemap'
        );

        $this->publishes([
            __DIR__.'/../resources/config/multidomain-sitemap.php' => config_path('multidomain-sitemap.php'),
        ], 'multidomain-sitemap-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'multidomain-sitemap');

        $this->registerRoutes();
    }

    public function register(): void
    {
        parent::register();

        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }

    /**
     * Register a separate route group per site so each locale has its own
     * sitemap reachable at the correct host/path.
     *
     * Routes are pushed via Statamic::pushWebRoutes so they register AFTER
     * other addon routes (in particular aerni/advanced-seo's `/sitemap.xml`).
     * Because Laravel's RouteCollection overwrites duplicate URI entries with
     * the last one added, our per-locale routes win for the root site too.
     */
    protected function registerRoutes(): void
    {
        if (! config('multidomain-sitemap.enabled', true)) {
            return;
        }

        $environments = config('multidomain-sitemap.environments', []);
        if (! empty($environments) && ! in_array(app()->environment(), $environments, true)) {
            return;
        }

        Statamic::pushWebRoutes(function () {
            $sites = Site::all();
            $appHost = parse_url(config('app.url'), PHP_URL_HOST);

            foreach ($sites as $site) {
                [$host, $prefix] = SiteResolver::hostAndPrefix($site);

                $group = Route::name("multidomain-sitemap.{$site->handle()}.");

                if ($host && $host !== $appHost) {
                    $group->domain($host);
                }

                if ($prefix !== '') {
                    $group->prefix($prefix);
                }

                $group->group(function () use ($site) {
                    Route::get('sitemap.xml', [SitemapController::class, 'index'])
                        ->defaults('site', $site->handle())
                        ->name('index');

                    Route::get('sitemaps/{id}.xml', [SitemapController::class, 'show'])
                        ->defaults('site', $site->handle())
                        ->name('show');

                    Route::get('sitemap.xsl', [SitemapController::class, 'xsl'])
                        ->name('xsl');
                });
            }
        });
    }
}
