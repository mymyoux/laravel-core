<?php

namespace Core\Console\Commands\Test;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App;
use Illuminate\Foundation\Providers\ArtisanServiceProvider;
use DB;
use Core\Model\Error;
use Logger;
use Illuminate\Console\Application;
use Log;


use Schema;
use ReflectionClass;
use File;
use Route;
use Core\Util\MarkdownWriter;
use Core\Util\Command as ExecCommand;
use Core\Util\ClassHelper;
use stdClass;
use Api;
use Core\Api\Annotations\Paginate;
use Core\Api\Annotations\Param;
use Core\Api\Annotations\Role;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Core\Util\ModuleHelper;
use Core\Util\ClassWriter;
use Illuminate\Database\Eloquent\Relations\Relation;
use Cache as CacheService;
class Cache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Cache';

    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        CacheService::put('test','lol', 3500);
        if(CacheService::has('test'))
        {
            Logger::info('cache ok');
        }else
        {
            Logger::error('cache not working');
        }
    }
   
}
