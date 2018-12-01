<?php

namespace Core\Console\Commands;
use DB;
use Illuminate\Console\Command;
use Core\Util\ClassWriter;
use Core\Util\ClassWriter\Body\Table;
use Core\Util\ClassWriter\Body\General;
use Schema;
use File;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Illuminate\Support\Arr;
use Config as Conf;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
class Project extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Project setup';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
       $domain = $this->argument('domain');
       $this->call('project:certificate', ['domain'=>$domain]);
       $this->call('project:config', ['domain'=>$domain]);
       $this->call('project:docker', ['domain'=>$domain]);
       $this->call('project:nginx', ['domain'=>$domain]);
       //setup domains
       //setup lets encrypt

       //update
    }
}
