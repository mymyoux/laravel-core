<?php 
namespace Core\Util;
use Core\Util\ClassWriter\Uses;
use Core\Util\ClassWriter\Property;
use Core\Util\ClassWriter\Constant;
use Core\Util\ClassWriter\Method;
use ReflectionMethod;
class ModuleHelper
{
	public static function getModulesFromComposer()
	{
		$composer = file_get_contents(base_path("composer.json"));
		$composer = json_decode($composer);
		$psr = "psr-4";
		if(!isset($composer->autoload->$psr))
		{
			return [];
		}
		$psr = $composer->autoload->$psr;

		$modules = [];
		$add_core = False;
		if(!in_array('Core\\', array_keys((array)$psr)))
		{
			$add_core = True;
		}
		foreach($psr as $key=>$value)
		{
			$modules[] = ["module"=>$key, "path"=>$value];
		}
		if($add_core)
		{
			array_splice($modules, 1,0, [['module'=>'Core\\','path'=>'vendor/myno/laravel/src/']]);
		}
		return $modules;
	}
	public static function getModulePath($name)
	{
		$modules = static::getModulesFromComposer();
		$name = strtolower($name)."\\";
		foreach($modules as $key=>$value)
		{
			if(strtolower($value["module"]) == $name)
			{
				return base_path($value["path"]);
			}
		}
		return NULL;
	}
}
