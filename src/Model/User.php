<?php

namespace Core\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\ Authenticatable;
use Storage;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\SoftDeletes;
/**
 * Doc
 */
class User extends \Core\Database\Eloquent\Model implements AuthenticatableContract
{
    use SoftDeletes;
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
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if(isset($model->uuidPrefix))
            {
                $short = $model->uuidPrefix;
            }else
            {
                $short = mb_substr($model->getTable(), 0, 5);
            }
            $model->token = $short.'-'.generate_token();
        });
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
        //default role
        if($name == 'user')
            return !$this->isGuest();
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