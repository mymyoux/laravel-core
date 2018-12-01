<?php

namespace Core\Http\Controllers;

use Core\Api\Annotations as myno;
use Auth;
use App\User;
use Storage;
class UserController extends Controller
{
    /**
     * @myno\Middleware("App\Http\Middleware\EncryptCookies")
     * @myno\Middleware("Illuminate\Session\Middleware\StartSession")
     */
    public function me()
    {
        return Auth::user();
    }
    public function onSignup($event)
    {

    }
}
