<?php 
namespace Core\Util;
use Core\Util\ClassWriter\Uses;
use Core\Util\ClassWriter\Property;
use Core\Util\ClassWriter\Constant;
use Core\Util\ClassWriter\Method;
use ReflectionMethod;
use ReflectionClass;
class ClassWriter
{
	protected $namespace;
	protected $uses;
	protected $classname;
	protected $extends;
	protected $implements;
	protected $usesTraits;
	protected $docs = NULL;

	protected $constants;
	protected $properties;

	protected $methods;
	protected $type = "class";
	public $abstract = false;
	public $final = false;


	public function __construct()
	{
		$this->uses = [];
		$this->extends = [];
		$this->implements = [];
		$this->constants = [];
		$this->properties = [];
		$this->methods = [];
		$this->usesTraits = [];
	}
	public function setDoc($docs)
	{
		if(!isset($docs))
		{
			return;
		}
		$this->docs = $docs;
	}
	public function setType($name)
	{
		$this->type = $name;
	}
	public function setNamespace($namespace)
	{
		if(!mb_strlen($namespace))
			return;
		$this->namespace = $namespace;
	}
	public function getNamespace()
	{
		return $this->namespace;
	}
	public function addUse($path, $alias = NULL)
	{
		$this->uses[] = new Uses($path, $alias);
	}
	public function addUseTrait($name, $aliases = NULL)
	{
		$this->usesTraits[] = std(['name'=>$name,'aliases'=>$aliases]);
	}
	public function setClassName($name)
	{
		$this->classname = $name;
	}
	public function getClassName()
	{
		return $this->classname;
	}
	public function getFullName()
	{
		return (isset($this->namespace)?$this->namespace.'\\':"").$this->classname;
	}
	public function setExtends($extends)
	{
		$this->extends = [$extends];
	}
	public function getExtend()
	{
		if(empty($this->extends))
			return NULL;
		return $this->extends[0];
	}
	public function addProperty($name, $visibility = "public", $static = False, $value = NULL, $doc = NULL )
	{
		$this->properties[] = new Property($name, $visibility, $static, $value, $doc);
	}
	public function addConstant($name, $value)
	{
		$this->constants[] = new Constant($name, $value);
	}
	public function addFunction($name, $params, $body, $visibility = "public", $static = False, $doc = NULL)
	{
		$this->methods[] = new Method($name, $params, $body, $visibility, $static, $doc);
	}
	public function addInterface($name)
	{
		$this->implements[] = $name;
	}
	public function addMethod($cls, $function, $name = NULL, $params = NULL, $visibility = NULL, $static = NULL, $doc = NULL)
	{
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
			if(isset($matches[4]))
			{
				$params = $matches[4];
			}else {
				$params = [];
			}
		}
		$body = implode("", array_slice($source, $start, $length));
		return $this->addFunction($name, $params, $body, $visibility, $static, $doc);
	}
	protected function tab($length)
	{
		$tab = "";
		for($i=0; $i<$length; $i++)
		{
			$tab.="\t";
		}
		return $tab;
	}
	protected function short($cls)
	{
		if(empty($this->uses))
			return $cls;

		foreach($this->uses as $use)
		{
			if($use->getPath() == $cls)
			{
				// if(!$use->hasAlias())
				// dd(last(explode('\\', $cls)));
				return $use->hasAlias()?$use->getAlias():last(explode('\\', $cls));
			}
		}
		return $cls;
	}
	public function getClassDefinition()
	{
		$cls = ($this->abstract?'abstract ':'').($this->final?'final ':'').$this->type." ".$this->classname;
		if(!empty($this->extends))
		{
			$cls.= " extends ".implode(", ", array_map(function($item){return $this->short($item);}, $this->extends));
		}
		if(!empty($this->implements))
		{
			$cls.= " implements ".implode(", ", array_map(function($item){return $this->short($item);}, $this->implements));
		}
		return $cls;
	}
	public function export()
	{
		$cls = "<?php\n";
		$tab = $this->tab(0);

		//namespace
		if(isset($this->namespace))
			$cls.= $tab."namespace ".$this->namespace.";\n\n";

		//use statements
		if(!empty($this->uses))
		{
			foreach($this->uses as $uses)
			{
				$cls.= $tab."use ".$uses->getPath().($uses->hasAlias()?" as ".$uses->getAlias():"").";\n";
			}
			$cls.= "\n";
		}

		//class declaration
		if(isset($this->classname))
		{
			if(isset($this->docs))
			{
				$cls.=$tab.$this->docs."\n";
			}
			$cls.=$this->getClassDefinition();
			// $cls.= $tab.($this->abstract?'abstract ':'').($this->final?'final ':'').$this->type." ".$this->classname;
			// if(!empty($this->extends))
			// {
			// 	$cls.= " extends ".implode(", ", array_map(function($item){return $this->short($item);}, $this->extends));
			// }
			// if(!empty($this->implements))
			// {
			// 	$cls.= " implements ".implode(", ", array_map(function($item){return $this->short($item);}, $this->implements));
			// }
			$cls.= "\n{\n";
			$tab = $this->tab(1);
		}
		if(!empty($this->usesTraits))
		{
			foreach($this->usesTraits as $trait)
			{
				$cls.= $tab."use ".$this->short($trait->name).";\n";
				if(!empty($trait->aliases))
				{
					$cls.= $tab."{\n";
					foreach($trait->aliases as $traitalias)
					{
						$cls.= $this->tab(2).$this->short($trait->name).'::'.$traitalias->method.' as '.$traitalias->alias.";\n";
					}
					$cls.= $tab."}\n";
				}
			}
			$cls.="\n";
		}
		if(!empty($this->constants))
		{
			foreach($this->constants as $constant)
			{
				$cls.= $tab."const ".$constant->getName().($constant->hasValue()?" = ".$constant->getEscapedValue():"").";\n";
			}
			$cls.="\n";
		}

		if(!empty($this->properties))
		{
			foreach($this->properties as $property)
			{
				if($property->hasDoc())
				{
					$cls.= "\n".$property->getDoc($tab)."\n";
				}
				$cls.= $tab.($property->hasVisibility()?$property->getVisibility()." ":"").($property->isStatic()?'static ':'')."$".$property->getName().($property->hasValue()?" = ".$property->getEscapedValue():"").";\n";
			}
			$cls.="\n";
		}
		if(!empty($this->methods))
		{
			foreach($this->methods as $method)
			{
				if($method->hasDoc())
				{
					$cls.= "\n".$method->getDoc($tab)."\n";
				}
				$cls.= $tab.($method->hasVisibility()?$method->getVisibility()." ":"").($method->isStatic()?'static ':'');

				$cls.= "function ".$method->getName()."(".($method->hasParams()?$method->getParams():"").")\n";
				$body = $method->getBody();
				if(!starts_with(trim($body), "{"))
				{
					$body = "\t{\n\t\t".join("\n\t\t",explode("\n",$body));
				}
				if(!ends_with(trim($body), "}"))
				{
					$body = $body."\n\t}\n";;
				}
				$cls.= $body;
			}
		}


		$tab = $this->tab(0);
		if(isset($this->classname))
		{
			$cls.= "\n".$tab."}\n";
		}
		return $cls;
	}
	public function write($path)
	{
		$text = $this->export();
		file_put_contents($path, $text);
	}


	public static function load($classpath)
	{
		$reflection = new ReflectionClass($classpath);
		$instance = new ClassWriter;
		//namespace
		$instance->setNamespace($reflection->getNamespaceName());
		//type
		if($reflection->isTrait())
			$instance->setType('trait');
		elseif($reflection->isInterface())
			$instance->setType('interface');
		else
			$instance->setType('class');

		//extends
		$extends = $reflection->getParentClass();
		if($extends)
			$instance->setExtends($extends->getName());

		//interfaces (remove parents)
		$interfaces = $reflection->getInterfaceNames();
		if($extends)
		{
			$interfaces_parent = $extends->getInterfaceNames();
			$interfaces = array_diff($interfaces, $interfaces_parent);
		}
		foreach($interfaces as $interface)
		{
			$instance->addInterface($interface);
		}
		
		//classname
		$instance->setClassName($reflection->getShortName());
		//doc
		$instance->setDoc($reflection->getDocComment());

		$traits = $reflection->getTraitNames();
		$traits_alias = $reflection->getTraitAliases();
		$traits_alias = array_reduce(array_keys($traits_alias), function($previous, $key) use($traits_alias)
		{
			$parts = explode('::', $traits_alias[$key]);
			$cls = array_shift($parts);
			$method = join('::', $parts);
			$alias = $key;
			if(!isset($previous[$cls]))
			{
				$previous[$cls] = [];
			}
			$previous[$cls][] = std(['method'=>$method, 'alias'=>$alias]);
			return $previous;
		}, []);
		foreach($traits as $trait)
		{
			$instance->addUseTrait($trait, isset($traits_alias[$trait])?$traits_alias[$trait]:null);
		}
		$constants = $reflection->getConstants();
		if($extends)
		{
			$constants = array_diff($constants,$extends->getConstants());
		}
		foreach($constants as $name=>$value)
		{
			$instance->addConstant($name, $value);
		}
		
		//properties
		$properties_values = $reflection->getDefaultProperties();
		$properties = collect($reflection->getProperties())
		->filter(function($item) use($classpath, $reflection)
		{
			return $item->class == $classpath && !self::isFromTrait($reflection, $item);
		})
		->map(function($item) use ($properties_values)
		{
			$std = new \stdClass;

			$std->name = $item->name;
			$std->class = $item->class;
			$std->visibility = $item->isPrivate()?'private':($item->isPublic()?'public':($item->isProtected()?'protected':null));
			$std->is_static = $item->isStatic();
			$std->value = NULL;
			$std->doc = $item->getDocComment();
			if(isset($properties_values[$std->name]))
			{
				$std->value = $properties_values[$std->name];
			}
			return $std;
		})
		->keyBy('name');

		foreach($properties as $property)
		{
			$instance->addProperty($property->name, $property->visibility, $property->is_static, $property->value, $property->doc);
		}
		//methods
		$methods = collect($reflection->getMethods())
		->filter(function($item) use($classpath, $reflection)
		{

			return  $item->getDeclaringClass()->getName() == $classpath && $item->getFileName() == $reflection->getFileName();// && self::getDeclaringTrait($reflection, $item) === null;
		})->map(function($item)
		{
			$std = new \stdClass;

			$std->name = $item->name;
			$std->class = $item->class;
			$std->visibility = $item->isPrivate()?'private':($item->isPublic()?'public':($item->isProtected()?'protected':null));
			$std->is_static = $item->isStatic();
			$std->doc = $item->getDocComment();
			return $std;
		});

		foreach($methods as $method)
		{
			$instance->addMethod($reflection->getName(), $method->name, $method->name, NULL, $method->visibility,$method->is_static, $method->doc);
		}
		
		//get uses
		if($reflection->getFileName())
		{
			$content = file_get_contents($reflection->getFileName());
			$rows = explode("\n", $content);
			array_splice($rows,  $reflection->getStartLine()-1,$reflection->getEndLine() - $reflection->getStartLine() +1 );
			$uses = [];
			foreach($rows as $row)
			{
				//preg_match("/use( |\t)(.+);/", $row, $matches);
				preg_match("/use( |\t)(.+)( |\t)+as( |\t)+(.*)( |\t)*;/", $row, $matches);
				if(!empty($matches))
				{
					$use = new \stdClass;
					$use->class = preg_replace("/ |\n/","", trim($matches[2]));
					$use->alias = NULL;
					if(count($matches)>3)
					{
						$use->alias =  preg_replace("/ |\n/","", trim($matches[5]));
					}
					$uses[] = $use;
					continue;
				}
				preg_match("/use( |\t)(.+)( |\t)*;/", $row, $matches);
				if(!empty($matches))
				{
					$use = new \stdClass;
					$use->class =  preg_replace("/ |\n/","", trim($matches[2]));
					$use->alias = NULL;
					$uses[] = $use;
				}
			}
			if(!empty($uses))
			{
				foreach($uses as $use)
				{
					$instance->addUse($use->class, $use->alias);
				}
			}
		}
		return $instance;
	}
	protected static function isFromTrait($cls, $property)
	{
		$traits = $cls->getTraits();
		foreach($traits as $trait)
		{
			$properties = $trait->getProperties();
			foreach($properties as $prop)
			{
				if($prop->name == $property->name)
					return true;
			}
			if(self::isFromTrait($trait, $property))
			{
				return true;
			}
		}
		return False;
	}
}