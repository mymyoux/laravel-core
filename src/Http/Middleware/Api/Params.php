<?php

namespace Core\Http\Middleware\Api;

use Closure;
use Core\Exception\ApiException;
use Illuminate\Http\Response;
use Api;
use Route;
use Request;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Contracts\Routing\UrlRoutable;
class Params
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
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $params = array_keys($route->parameters);

        $signatures = array_filter($route->signatureParameters(), function($item) use($params)
        {
            return in_array($item->name, $params);
        });

      
        $parameters = [];
        foreach($signatures as $signature)
        {
            if(in_array($signature->name, $params))
            {
                $parameters[$signature->name] = $route->parameters[$signature->name]??False;
            }
        }
        
        $route->parameters = $parameters;

        
        return $next($request);
    }
}
