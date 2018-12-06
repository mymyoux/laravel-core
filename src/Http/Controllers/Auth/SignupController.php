<?php

namespace Core\Http\Controllers\Auth;
use Core\Api\Annotations as myno;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite; 
use Core\Http\Controllers\Auth\Connectors\Connector;
use DB;
use App\User;
use Illuminate\Http\Request;
use URL;
use Storage;
use Illuminate\Http\File;
use Core\Exception\ApiException;
use App;
use Illuminate\Auth\Events\Registered;

class SignupController extends \Core\Http\Controllers\Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }
    /**
     * Will redirect user to the social site register page
     * @return void
     */
    public function register($api)
    {
       $this->configureSocialite($api);
        return Socialite::driver($api)->redirect();
    }

    /**
     * Callback afer social register website
     * @param string $api Social site name or manual
     * @param Request $request
     * @return void
     */
    public function callback($api, Request $request)
    {
        $this->configureSocialite($api);
        $connector = Connector::get($api);
        $user = $connector->user();
        $dbuser = $connector->getDBUser();
        if(isset($dbuser))
        {
            $connector->addToUser($dbuser);

            //TODO: redirect home
            $request->session()->flash('login.user_id', $dbuser->getKey());
            return redirect()->route('login.callback', ['api' => "session"]);
        }
        $dbuser = $this->createUser($connector);
        $request->session()->flash('login.user_id', $dbuser->getKey());
        return redirect()->route('login.callback', ['api' => "session"]);
    }
    /**
     * @myno\Param(name="email",requirements="email",required=true)
     * @myno\Param(name="password", required=true)
     * @myno\Param(name="name",required=true)
     * @return void
     */
    public function check(Request $request)
    {
        $connector = Connector::get('manual');
        $user = $connector->user();
        $dbuser = $connector->getDBUser();
        if(isset($dbuser))
        {
            throw new ApiException('already_registered');
        }
        if(mb_strlen($user->password) < 8)
        {
            throw new ApiException('password_short');
        }
        return true;
    }
    /**
     * @myno\Middleware("App\Http\Middleware\EncryptCookies")
     * @myno\Middleware("Illuminate\Session\Middleware\StartSession")
     * @myno\Param(name="email",requirements="email",required=true)
     * @myno\Param(name="password", required=true)
     * @myno\Param(name="name",required=true)
     * @return void
     */
    public function manual(Request $request)
    {
        $connector = Connector::get('manual');
        $user = $connector->user();
        
        try
        {
            //will throw an exception 
            if(!$this->check($request))
            {
                return false;
            }
        }catch(\Exception $e)
        {
            //redirect if login correct
            if($e->getMessage()=='[API Exception] already_registered')
            {
                if($connector->isPasswordValid())
                {
                    return App::call('App\Http\Controllers\Auth\LoginController@manual', [$request]);
                }
            }
            throw $e;
        }
        $user = $this->createUser($connector);
        return App::call('App\Http\Controllers\Auth\LoginController@manual', [$request]);
    }
    /**
     * List all user columns editable on register
     */
    protected function getUserKeys()
    {
        $columns = User::getColumnList();

        $columns = array_diff($columns, ['id','created_at','updated_at','remember_token']);
        $columns = array_values(array_filter($columns, function($item)
        {
            return !ends_with($item, '_id');
        }));
        return $columns;
    }
    /**
     * Create user from connector
     */
    protected function createUser($connector)
    {
        $user = new User;
        //protect for half registered users
        DB::transaction(function () use($user, $connector) {
            $api_user = $connector->user();
            $keys = $this->getUserKeys();
            foreach($keys as $key)
            {
                if(isset($api_user->$key))
                {
                    $user->$key = $api_user->$key;
                }
            }
            $user->save();
            event(new Registered($user));
          
            $connector->addToUser($user);
        });


        //TODO:event register
        return $user;
    }
    /**
     * Configure each services of socialite (generate clalback url)
     */
    protected function configureSocialite($api)
    {
        //nothing to do
        if($api == 'manual')
            return;
        $config = 'services.'.$api.'.redirect';
        $redirect = config($config);
        if(!isset($redirect))
        {
            $redirect = route('signup.callback',['api'=>$api]);
            config([$config=>$redirect]);
        }
        if(config('services.'.$api.'.client_id') === NULL)
        {
            throw new \Exception('Bad connector');
        }
    }
}
