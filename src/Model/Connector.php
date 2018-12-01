<?php

namespace Core\Model;
use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    public static function addConnector($user, $connector)
    {
        $keys = $this->getConnectorKeys();
        $rawdata = $connector->getConnectorData();
        $data = new \stdClass;
        foreach($keys as $key)
        {
            if(isset($rawdata->$key))
            {
                $data->$key = $rawdata->$key;
            }
        }
        if(!$connector->isMultiple())
        {
            DB::table('connector_user')
            ->where('user_id','=',$user->getKey())
            ->where('connector_id','=',$connector->getKey())
            ->delete();
        }else
        if(isset($rawdata->id) || isset($rawdata->email))
        {
            $request = DB::table('connector_user')
            ->where('user_id','=',$user->getKey())
            ->where('connector_id','=',$connector->getKey());
            $request->where(function($query) use($rawdata)
            {
                if(isset($rawdata->id))
                {
                    $query->whereOr('id','=',$rawdata->id);
                }
                if(isset($rawdata->email))
                {
                    $query->whereOr('email','=',$rawdata->email);
                }
            });
            $request->delete();
        }
        $user->connectors()->attach($connector->getKey(), (array)$data);
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    public function users()
    {
        return $this->belongsToMany('App\User');
    }
}
