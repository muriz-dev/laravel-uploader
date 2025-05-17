<?php

namespace Overtrue\LaravelUploader;

use Illuminate\Support\Facades\Facade;

class LaravelUploader extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-uploader';
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

use Illuminate\Support\ServiceProvider;
use Overtrue\LaravelUploader\Routing\UploaderRouteRegistrar;

class UploadServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Mendaftarkan singleton untuk uploader
        $this->app->singleton('laravel-uploader', function ($app) {
            return new UploaderService();
        });

        // Mendaftarkan route registrar
        $this->app->singleton('uploader.route.registrar', function ($app) {
            return new UploaderRouteRegistrar($app);
        });
    }

    public function boot()
    {
        $this->loadConfig();
        $this->loadTranslations();
        
        // Menyediakan method helper untuk mendaftarkan rute
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/uploader.php' => config_path('uploader.php'),
            ]);
        }
    }

    protected function loadConfig()
    {
        $this->publishes([
            __DIR__.'/../config/uploader.php' => config_path('uploader.php'),
        ]);
    }

    protected function loadTranslations()
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'uploader');

        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/uploader'),
        ]);
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
