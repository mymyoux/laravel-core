<?php

namespace Core\Exception;

use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if($request->ajax() || $request->headers->get('Content-Type') == 'application/json' || mb_strpos($request->headers->get('accept'), 'application/json')!==False)
        {
            //only not found for now - others are handle by api or by laravel
            if($exception instanceof NotFoundHttpException)
            {
                $message = $exception->getMessage();
                $code = method_exists($exception, "getStatusCode")?$exception->getStatusCode():NULL;
                $message = "not_found";
                return response()->json(['data'=>
                    ['success'=>False,
                    'exception'=>'NotFoundHttpException',
                     'message'=>$message, 'code'=>$code]
                ]);
            }
        }
        return parent::render($request, $exception);
    }
}
