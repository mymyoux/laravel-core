<?php 
namespace Core\Util;
use Core\Util\ClassWriter\Uses;
use Core\Util\ClassWriter\Property;
use Core\Util\ClassWriter\Constant;
use Core\Util\ClassWriter\Method;
use ReflectionMethod;
class ClassHelper
{
	protected static function getNamespaceByRegexp($src) {
		if (preg_match('#(namespace)(\\s+)([A-Za-z0-9\\\\]+?)(\\s*);#sm', $src, $m)) {
			return $m[3];
		}
		return null;
	}
	public static function getInformations($path, $weak = False)
	{
		if($weak)
		{
			if(starts_with($path->getPathName(), base_path()))
			{
				// $namespace = dirname($path);
				$namespace = self::getNamespaceByRegexp(file_get_contents($path->getPathName()));
				if(isset($namespace))
				{
					$class = last(explode('/', $path->getPathName()));
					$index = mb_strpos($class, '.');
					if($index !== False)
					{
						$class = mb_substr($class, 0, $index);
					}
					// $namespace = str_replace('/','\\', $namespace);
					$std = new \stdClass();
					$std->namespace = $namespace;
					$std->class = $class;
					$std->fullname =  ($namespace??"")."\\".$class;
					return $std;
				}
			}
		}
		$fp = fopen($path, 'r');
		$class = $buffer = '';
		$i = 0;
		$namespace = "";
		while (!$class) {
		    if (feof($fp)) break;
		    $buffer .= fread($fp, 512);
		    $tokens = @token_get_all($buffer);

		    if (strpos($buffer, '{') === false) continue;

		    for (;$i<count($tokens);$i++) {
		        if ($tokens[$i][0] === \T_CLASS) {
					if(!isset($tokens[$i-1]) || (!isset($tokens[$i-1][1]) || $tokens[$i-1][1]!="::"))
					{
						
						for ($j=$i+1;$j<count($tokens);$j++) {
							if ($tokens[$j] === '{') {
								if(isset($tokens[$i+2][1]))
								{
									$class = $tokens[$i+2][1];
									break 2;
								}else
								{
									dd($tokens[$i-1]);
								}
							}
						}
					}
		        }else
		        if ($tokens[$i][0] === \T_NAMESPACE) {
		            for ($j=$i+1;$j<count($tokens);$j++) {
						if ($tokens[$j] === '{') {
							break;
						}
		                if ($tokens[$j] === ';') {
		                    $namespace = join("", array_map(function($item){
								if(!isset($item[1]))
								{
									return "";
								}
								return $item[1];},array_slice($tokens, $i+2,$j-$i-2)));//$tokens[$i+2][1];
							break;
		                }
		            }
		        }
		    }
		}
		$std = new \stdClass();
		$std->namespace = $namespace;
		$std->class = $class;
		$std->fullname =  ($namespace??"")."\\".$class;
		return $std;
	}
	public static function getNamespace($path)
	{
		return static::getInformations($path)->namespace;
	}
	public static function getFullClassName($path)
	{
		return static::getInformations($path)->fullname;
	}
	public static function getMethodBody($path, $withHeaders = False, $removeBrackets = False)
	{
		list($cls, $function) = explode("@", $path);
		$func = new ReflectionMethod($cls, $function);
		$filename = $func->getFileName();
		$start = $func->getStartLine();
		$end = $func->getEndLine();

		$length = $end - $start;

		$source = file($filename);
		$head = $source[$start-1];
		$matches = [];
		preg_match("/([a-z]+) +(static)? *function +([^\( ]+)\(([^)]*)\)/i", $head, $matches);
		if(!isset($visibility))
		{
			if(!isset($matches[1]))
			{
				var_dump($source);
				var_dump($func);
				echo ($start-1)."\n";
				echo $head."\n";
				echo $filename."\n";
				echo $path."\n";
				dd($matches);
			}
			$visibility = $matches[1];
		}
		if(!isset($static))
		{
			$static = strlen($matches[2])>0;
		}
		if(!isset($name))
		{
			$name = $matches[3];
		}
		if(!isset($params))
		{
			$params = $matches[4];
		}
		$body = implode("", array_slice($source, $start, $length));
		if($removeBrackets)
		{
			$index = mb_strpos($body, '{');
			if($index !== False)
			{
				$body = mb_substr($body, $index+1);
			}
			$index = mb_strrpos($body, '}');
			if($index !== False)
			{
				$body = mb_substr($body,0, $index);
			}
		}
		return ($withHeaders?$head:"").$body;
	}
	public static function getSubclassesOf($parent) {
        $result = array();
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, $parent))
                $result[] = $class;
		}
        return $result;
	}
	public static function getLastSubclassOf($parent) {
		$result = static::getSubclassesOf($parent);
		if(empty($result))
			return NULL;
		if(count($result) == 1)
			return $result[0];
		dd(["need to be implemented"=>$result]);
        return $result;
    }
}
