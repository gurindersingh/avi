<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('blade.compiler', function () {
            return new BladeCompiler(
                $this->app['files'], __DIR__ . '/../../stubs/views'
            );
        });
    }
}
