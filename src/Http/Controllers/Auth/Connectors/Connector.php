<?php
namespace Core\Http\Controllers\Auth\Connectors;
use Socialite;
use Core\Model\Connector as ConnectorModel;
use App\User;
use DB;
use Carbon\Carbon;
use Core\Events\ConnectorAdded;

class Connector
{
    protected $api;
    protected $model;
    protected $rawuser;
    public function __construct($api)
    {
        $this->api = $api;
    }
    static function get($api)
    {
        $name = 'Core\Http\Controllers\Auth\Connectors\\'.ucfirst($api);
        return new $name($api);
    }
    public function user()
    {
        return $this->rawuser();
    }
    public function rawuser()
    {
        if(!isset($this->rawuser))
        {
            $this->rawuser = session($this->api.'.user');
            if(!isset($this->rawuser))
                $this->rawuser =  Socialite::driver($this->api)->user();
            if(isset($this->rawuser->id))
                $this->rawuser->api_id = $this->rawuser->id;
        }
        return $this->rawuser;
    }
    public function getName()
    {
        return $this->api;
    }
    public function getModel()
    {
        if(!isset($this->model))
        {
            $this->model = ConnectorModel::where('name','=',$this->api)->first();
        }
        return $this->model;
    }
    public function getConnectorData()
    {
       return $this->rawuser();
    }
    public function isMultiple()
    {
        return !!$this->getModel()->multiple;
    }
    public function getKey()
    {
        return $this->getModel()->getKey();
    }
    protected function getConnectorKeys()
    {
        return ['api_id','email','scopes','access_token','refresh_token'];
    }
    /**
     * add Connector to user
     */
    public function addToUser($user)
    {
        $keys = $this->getConnectorKeys();
        $rawdata = $this->getConnectorData();
        $data = new \stdClass;
        foreach($keys as $key)
        {
            if(isset($rawdata->$key))
            {
                $data->$key = $rawdata->$key;
            }
        }
        // dd(["data"=>$data, "rawdata"=>$rawdata, "key"=>$keys]);
        if(!$this->isMultiple())
        {
            DB::table('connector_user')
            ->where('user_id','=',$user->getKey())
            ->where('connector_id','=',$this->getKey())
            ->delete();
        }else
        if(isset($rawdata->id) || isset($rawdata->email))
        {
            $request = DB::table('connector_user')
            ->where('user_id','=',$user->getKey())
            ->where('connector_id','=',$this->getKey());
            $request->where(function($query) use($rawdata)
            {
                if(isset($rawdata->id))
                {
                    $query->whereOr('api_id','=',$rawdata->id);
                }
                if(isset($rawdata->email))
                {
                    $query->whereOr('email','=',$rawdata->email);
                }
            });
            $request->delete();
        }
        $user->connectors()->attach($this->getKey(), (array)$data);
        $user_connector_id = DB::getPdo()->lastInsertId();




        //TODO:attach name/avatar/firstname/lastname




        event(new ConnectorAdded($this, $user));

        $columns = $this->getAdditionalColumns();


        //no additional data
        if(empty($columns))
            return;

        $table = $this->getAdditionalTable();
        $insert = ['user_id'=>$user->getKey(), 'connector_user_id'=>$user_connector_id,'created_at'=>Carbon::now(), 'updated_at'=>Carbon::now()];
        foreach($columns as $key=>$column)
        {
            $from = $key;
            if(is_numeric($key))
            {
                $from = $column;
            }
            if(isset($rawdata->$from))
            {
                $insert[$column] = $rawdata->$from;
            }
        }
        DB::table($table)->insert($insert);
    }
    public function getAdditionalTable()
    {
        return 'connector_user_'.$this->api;
    }
    public function getAdditionalColumns()
    {
        return [];
    }
    public function hasMigration()
    {
        return !empty($this->getAdditionalColumns());   
    }
    public function getMigrationPath()
    {
        return join_paths(__DIR__, 'migrations',$this->api.'.php');
    }
    public function getDBUser($append_has_connector = False)
    {
        $has_connector = False;
        User::macro('hasConnector', function() use($has_connector) {
            return $has_connector;
         });
        $dbuser = NULL;
        $user = $this->user();
        if(isset($user->id))
        {
            $dbuser = User::select('users.*')
            ->join('connector_user','connector_user.user_id','=','users.id')
            ->where('connector_user.api_id','=',$user->id)
            ->where('connector_user.connector_id','=',$this->getKey())
            ->first();
            if(isset($dbuser))
            {
                $has_connector = True;
            }
        }
        if(!isset($dbuser) && isset($user->email))
        {
            $has_connector = False;
            $dbuser = User::select('users.*')
            ->join('connector_user','connector_user.user_id','=','users.id')
            ->where('connector_user.email','=',$user->email)->first();
            
            
            if(!isset($dbuser))
            {
                $dbuser = User::where('email','=',$user->email)->first();
            }
        }
        if($append_has_connector)
            $dbuser->has_connector = $has_connector;
   
        return $dbuser;
    }
}