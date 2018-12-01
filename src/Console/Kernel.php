<?php

namespace Core\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Console\Command;
use Core\Util\ModuleHelper;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Console\Application as Artisan;
use ReflectionClass;
class Kernel extends ConsoleKernel
{

      /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];
  
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')
        //          ->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        //add Core folder + app folder as commands sources
        $modules = array_reverse(array_map(function($item)
        {
            $item['path'] = base_path(join_paths($item['path'],'Console','Commands'));;
            return $item;
        }, ModuleHelper::getModulesFromComposer()));

        foreach($modules as $module)
        {
            $namespace = $module['module'].'Console\Commands\\';
            if(!is_dir($module['path']))
                continue;

            foreach((new Finder)->in($module['path'])->files() as $command)
            {
                $command = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($command->getPathname(), $module['path'].DIRECTORY_SEPARATOR)
                );
                if (is_subclass_of($command, Command::class) &&
                    ! (new ReflectionClass($command))->isAbstract()) {
                    Artisan::starting(function ($artisan) use ($command) {
                        $artisan->resolve($command);
                    });
                }
            }
        }
        
        require base_path('routes/console.php');
    }
}
