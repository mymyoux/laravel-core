<?php

namespace Core\Http\Middleware\Api;

use App\User;
use Core\Exception\ApiException;
use Core\Model\User\Token\One;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Core\Exception\Exception as CoreException;
use Closure;
use Auth;
use Api;
use App;
use URL;
use Request;
class ApiMiddleware
{

    protected function decode($input, $all = false)
    {
        foreach($input as $key=>$value)
        {
            if($all || (is_string($value) && (starts_with($value, "[") || starts_with($value, "{") || starts_with($value, '"')) ))
            {
                if(!is_string($value))
                    continue;
                $tmp = json_decode($value, True);
                if(json_last_error() ==  \JSON_ERROR_NONE)
                {
                    $input[$key] = $tmp;
                }else
                {
                    $input[$key] = $value;
                }
            }
                if(is_array($input[$key]))
                {
                    
                    $input[$key] = $this->decode($input[$key], $all);
                }
        }
       // dd($input);
        return $input;
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
        //convert json data to array
        $input = $request->all();
        if(isset($input['__type']))
            unset($input['__type']);
         //   dd($request->__type);
        $input = $this->decode($input, isset($request->__type) && $request->__type == 'json');
       // dd($input);
        $request->replace($input);
        Request::replace($input);

        //TODO:change by token
        $user_id  = $request->input('user_id');
        if(isset($user_id))
        {
            Auth::loginUsingId($user_id);
        }
        $return = $next($request);
        $api_data = Api::popApiData();
        
        if($return instanceof RedirectResponse || $return instanceof BinaryFileResponse)
        {
            return $return;
        }    
        $result = [];
        if($return instanceof Response)
        {
            if(isset($return->exception))
            {
                //$result['exception'] = $return->exception;
                if(!($return->exception instanceof CoreException))
                {
                    $result['exception'] = 
                    [
                        'message'=>$return->exception->getMessage(),
                        'file'=>$return->exception->getFile(),
                        'line'=>$return->exception->getLine(),
                        'trace'=>explode("\n",$return->exception->getTraceAsString()),
                    ];
                }else {
                    # code...
                    $result['exception'] = $return->exception->toJsonObject();
                }
                if(App::environment('production'))
                {
                    $result['exception'] = ['message'=>$result['exception']['message']];
                }
            }else
            {
                $result['data'] = $return->getOriginalContent();
            }
        }else
        if($return instanceof JsonResponse)
        {
            $paginate = $return->getOriginalContent();
            if($paginate instanceof \Illuminate\Pagination\LengthAwarePaginator)
            {
                $paginate = (array)$return->getData();
                $result['data'] = $paginate['data'];
                unset($paginate['data']);
                $api_data = array_merge($api_data, $paginate);
            }else
            {
                $result['data'] = $return->getData();
            }
        }
        $result['api_data'] = $api_data;
        

        
        $response = new JsonResponse($result);

        $origin = URL::previous()??'*';
        if($origin != '*')
        {
            $parts = parse_url($origin);
            $origin = $parts['scheme'].'://'.$parts['host'];
            if(isset($parts['port']))
                $origin.=':'.$parts['port'];
        }
        if(ends_with($origin, '/'))
        {
            $origin = mb_substr($origin, 0, -1);
        }

        // $response = $response->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
        // $response = $response->header('Access-Control-Allow-Origin', $origin);
        // $response = $response->header('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
