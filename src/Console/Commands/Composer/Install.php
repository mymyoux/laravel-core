<?php

namespace Core\Console\Commands\Composer;
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
use Core\Util\Command as Executer;

class Install extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'composer:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute composer install';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Executer::executeRaw('composer', ['install']);
    }
}
