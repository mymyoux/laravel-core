<?php

namespace Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Artisan;
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if(config('database.connections.mysql.charset') == 'utf8mb4')
        {
            Schema::defaultStringLength(191);
        }

  
        
        //only execute it once
        if ($this->app->runningInConsole() && count($_SERVER['argv'])>1) {
            if($_SERVER['argv'][1] == 'package:discover' && config('front') === NULL)
            {
                \Core\Scripts::postInstall();
                $this->publishes([
                    __DIR__.'/../../config/export.php' => config_path('export.php'),
                    __DIR__.'/../../config/front.php' => config_path('front.php'),
                ], 'myno');
                Artisan::call('vendor:publish',['--tag'=>'myno']);
            }
        }
        
    }
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerApi();
        $this->registerLogger();

    }
    protected function registerApi()
    {
        $this->app->singleton('api', '\Core\Api\Api');
    }
    protected function registerLogger()
    {
        $this->app->singleton('logger', '\Core\Services\Logger');
    }

}

