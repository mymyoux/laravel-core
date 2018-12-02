<?php

namespace Core\Http\Controllers;

use Core\Api\Annotations as myno;
use Auth;
use App\User;
use Storage;
use Illuminate\Http\Request;
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
     /**
     * @myno\Param(name="email", required=true, requirements="email")
     */
    public function emailExists($email)
    {
        $req = User::where(function($query) use($email)
        {
            $query->where('email', '=', $email);
            $query->orWhereHas('connectors',  function ($query) use($email) {
                $query->where('email', '=', $email);
            });
        });
        if(Auth::check())
            $req->where('id', '!=', Auth::id());
        return !!$req->first();
    }
     /**
     * @myno\Role("user")
     * @myno\Param(name="email",  requirements="email")
     * @myno\Param(name="name")
     */
    public function update(Request $request, $email, $name)
    {
        $data = $request->only('email', 'name');
        
        $user = Auth::user();
        foreach($data as $key=>$value)
        {
            $user->$key = $value;
        }
        $user->save();
        return $user;
    }
    public function onSignup($event)
    {

    }
}
