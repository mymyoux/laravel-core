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


class LetsEncrypt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'project:lets-encrypt {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Handle letsencrypt setup';
 
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
        dd($domain);
    }
}
