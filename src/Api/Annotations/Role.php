<?php
namespace Core\Api\Annotations;
use Core\Exception\Exception;
use Core\Exception\ApiException;
use Core\Traits\Role as RoleTrait;
use Logger;
/**
 *
 * @Annotation
 * @Target({"METHOD","CLASS"})
 */
class Role extends CoreAnnotation
{
    public $rawroles;
    public $roles;
    protected $needed;
    protected $forbidden;
    protected $handled;
    public static function getMiddleware()
    {
        return 'Core\Http\Middleware\Api\Acl';
    }
    public function handleAnnotations($annotations)
    {
    	foreach($annotations as $annotation)
    	{
    		if(!($annotation instanceof Role))
    		{
    			continue;
			}
            $this->handled = True;
    		if(!isset($annotation->roles))
    		{
    			$annotation->roles = [];
			}
    		$annotation->roles = array_unique( array_merge( $this->roles, $annotation->roles ) );
		}
    }
    public function getNeeded()
    {
        return $this->needed??[];
    }
    public function getForbidden()
    {
        return $this->forbidden??[];
    }
    public function isAllowed($user)
    {
		if(!isset($this->roles))
        {
        	$this->roles = [];
		}
    	if(!isset($user))
    	{
    		if(!empty($this->needed) && (count($this->needed) > 1 || $this->needed[0] != 'guest'))
    		{
    			return False;
    		}
    		if(!empty($this->forbidden) && in_array('guest', $this->forbidden))
    		{
    			return False;
    		}
    		if(in_array('guest', $this->roles))
    		{
    			return True;
    		}
    		return False;
		}
    	if(!empty($this->needed))
    	{
    		foreach($this->needed as $role)
	    	{
				if(!$user->hasRole($role))
	    		{
	    			return False;
	    		}
	    	}
    	}
    	if(!empty($this->forbidden))
    	{
    		foreach($this->forbidden as $role)
	    	{
	    		if($user->hasRole($role) && !$user->isAdmin())
	    		{
	    			return False;
	    		}
	    	}
    	}
    	if(!empty($this->roles))
    	{
	    	foreach($this->roles as $role)
	    	{
	    		if($user->hasRole($role))
	    		{
					
	    			return True;
				}
			}
	    	return False;
    	}
    	return True;
    }
    public function boot()
    {
    	if(!isset($this->roles))
        {
        	$this->roles = [];
        }
    	if(is_string($this->rawroles))
    	{
    		$this->roles = array_merge($this->roles, array_map("trim", explode(",", $this->rawroles)));
		}
		
        //unload for serializing
		$this->rawroles = NULL;
    	// if($this->classAnnotation)
    	// {
		// 	return;
		// }
    	$roles_count = [];
    	foreach($this->roles as $role)
    	{
			$count = 1;
			if(starts_with($role, '0') || starts_with($role, '~'))
			{
				$role = substr($role, 1);
				$roles_count[$role] = 0;
				//ignore role
				continue;
			}
			if(starts_with($role, '-') || starts_with($role, '+'))
			{
				if(starts_with($role, '-'))
				{
					$count = -1;
				}else
				{
					$count = 2;
				}
				$role = substr($role, 1);
			}
    		$roles_count[$role] = $count;
    	}

    	$this->roles = [];
    	$this->forbidden = [];
    	$this->needed = [];

    	foreach($roles_count as $role=>$value)
    	{
    		if($value == 0)
    		{
    			continue;
    		}
    		if($value < 0)
    		{
    			$this->forbidden[] = $role;
    		}else
    		if($value == 1)
    		{
    			$this->roles[] = $role;
    		}elseif($value > 1)
    		{
    			$this->needed[] = $role;
    		}
    	}
		

    	if(empty($this->forbidden))
    	{
    		$this->forbidden = NULL;
    	}
    	if(empty($this->needed))
    	{
    		$this->needed = NULL;
    	}
    	if(empty($this->roles))
    	{
    		$this->roles = NULL;
		}
    }
}
