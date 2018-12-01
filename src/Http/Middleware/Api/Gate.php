<?php

namespace Core\Http\Middleware\Api;

use Closure;
use Core\Exception\ApiException;
use Illuminate\Http\Response;
use Api;
use Route;
use Request;
use Illuminate\Contracts\Routing\Registrar;
use Gate as GateService;
use Auth;
class Gate
{
    /*
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$params)
    {
        $param = Api::unserialize($params);
        foreach($param->allows as $gate)
        {
            if(!GateService::has($gate))
            {
                throw new \Exception('gate '.$gate.' doesn\'t exist');
            }
            if(!GateService::allows($gate, $request->route()))
            {
                throw new ApiException('not_allowed_gate', 'current user is not allowed for '.$gate);
            }
        }
        return $next($request);
    }
}
