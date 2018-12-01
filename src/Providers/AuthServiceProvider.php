<?php

namespace Core\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Auth\Access\Gate as GateAuth;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
class AuthServiceProvider extends ServiceProvider
{

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerAccessGate();
        $this->registerPolicies();
        
        //Automatically bound gate to policies (view, create, update, delete)
        //Example: App\Model\Post => posts.view, posts.create, posts.update, posts.delete
        foreach($this->policies as $key=>$policy)
        {
            $name = str_replace(
                '\\', '', Str::snake(Str::plural(last(explode('\\',$key))))
            );
            Gate::resource($name, $policy);
        }
    }
    protected function registerAccessGate()
    {
        $this->app->singleton(GateContract::class, function ($app) {
            return new GateAuth($app, function () use ($app) {
                $user = call_user_func($app['auth']->userResolver());
                if(!isset($user))
                {
                    //guest user
                    return new \App\user;
                }
                return $user;
            });
        });
    }
}
