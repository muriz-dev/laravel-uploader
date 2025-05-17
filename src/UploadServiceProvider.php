<?php

namespace Overtrue\LaravelUploader\Providers;

use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelUploader\Routing\UploaderRouteRegistrar;
use Overtrue\LaravelUploader\UploaderService;

class UploadServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Mendaftarkan service provider lainnya
        $this->app->register(ConfigServiceProvider::class);
        $this->app->register(TranslationServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        // Mendaftarkan singleton untuk uploader service
        $this->app->singleton('laravel-uploader', function ($app) {
            return new UploaderService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Tidak melakukan apapun karena tanggung jawab sudah didelegasikan
        // ke provider yang lebih spesifik
    }
}

namespace Overtrue\LaravelUploader\Providers;

use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/uploader.php' => config_path('uploader.php'),
        ], 'uploader-config');

        $this->mergeConfigFrom(
            __DIR__.'/../../config/uploader.php', 'uploader'
        );
    }
}

namespace Overtrue\LaravelUploader\Providers;

use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'uploader');

        $this->publishes([
            __DIR__.'/../../resources/lang' => resource_path('lang/vendor/uploader'),
        ], 'uploader-translations');
    }
}

namespace Overtrue\LaravelUploader\Providers;

use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelUploader\Routing\UploaderRouteRegistrar;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('uploader.route.registrar', function ($app) {
            return new UploaderRouteRegistrar($app);
        });
    }
}

namespace Overtrue\LaravelUploader\Routing;

use Illuminate\Contracts\Container\Container;

class UploaderRouteRegistrar
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected $app;

    /**
     * Create a new route registrar instance.
     *
     * @param Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Register the uploader routes.
     *
     * @param array $options
     * @return void
     */
    public function register(array $options = [])
    {
        if (!$this->app->routesAreCached()) {
            $this->app->make('router')->post(
                'files/upload',
                array_merge([
                    'uses' => '\Overtrue\LaravelUploader\Http\Controllers\UploadController',
                    'as' => 'file.upload',
                ], $options)
            );
        }
    }
}

namespace Overtrue\LaravelUploader;

class UploaderService
{
    /**
     * Register the uploader routes.
     *
     * @param array $options
     * @return void
     */
    public function routes(array $options = [])
    {
        app('uploader.route.registrar')->register($options);
    }
}