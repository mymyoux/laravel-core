<?php

namespace Core\Console\Commands\Social;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App;
use Illuminate\Foundation\Providers\ArtisanServiceProvider;
use DB;
use Core\Model\Error;
use File;
use Logger;
use Illuminate\Console\Application;
use Log;
use Core\Model\Connector;


class ListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'social:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all connectors';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $locals = config('services');
        $locals = array_filter($locals, function($item)
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

        if(!isset($locals['manual']))
        {
            $locals['manual'] = ['signup' => 1, 'login' => 1, 'multiple'=>0, 'scopes'=>NULL];
        }
        foreach($locals as $name=>&$local)
        {
            $local['name'] = $name;
            if(!isset($local['signup']))
            {
                $local['signup'] = True;
            }
            if(!isset($local['login']))
            {
                $local['login'] = True;
            }
            if(!isset($local['scopes']))
            {
                $local['scopes'] = NULL;
            }
            if(!isset($local['multiple']))
            {
                $local['multiple'] = False;
            }
        }
        
        $connectors = Connector::get()->keyBy('name')->toArray();
        foreach($connectors as &$connector)
        {
            $name = $connector['name'];
            if(!isset($locals[$name]))
            {
                $connector['state'] = "\033[31mDB only\033[0m";
            }else
            {
                $local = $locals[$name];
                unset($locals[$name]);
                if($local['signup'] != $connector['signup'] || $local['login'] != $connector['login'] || $local['scopes'] != $connector['scopes']  || $local['multiple'] != $connector['multiple'])
                {
                    $connector['state'] = "\033[31munsync\033[0m";
                }else
                {
                    $connector['state'] = "\033[32msync\033[0m";
                }
            }
        }
        if(!empty($locals))
        {
            foreach($locals as $name=>$local)
            {
                $local['state'] = "\033[31mlocal only\033[0m";
                $connectors[$name] = $local;
            }
        }
        $connectors = array_map(function($item)
        {
            return ['name'=>$item['name'], 'login'=>$item['login'],'signup'=>$item['signup'],'multiple'=>$item['multiple'],'scopes'=>$item['scopes'],'state'=>$item['state']];
        },array_values($connectors));

        $headers = ['name', 'login', 'signup','multiple','scopes', 'state'];
        $this->table($headers, $connectors);
    }
}
