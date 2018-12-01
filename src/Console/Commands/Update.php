<?php

namespace Core\Console\Commands;
use Illuminate\Console\Command;
use Logger;
use Core\Util\Command as Executer;
use Illuminate\Console\Application;
use App;
class Update extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update {--enable=} {--disable=} {--step=} {--front}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update project';


    protected $steps = [
        'git' => True,
        'clearCache' => True,
        'composer' => True,
        'migrate' => True,
        'socialSync' => True,
        'buildCache' => False,
        'exportConfig' => True,
        'doc' => False,


        //front
        'gitFront' => False,
        'gitFrontFramework' => False,
        'yarn' => False,
        'buildFront' => False
    ];
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
        $step = $this->option('step');
        if(isset($step))
            return $this->step($step);

        if(App::environment() == 'production')
        {
            $this->steps['buildCache'] = True;
            $this->steps['doc'] = True;
        }
        $this->steps['exportConfig'] = config('front.path')?True:False;

        $front = $this->option('front');
        if(!$front && App::environment() == 'production' && config('front.path'))
        {
            if($this->confirm('Do you want to run front update?'))
            {
                $front = True;
            }
        }
        if($front)
        {
            $this->steps['gitFront'] = True;
            $this->steps['gitFrontFramework'] = True;
            $this->steps['yarn'] = True;
            if(App::environment() == 'production')
            {
                $this->steps['buildFront'] = True;
            }
        }
        //disable steps
        $disabled = $this->option('disable');
        if(isset($disabled))
        {
            $disabled = $disabled == 'all'?array_keys($this->steps):array_map('trim', explode(',', $disabled));
            $disabled = array_map(function($item)
            {
                return camel_case($item);
            }, $disabled);
            $bad_steps = array_diff($disabled, array_keys($this->steps));
            if(!empty($bad_steps))
            {
                foreach($bad_steps as $step)
                {
                    Logger::error('step \''.$step.'\' is not recognized');
                }
                Logger::fatal('update aborted');
                exit();
            }
            foreach($disabled as $step)
            {
                $this->steps[$step] = False;
            }
        }

        //enable steps
        $enabled = $this->option('enable');
        if(isset($enabled))
        {
            $enabled = $enabled == 'all'?array_keys($this->steps):array_map('trim', explode(',', $enabled));
            $enabled = array_map(function($item)
            {
                return camel_case($item);
            }, $enabled);
            $bad_steps = array_diff($enabled, array_keys($this->steps));
            if(!empty($bad_steps))
            {
                foreach($bad_steps as $step)
                {
                    Logger::error('step \''.$step.'\' is not recognized');
                }
                Logger::fatal('update aborted');
                exit();
            }
            foreach($enabled as $step)
            {
                $this->steps[$step] = True;
            }
        }

        $steps = array_filter(array_keys($this->steps), function($step)
        {
            return $this->steps[$step];
        });

        //handle steps
        $bar = $this->output->createProgressBar(count($steps)+1);
        $bar->setFormatDefinition('custom', '<fg=cyan>%bar% %current%/%max%</><fg=yellow> -- %message%</>');
        $bar->setFormat('custom');

        $results = [];
        foreach($steps as $step)
        {
            $bar->setMessage('Running '.uncamel($step)."\n");
            $bar->advance();
             $result = Executer::executeRaw(Application::phpBinary(), [Application::artisanBinary(), "update", "--step=".$step]);
            if($result !== 0)
            {
                $results[$step] = False;
                if(!$this->confirm('<error>An error has occurred, do you want to continue anyway?</error>'))
                {
                    Logger::error('aborted');
                    exit(1);
                    break;
                }
            }else
            {
                $results[$step] = True;
            }
        }
        $bar->setMessage("Exiting\n");
        $bar->advance();
        Logger::normal("\n");


        foreach($results as $step => $result)
        {
            $step = uncamel($step);
            if($result)
            {
                Logger::normal($step.': '."\033[32msuccess\033[0m");
            }else
            {
                Logger::normal($step.': '."\033[31mfailed\033[0m");
            }
        }
    }

    public function step($step)
    {
        $name = 'step'.ucfirst($step);
        return $this->$name();
    }
    public function stepGit()
    {
       $result = Executer::executeRaw('git pull');
       exit($result);
    }
    public function stepComposer()
    {
        if(App::environment() == 'production')
        {
            $result = Executer::executeRaw('composer install --optimize-autoloader --no-dev');
        }else {
            $result = Executer::executeRaw('composer install');
        }
       exit($result);
    }
    public function stepClearCache()
    {
        $this->call('clear-compiled');
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
    }
    public function stepMigrate()
    {
        $this->call('migrate',['--force'=>True]);
        $this->call('table:cache');
    }
    public function stepSocialSync()
    {
        $this->call('social:sync');
    }
    public function stepBuildCache()
    {
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');
    }
    public function stepExportConfig()
    {
        $this->call('config:export');
    }
    public function stepDoc()
    {
        $this->call('doc:build');
    }
    public function stepGitFront()
    {
        chdir(config('front.path'));
        $result = Executer::executeRaw('npm run update -- --step=git', NULL, config('front.path'));
        exit($result);
    }
    public function stepGitFrontFramework()
    {
        chdir(config('front.path'));
        $result = Executer::executeRaw('npm run update -- --step=gitFramework', NULL, config('front.path'));
        exit($result);
    }
    public function stepYarn()
    {
        chdir(config('front.path'));
        $result = Executer::executeRaw('npm run update -- --step=yarn', NULL, config('front.path'));
        exit($result);
    }
    public function stepBuildFront()
    {
        chdir(config('front.path'));
        $result = Executer::executeRaw('node src/main/app-update.js --step=build', NULL, config('front.path'));
        exit($result);
    }
}
