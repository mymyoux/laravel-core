<?php

namespace Core\Http\Controllers\Auth;
use Socialite; 
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Core\Http\Controllers\Auth\Connectors\Connector;
use Auth;
use Illuminate\Http\Request;
use App\User;
use Core\Api\Annotations as myno;
use Core\Exception\ApiException;

class LoginController extends \Core\Http\Controllers\Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
       // $this->middleware('guest')->except('logout');
    }
    protected function configureSocialite($api)
    {
        $config = 'services.'.$api.'.redirect';
        $redirect = config($config);
        if(!isset($redirect))
        {
            $redirect = route('login.callback',['api'=>$api]);
            config([$config=>$redirect]);
        }
        if(config('services.'.$api.'.client_id') === NULL)
        {
            throw new \Exception('Bad connector');
        }
    }
    /**
     * @myno\Middleware("App\Http\Middleware\EncryptCookies")
     * @myno\Middleware("Illuminate\Session\Middleware\StartSession")
     * @myno\Param(name="email",requirements="email",required=true)
     * @myno\Param(name="password", required=true)
     * @return void
     */
    public function manual(Request $request)
    {
        $connector = Connector::get('manual');
        $user = $connector->user();
        $dbuser = $connector->getDBUser();
        if(!isset($dbuser))
        {
            throw new ApiException('bad_email_or_password');
        }
        if(!$connector->hasPassword())
        {
            throw new ApiException('bad_method');
        }
        //TODO:return a valid connector

        if(!$connector->isPasswordValid())
        {
            throw new ApiException('bad_email_or_password');
        }
        Auth::login($dbuser, true);
        //TODO:event login

        return redirect('user/me');
    }
    public function authenticate($api)
    {
        $this->configureSocialite($api);
        if(Auth::check())
        {
            return redirect()->route('signup', ['api'=>$api]);
        }
        if($api == 'manual')
        {

        }else
        {
            return Socialite::driver($api)->redirect();
        }
    }
    public function callback($api, Request $request)
    {
        if($api == 'session')
        {
            $user_id = session('login.user_id');

            if(!isset($user_id))
            {
                throw new \Exception('no_user_id');
            }
            Auth::loginUsingId($user_id, true);
            if(!Auth::check())
            {
                throw new \Exception('bad_user_id');
            }
            return $this->onLogin();
        }
        $this->configureSocialite($api);
        $connector = Connector::get($api);
        if($api == 'manual')
        {

        }else
        {
            $dbuser = $connector->getDBUser();
            //TODO: Faire la difference entre trouve juste l'user par l'email ou juste l'user
            dd($dbuser);
            //register
            if(!isset($dbuser))
            {
                $request->session()->flash($api.'.user', $connector->user());
                return redirect()->route('signup.callback', ['api'=>$api]);
            }
            Auth::login($dbuser, true);
            //login with api
            return $this->onLogin();
        }

    }
    /**
     * @myno\Route("user/logout")
     * @myno\Middleware("App\Http\Middleware\EncryptCookies")
     * @myno\Middleware("Illuminate\Session\Middleware\StartSession")
     */
    public function logoutAPI()
    {
        Auth::logout();
    }
    protected function logout()
    {
        Auth::logout();
        return redirect(config('app.home_url'));
    }
    protected function onLogin()
    {
        //TODO:event login
        return redirect(config('app.home_url'));
    }
}
