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


class Docker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'project:docker {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate docker files for domain';
 
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

        Project::copy('vhost.conf', base_path('docker/vhost.conf'), $domain);
        Project::copy('docker-compose.yml', base_path('docker-compose.yml'), $domain);
       
        Project::write();





    }
}
