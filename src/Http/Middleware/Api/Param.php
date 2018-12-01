<?php

namespace Core\Http\Middleware\Api;

use Closure;
use Core\Exception\ApiException;
use Illuminate\Http\Response;
use Api;
use Route;
use Request;
use Illuminate\Contracts\Routing\Registrar;
use Gate;
use Auth;
class Param
{
      /**
     * The router instance.
     *
     * @var \Illuminate\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new bindings substitutor.
     *
     * @param  \Illuminate\Contracts\Routing\Registrar  $router
     * @return void
     */
    public function __construct(Registrar $router)
    {
        $this->router = $router;
    }
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$params)
    {
        $param = Api::unserialize($params);
        $route = Route::getFacadeRoot()->current();

        $param->value = $request->input($param->name);

        $route = $request->route();

        if(isset($param->type))
        {
            if(class_exists($param->type))
            {
                if(!isset($param->prop))
                {
                    if(starts_with($param->name, "id_"))
                    {
                        $param->prop = substr($param->name, 3);
                    }else if(ends_with($param->name, "_id")){
                        $param->prop = substr($param->name, 0, -3);
                    }
                    if($param->array)
                    {
                        $param->prop = str_plural($param->prop);
                    }
                }
                if(isset($request->{$param->prop}))
                {
                    $model = $request->{$param->prop};
                    if(is_array($model))
                    {
                        $model = collect($model);
                    }
                    if($model instanceof \Illuminate\Database\Eloquent\Collection)
                    {

                        $param->value = $model->map(function($item)
                        {
                            return $item->getKey();
                        })->toArray();
                    }
                    else
                    {
                        $param->value = $model->getKey();
                    }
                }
                //TODO:allow to pass model to id_user instead of user too 
                // if(isset($request->{$param->name}))
                // {
                //     $model = $request->{$param->name};
                //     if(is_array($model))
                //     {
                //         $model = collect($model);
                //     }
                //     if($model instanceof \Illuminate\Database\Eloquent\Collection)
                //     {

                //         $param->value = $model->map(function($item)
                //         {
                //             return $item instanceof Model?$item->getKey():$item;
                //         })->toArray();
                        
                //     }
                //     else
                //     {
                //         $param->value = $model->getKey();
                //     }
                // }
            }
        }


        $value = $param->validate($param->value);
        if(!isset($value) && isset($param->default))
        {
            $value = $param->default;
        }


        if(isset($param->type))
        {
            if(class_exists($param->type))
            {
                $cls = $param->type;
                if(!isset($model))
                {
                    if(!isset($value))
                    {
                        $model = NULL;
                    }else
                    {
                        if(is_array($value))
                        {
                            //array => collection
                            $model = collect($value)->map(function($item) use($cls)
                            {
                               return app()->make($cls)->resolveRouteBinding($item);
                            });
                        }else {
                            $model =  app()->make($cls)->resolveRouteBinding($value);
                        }
                    }
                    if(isset($value) && ($param->flag_missing || $param->required) && !isset($model))
                    {
                        throw new ApiException('model_missing', $param->name . " model linked not found");
                    }
                }
                if($param->array)
                { 
                    if(!empty($value) && count($value) > $model->count() && ($param->flag_missing || $param->required))
                    {
                        $ids = $model->map(function($item){ return $item->getKey(); })->toArray();
                        $missing = array_diff($value, $ids);
                        throw new ApiException('model_missing', $param->name . " model linked not found - ".implode(",", $missing));
                    }
                }
                if(isset($param->policies) && isset($model))
                {
                    //must use a guest user
                    $user = Auth::user()??new \App\user;
                    foreach($param->policies as $policy)
                    {
                        if($param->array)
                        {
                            $model->each(function($item) use($user, $policy)
                            {
                                if(!$user->can($policy, $item))
                                {
                                    throw new ApiException('not_allowed_policy', 'current user is not allowed for '.$policy);
                                }
                            });
                        }else
                        if(!$user->can($policy, $model))
                        {
                            throw new ApiException('not_allowed_policy', 'current user is not allowed for '.$policy);
                        }
                    }
                }
                $param->type = "int";
            }
            
            if (null !== $value)
            {
                if($param->array)
                {
                    foreach($value as &$v)
                    {
                        if ($param->type === 'boolean')
                            $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                        else
                            settype($v, $param->type);
                    }
                }else
                {
                    if ($param->type === 'boolean')
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    else
                        settype($value, $param->type);
                }
            }
        }
        if(isset($param->prop))
        {
            $route->setParameter($param->prop, $model);
        }
        $route->setParameter($param->name, $value);
        $input = $request->all();
        if(!isset($input))
        {
            $input = [];
        }
        if(isset($value) || $param->required)
        {
            $input[$param->name] = $value;
            
            if(isset($param->prop))
            {
                if(isset($input[$param->prop]) && $input[$param->prop]!==$model)
                {
                    throw new ApiException($param->prop." already exists");
                }
                $input[$param->prop] = $model;
                // $route->setParameter($param->prop, $model);
            }else
            {
                // $route->setParameter($param->name, $value);
            }
        }
        // need both in order to keep that working for sub API request 
        $request->replace($input);
        Request::replace($input);
        
        return $next($request);
    }
}
