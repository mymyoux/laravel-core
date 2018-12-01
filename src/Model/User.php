<?php

namespace Core\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\ Authenticatable;
use Storage;
use Illuminate\Foundation\Auth\Access\Authorizable;
/**
 * Doc
 */
class User extends \Core\Database\Eloquent\Model implements AuthenticatableContract
{
    use Authorizable;
    use Notifiable;
    use Authenticatable;
    protected $_is_guest = False;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token'
    ];
    protected $appends = ['avatar_url'];
    public function connectors()
    {
        return $this->belongsToMany('Core\Model\Connector');
    }
    /**
     * test
     *
     * @return void
     */
    public function getAvatarUrlAttribute()
    {
        if(!isset($this->avatar))
        {
            return NULL;
        }
            if(starts_with($this->avatar,'http'))
        {
            return $this->avatar;
        }
        return config('app.url').Storage::url($this->avatar);
    }

    public function getAvatarAttribute()
    {
        if(!isset($this->attributes['avatar']))
        {
            return NULL;
        }
            if(starts_with($this->attributes['avatar'],'http'))
        {
            return $this->attributes['avatar'];
        }
        return config('app.url').Storage::url($this->attributes['avatar']);
    }
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }
    public function hasRole($name)
    {
        foreach($this->roles as $role)
        {
            //admin have all rights
            if($role->name == 'admin')
                return True;

            if($role->name == $name)
                return True;
        }
        return False;
    }

    public function isGuest()
    {
        return $this->getKey() === NULL;
    }
}
//test ok