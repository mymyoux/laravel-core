<?php

namespace Core\Console\Commands\Project;
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
use Core\Services\Project;

class Nginx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'project:nginx {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate nginx file for domain';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->argument('domain');
        if(!is_domain_name($domain))
        {
            Logger::fatal($domain.' is not a valid domain name');
            return;
        }
        

        $home_path = home_path('.myno/config.json');
        if(!file_exists($home_path))
        {
            throw new \Exception($home_path.' not found - execute artisan project:docker before');
        }
        if(!Project::hasNginxPath())
        {
            $global_config = [
                "linux"=> 
                    ["nginx_path"=> "/etc/nginx"],
                "mac"=>
                ["nginx_path"=> "/usr/local/etc/nginx"]
            ];
    
            if(PHP_OS == 'Linux')
            {
                $localconfig = $global_config["linux"];
            }else
            {
                $localconfig = $global_config["mac"];
            }
    
            //TODO:test if current certificate are already good domain
    
    
            $default = $localconfig['nginx_path'];
            $path = $this->anticipate('Nginx directory ['.$default.']', [$default,$localconfig["nginx_path"]]+array_map(function($item)
            {
                return $item['nginx_path'];
            },$global_config));
            if(!isset($path))
            {
                $path = $default;
            }
            Project::setNginxPath($path);
        }
        $servers_path = join_paths(Project::getNginxPath(), 'servers');

        Project::copy('nginx.conf', base_path('docker/configuration/'.$domain.'.conf'), $domain);
       
        Project::write();

        $source = base_path('docker/configuration/'.$domain.'.conf');
        $destination = join_paths($servers_path, $domain.'.conf');
        if(!file_exists($source))
        {
            throw new \Exception($source.' not found - execute artisan project:docker before');
        }
        if(file_exists($destination))
        {
            @unlink($destination);
        }
        $this->laravel->make('files')->link(
            $source, $destination
        );
        Logger::info($destination);
        Logger::info('nginx configuration file created');
        Logger::info('relaunch nginx with:');
        Logger::normal('nginx -s reload');
    }
}
