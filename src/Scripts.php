<?php

namespace Core;
//require 'Util/Initialize.php';
use ReflectionClass;
use ReflectionMethod;
use Core\Util\ClassHelper;
use Logger;
use File;
function global_base_path($path = '')
{
   return base_path($path);
}
function local_base_path($path = '')
{
    $dir = __DIR__;
    while(!in_array(basename($dir), ['src']))
    {
        $old = $dir;
        $dir = dirname($dir);
        if($old == $dir)
        {
            throw new \Exception('root folder not found');
        }
    }
    $dir = dirname($dir);
    if(isset($path) && mb_strlen($path))
    {
        return join_paths($dir, $path);
    }else
    {
        return $dir;
    }
}
class Scripts
{
    static function log($data)
    {
        var_dump($data);
    }
    static function replaceUse($import, $newimport, $file)
    {
        if(!file_exists($file))
        {
            static::log('file '.$file.' doesn\'t exist');
            return;
        }
        
        $content  = file_get_contents($file);

        preg_match_all("/use\s+([^;]+)\s*;/m",$content, $matches);
        if(empty($matches))
        {
            static::log('no use in file '.$file);
            return;
        }
        $uses = $matches[1];
        $uses = array_map(function($item)
        {
            preg_match("/([^;]+)\s+as\s+/",$item, $matches);
            if(empty($matches))
            {
                return $item;
            }
            return $matches[1];
        }, $uses);

        $tmp = [];
        foreach($uses as $use)
        {
            $tmp[$use] = preg_replace("/\s/","",$use);
            if(starts_with($tmp[$use], '\\'))
            {
                $tmp[$use] = mb_substr($tmp[$use], 1);
            }
        }
        $uses = $tmp;

        foreach($uses as $key=>$use)
        {
            if($use == $import)
            {
                $content = str_replace($key, $newimport, $content);
                static::log($file.' written');
               file_put_contents($file, $content);
                return;
            }
        }
    }
    static function replace($search, $replace, $file)
    {
        if(!file_exists($file))
        {
            static::log('file '.$file.' doesn\'t exist');
            return;
        }
        
        $content  = file_get_contents($file);

        $content = str_replace($search, $replace, $content);
        static::log($file.' written');
        file_put_contents($file, $content);
    }
    static function append($append, $file)
    {
        if(!file_exists($file))
        {
            static::log('file '.$file.' doesn\'t exist');
            return;
        }
        
        $content  = file_get_contents($file);

        if(mb_strpos($content, $append)===False)
        {
            $content .="\n".$append."\n";
        }
        static::log($file.' written');
        file_put_contents($file, $content);
    }
    static function copy($source, $destination)
    {
        if(file_exists($destination))
        {
            static::log('file '.$destination.' already exist');
            return;
        }
        $content  = file_get_contents($source);

        static::log($destination.' written');
        file_put_contents($destination, $content);
    }
    static function appendInMethod($path, $append)
    {
        list($cls, $method) = explode('@', $path);
        if(!class_exists($cls))
        {
            static::log('class '.$cls.' doesn\'t exist');
            return;
        }
        $reflection = new ReflectionClass($cls);
        if(!$reflection->hasMethod($method))
        {
            Logger::error('class '.$cls.' doesn\'t have method '.$method);
            return;
        }
        $func = $reflection->getMethod($method);
        if($func->class != $cls)
        {
            static::log('class '.$cls.' method '.$method.' is defined in parent');
            return;   
            
        }
        $content = ClassHelper::getMethodBody($cls.'@'.$method, False, True);

       
        
        

        if(mb_strpos($content, $append)===False)
        {
            $file = $func->getFileName();
            $start = $func->getStartLine();
            $end = $func->getEndLine();
            
            $fullcontent = file($file);
            array_splice($fullcontent,$end-1, 0, "        ".$append.";\n");

            $content = implode("", $fullcontent);
            static::log($file.' written');
            file_put_contents($file, $content);
        }
    }
    static function replaceMethod($path, $replace)
    {
        list($cls, $method) = explode('@', $path);
        if(!class_exists($cls))
        {
            static::log('class '.$cls.' doesn\'t exist');
            return;
        }
        $reflection = new ReflectionClass($cls);
        if(!$reflection->hasMethod($method))
        {
            Logger::error('class '.$cls.' doesn\'t have method '.$method);
            return;
        }
        $func = $reflection->getMethod($method);
        if($func->class != $cls)
        {
            static::log('class '.$cls.' method '.$method.' is defined in parent');
            return;   
            
        }
        $content = ClassHelper::getMethodBody($cls.'@'.$method, False, True);

       
        

        $file = $func->getFileName();
        $start = $func->getStartLine();
        $end = $func->getEndLine();
        
        $fullcontent = file($file);
        array_splice($fullcontent,$start+1, $end-$start-2, "        ".$replace.";\n");

        $content = implode("", $fullcontent);
        static::log($file.' written');
        file_put_contents($file, $content);
    }
    static function postInstall()
    {
        //kernel
        static::replaceUse('Illuminate\Foundation\Console\Kernel','Core\Console\Kernel', global_base_path('app/Console/Kernel.php'));
        //providers
        //static::replaceUse('Illuminate\Support\ServiceProvider','Core\Providers\AppServiceProvider as ServiceProvider', global_base_path('app/Providers/AppServiceProvider.php'));
        static::replaceUse('Illuminate\Foundation\Support\Providers\AuthServiceProvider','Core\Providers\AuthServiceProvider', global_base_path('app/Providers/AuthServiceProvider.php'));
        static::replaceUse('Illuminate\Foundation\Support\Providers\RouteServiceProvider','Core\Providers\RouteServiceProvider', global_base_path('app/Providers/RouteServiceProvider.php'));
        static::replaceUse('Illuminate\Foundation\Support\Providers\EventServiceProvider','Core\Providers\EventServiceProvider', global_base_path('app/Providers/EventServiceProvider.php'));
        static::replaceUse('Illuminate\Foundation\Exceptions\Handler','Core\Exception\Handler', global_base_path('app/Exceptions/Handler.php'));
        //api

        static::append('\Core\Api\Api::generateRoutes();', global_base_path('routes/api.php'));
        //bootstrap
        static::replace('Illuminate\Foundation\Application','\Core\App\Application', global_base_path('bootstrap/app.php'));
        static::copy( local_base_path('bootstrap/autoload.php'),global_base_path('bootstrap/autoload.php'));
        //artisan
        $artisan =  <<<'EOD'
require __DIR__.'/bootstrap/autoload.php';

$isCron = False;
$inputs = $argv;
foreach($inputs as $key=>$value)
{
if(($index=mb_strpos($value, "cron")) === 0)
{
    list($name, $cron) = explode("=", $value);
    if($name == "cron")
    {
        $isCron = $cron;
        array_splice($inputs, $key, 1);
        break;
    }
}
}

putenv("ENV_CRON=$isCron");
EOD;
        static::replace("require __DIR__.'/vendor/autoload.php';",$artisan, global_base_path('artisan'));
        // static::appendInMethod('App\Providers\AppServiceProvider@boot','parent::boot()');
        // static::appendInMethod('App\Providers\AppServiceProvider@register','parent::register()');
        static::replaceMethod('App\Console\Kernel@commands','return parent::commands()');

        if(!file_exists(global_base_path('docker')))
        {
            File::copyDirectory( local_base_path('docker'), global_base_path('docker'));
        }
        if(!file_exists(global_base_path('certificates')))
        {
            File::copyDirectory( local_base_path('certificates'), global_base_path('certificates'));
        }
    }
}

