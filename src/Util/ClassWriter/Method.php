<?php 
namespace Core\Util\ClassWriter;

class Method
{
	protected $name;
	protected $params;
	protected $visibility;
	protected $_isStatic;
	protected $body;
	protected $doc;
	public function __construct($name, $params, $body, $visibility, $isStatic, $doc)
	{
		$this->name = $name;
		if(is_array($params))
		{
			$params = join($params, ',');
		}
		$this->params = $params;
		$this->body = $body;
		$this->doc = $doc;
		$this->visibility = $visibility;
		$this->_isStatic = $isStatic;
	}
	public function getName()
	{
		return $this->name;
	}
	public function getVisibility()
	{
		return $this->visibility;
	}
	public function hasBody()
	{
		return isset($this->c);
	}
	public function getBody()
	{
		return $this->body;
	}
	public function hasParams()
	{
		return isset($this->params) && strlen($this->params);
	}
	public function getParams()
	{
		return $this->params;
	}
	public function hasVisibility()
	{
		return isset($this->visibility);
	}
	public function isStatic()
	{
		return $this->_isStatic;
	}
	public function hasDoc()
	{
		return isset($this->doc);
	}
	public function getDoc($tab = NULL)
	{
		if(isset($tab))
		{
			return preg_replace("/^ *\t*/m",$tab, $this->doc);
		}
		return $this->doc;
	}
}


