<?php

namespace Core\Console\Commands\Social;
use Illuminate\Console\Command;
use Logger;
use Core\Model\Connector;
use Core\Http\Controllers\Auth\Connectors\Connector as ConnectorService;
use DB;
class Synchronize extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'social:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize database connectors with configuration files';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $services = config('services');
        $services = array_filter($services, function($item)
        {
            if(!isset($item['client_id']) || !mb_strlen($item['client_id']))
            {
                return False;
            }
            if(!isset($item['login']))
            {
                $item['login'] = True;
            }
            if(!isset($item['signup']))
            {
                $item['signup'] = True;
            }
            return   $item['signup'] || $item['login'];
        });
        if(!isset($services['manual']))
        {
            $services['manual'] = ['signup' => 1, 'login' => 1, 'multiple'=>0, 'scopes'=>NULL];
        }

        foreach($services as $name=>$service)
        {
            $connector = Connector::where('name', '=', $name)->first();
            if(!isset($connector))
            {
                $connector = new Connector;
                $connector->name = $name;
                $connector->signup = True;
                $connector->login = True;
                Logger::info('Add '.$name);
            }
            if(isset($service['scopes']))
            {
                $connector->scopes = $service['scopes'];
            }
            if(isset($service['signup']))
            {
                $connector->signup = $service['signup'];
            }
            if(isset($service['multiple']))
            {
                $connector->multiple = $service['multiple'];
            }
            if(isset($service['login']))
            {
                $connector->login = $service['login'];
            }
            if($connector->isDirty() && $connector->exists)
            {
                Logger::warn('Update '.$name);
            }
            if(!$connector->exists)
            {
                //new
                $connector_definition = ConnectorService::get($name);
                if($connector_definition->hasMigration())
                {
                    $path = $connector_definition->getMigrationPath();
                    Logger::warn('Executing migration for '.$name);
                    $this->call('migrate', ['--realpath'=>1, '--path'=>$path,'--force'=>True]);
                    DB::table('migrations')->where('migration','=',$name)->delete();
                }
            }
            $connector->save();
        }

        $names = array_keys($services);
        $connectors = Connector::whereNotIn('name', $names)->get();

        foreach($connectors as $connector)
        {
            if ($this->confirm('delete '.$connector->name.'?')) {
                $connector_definition = ConnectorService::get($connector->name);
                if($connector_definition->hasMigration())
                {
                    $path = $connector_definition->getMigrationPath();
                    Logger::warn('Rollbacking migration for '.$connector->name);
                    Logger::info('migrate:rollback --realpath --path='.$path);
                    $batch = DB::table('migrations')->select(DB::raw('MAX(batch) as count'))->first();
                    if(!isset($batch))
                    {
                        $batch = 1;
                    }else {
                        $batch = ((int)$batch->count)+1;
                    }
                     DB::table('migrations')->insert(['migration'=>$connector->name,'batch'=>$batch]);
                    $this->call('migrate:rollback', ['--realpath'=>1, '--path'=>$path, '--step'=>1, '--force'=>True]);
                }
                $connector->delete();
                Logger::warn('Remove '.$connector->name);
            }
        }

    }
}
