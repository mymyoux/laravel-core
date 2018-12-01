<?php

namespace Core\Http\Controllers;

use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Socialite; 
use DB;
use App\User;
use Illuminate\Http\Request;
use Core\Model\Connector;
use URL;
use Core\Api\Annotations as myno;
class ConnectorController extends \Core\Http\Controllers\Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('guest');
    }
    /**
     * @myno\Api 
     */
    public function signup()
    {
        return Connector::where('signup','=',1)
        ->where('name','!=','manual')
        ->get();
    }
    /**
     * @myno\Api 
     */
    public function login()
    {
        return Connector::where('login','=',1)
        ->where('name','!=','manual')
        ->get();
    }
}
