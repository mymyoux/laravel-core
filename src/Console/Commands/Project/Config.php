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


class Config extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'project:config {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up laravel config files for domain';
 
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
        $content = File::get(base_path('.env'));
        $regexp = "/^(\s*APP_URL\s*=\s*)(.*)$/m";

        $url = 'https://'.$domain;
        preg_match($regexp, $content, $matches);
        if(!empty($matches))
        {
            $content = preg_replace($regexp,"$1".$url, $content);
        }else
        {
            $content.="\nAPP_URL=".$url."\n";
        }
        File::put(base_path('.env'), $content);
        Logger::info('domain setup in .env config file');
    }
}
