<?php
namespace Core\Http\Controllers\Auth\Connectors;

use DB;

class Manual extends Connector
{
    public function rawuser()
    {
        if(!isset($this->rawuser))
        {
           $this->rawuser = std(request()->all());
           if(isset($this->rawuser->password))
           {
                $this->rawuser->password_crypt = password_hash($this->rawuser->password, \PASSWORD_DEFAULT);
           }
        }
        return $this->rawuser;
    }
    public function getAdditionalColumns()
    {
        return ['password_crypt'=>'password'];
    }
    public function hasPassword()
    {
        $dbuser = $this->getDBUser();
        if(!isset($dbuser))
            return False;

        $row = DB::table('connector_user_manual')
        ->where('user_id','=',$dbuser->getKey())
        ->first();
        //if no manual row
        if(!isset($row))
            return False;
        return True;
    }
    public function isPasswordValid()
    {
        $user = $this->user();
        if(!isset($user->password))
        {
            return False;
        }
        $dbuser = $this->getDBUser();
        if(!isset($dbuser))
            return False;

        $row = DB::table('connector_user_manual')
        ->where('user_id','=',$dbuser->getKey())
        ->first();
        //if no manual row
        if(!isset($row))
            return False;

        if(password_verify($user->password, $row->password))
        {
            return True;
        }
        return False;
    }
}