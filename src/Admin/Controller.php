<?php
namespace Core\Admin;


use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Core\Exception\ApiException;
use Core\Admin\Fields\BelongsToField;
use Core\Admin\Fields\PictureField;
use Core\Admin\Fields\HasManyField;
use Core\Admin\Resource;
use Core\Api\Annotations as myno;
use Core\Admin\Controller;
use Core\Admin\Components\ListComponent;
use Core\Database\Eloquent\Table;
use Core\Database\Eloquent\Relations\Pivot;
use Storage;
use App\User;

class Controller
{
 
	/**
	* @myno\Api
	**/
	public function create(Request $request)
	{
		
		$fields = static::$resource::editable();
		
		$item = $request->all();
		
		$adminmodel = new $class;
		
		foreach($fields as $field)
		{
		    $key = $field->column;
		    if($field instanceof BelongsToField)
		    {
		        if(array_key_exists($key, $item))
		        {
		            $value = $item[$key];
		            if(isset($value))
		            {
		                $belongsToModel = $field->getModel();
		                $instance = new $belongsToModel;
		                $primaryKey = $instance->getKeyName();
		                //TODO:handle multiple primary keys ?
		                if(isset($value[$primaryKey]))
		                {
		                    $belongsToModel = $belongsToModel::find($value[$primaryKey]);
		                    if(!isset($belongsToModel))
		                    {
		                        throw new ApiException('bad_id', 'bad_id:'.$value[$primaryKey]);
		                    }
		                }else
		                {
		                    throw new ApiException('bad_model_format');
		                }
		                $adminmodel->$key()->associate($belongsToModel);
		            }
		        }
		    }else
		    {
				if($request->hasFile($key))
				{
					if($field instanceof PictureField)
					{
						$item[$key] = Storage::putFile('public/avatars', $request->file($key));
					}else
					{
						$item[$key] = Storage::putFile('uploads', $request->file($key));
					}
				}
		        if(array_key_exists($key, $item))
		            $adminmodel->$key = $field->cast($item[$key]);
		    }
		}
		$adminmodel->save();
		return $adminmodel;
		    
	}

	/**
	 * @myno\Param(name="adminmodelid",type="App\User",required=true,prop="adminmodel")
	 **/
	public function update(Request $request, $adminmodel)
	{
		
		$fields = static::$resource::editable();
		
		$item = $request->all();
		
		foreach($fields as $field)
		{
		    $key = $field->column;
		    if($field instanceof BelongsToField)
		    {
		        if(array_key_exists($key, $item))
		        {
		            $value = $item[$key];
		            if(!isset($value))
		            {
		                $adminmodel->$key()->dissociate();
		            }else
		            {
		                $belongsToModel = $field->getModel();
		                $instance = new $belongsToModel;
		                $primaryKey = $instance->getKeyName();
		                //TODO:handle multiple primary keys ?
		                if(isset($value[$primaryKey]))
		                {
		                    $belongsToModel = $belongsToModel::find($value[$primaryKey]);
		                    if(!isset($belongsToModel))
		                    {
		                        throw new ApiException('bad_id', 'bad_id:'.$value[$primaryKey]);
		                    }
		                }else
		                {
		                    throw new ApiException('bad_model_format');
		                }
		                $adminmodel->$key()->associate($belongsToModel);
		            }
		        }
		    }else
		    if($field instanceof HasManyField)
			{
				if(array_key_exists($key, $item))
		        {
		            $value = $item[$key];
		            if(!isset($value))
		            {
		                $adminmodel->$key()->detach();
		            }else
		            {
		                $belongsToModel = $field->getModel();
		                $instance = new $belongsToModel;
		                $primaryKey = $instance->getKeyName();
		                //TODO:handle multiple primary keys ?
		                if(isset($value[$primaryKey]))
		                {
		                    $belongsToModel = $belongsToModel::find($value[$primaryKey]);
		                    if(!isset($belongsToModel))
		                    {
		                        throw new ApiException('bad_id', 'bad_id:'.$value[$primaryKey]);
		                    }
		                }else
		                {
		                    throw new ApiException('bad_model_format');
		                }
		                $adminmodel->$key()->associate($belongsToModel);
		            }
		        }
			}
		    else
		    {
				if($request->hasFile($key))
				{
					if($field instanceof PictureField)
					{
						$item[$key] = Storage::putFile('public/avatars', $request->file($key));
					}else
					{
						$item[$key] = Storage::putFile('uploads', $request->file($key));
					}
				}
		        if(array_key_exists($key, $item))
		            $adminmodel->$key = $field->cast($item[$key]);
		    }
		}
		$adminmodel->save();
		return $adminmodel;
		    
	}

	/**
	* @myno\Param(name="column",required=true)
	**/
	public function viewableMany(Request $request, $column)
	{
		
		$field = static::$resource::field($column);
		$currentResource = static::$resource::getResourceFromModel($field->getModel());
		$fields = $currentResource::viewable();
		if($field->hasFields())
		{
		   $fields = $fields->each(function($field)
		   {
		        $field->editable = False;
		   })->merge($field->getFields());
		}
		return $fields;
		    
	}

	/**
	* @myno\Param(name="adminmodelid",type="App\User",required=true,prop="adminmodel")
	* @myno\Param(name="admincolumn",required=true)
	* @myno\Param(name="search",required=false)
	**/
	public function getMany(Request $request, $adminmodel, $admincolumn, $search)
	{
		
		$field = static::$resource::field($admincolumn);
		if(!isset($field))
		{
			throw new ApiException('bad_column', 'column '.$admincolumn.' not found');
        }
		$relationResource = Resource::getResourceFromModel($field->getModel());
		if(!isset($field))
		{
			throw new ApiException('bad_resource', 'column '.$admincolumn.' not found');
        }
        $relation = $adminmodel->$admincolumn();
        if($relation instanceof BelongsToMany)
        {
            $relation = $relation->withPivot($field->getFields()->map->column->toArray());
        }
		$result = $relationResource::search($search,'viewable', $relation->select('*'));
		return $result;
		    
	}

	/**
	* @param Request $request
	* @param \App\User $model
	* @param array $fields
	* @return \App\User
	*/
	protected function updateModel(Request $request, $model, $fields)
	{
		
		$item = $request->all();
		foreach($fields as $field)
		{
		    $key = $field->column;
		    if($field instanceof BelongsToField)
		    {
		        if(array_key_exists($key, $item))
		        {
		            $value = $item[$key];
		            if(!isset($value))
		            {
		                $model->$key()->dissociate();
		            }else
		            {
		                $belongsToModel = $field->getModel();
		                $instance = new $belongsToModel;
		                $primaryKey = $instance->getKeyName();
		                //TODO:handle multiple primary keys ?
		                if(isset($value[$primaryKey]))
		                {
		                    $belongsToModel = $belongsToModel::find($value[$primaryKey]);
		                    if(!isset($belongsToModel))
		                    {
		                        throw new ApiException('bad_id', 'bad_id:'.$value[$primaryKey]);
		                    }
		                }else
		                {
		                    throw new ApiException('bad_model_format');
		                }
		                $model->$key()->associate($belongsToModel);
		            }
		        }
		    }else
		    if($field instanceof HasManyField)
			{
				if(array_key_exists($key, $item))
		        {
		            $value = $item[$key];
		            if(!isset($value))
		            {
		                $model->$key()->detach();
		            }else
		            {
		                $belongsToModel = $field->getModel();
		                $instance = new $belongsToModel;
		                $primaryKey = $instance->getKeyName();
		                //TODO:handle multiple primary keys ?
		                if(isset($value[$primaryKey]))
		                {
		                    $belongsToModel = $belongsToModel::find($value[$primaryKey]);
		                    if(!isset($belongsToModel))
		                    {
		                        throw new ApiException('bad_id', 'bad_id:'.$value[$primaryKey]);
		                    }
		                }else
		                {
		                    throw new ApiException('bad_model_format');
		                }
		                $model->$key()->associate($belongsToModel);
		            }
		        }
			}
		    else
		    {
				if($request->hasFile($key))
				{
					if($field instanceof PictureField)
					{
						$item[$key] = Storage::putFile('public/avatars', $request->file($key));
					}else
					{
						$item[$key] = Storage::putFile('uploads', $request->file($key));
					}
				}
		        if(array_key_exists($key, $item))
		            $model->$key = $field->cast($item[$key]);
		    }
		}
		$model->save();
		return $model;
		    
	}

	/**
	* @myno\Param(name="adminmodelid",type="App\User",required=true,prop="adminmodel")
	* @myno\Param(name="adminrelationid",required=true)
	* @myno\Param(name="admincolumn",required=true)
	**/
	public function addMany(Request $request, $adminmodel, $admincolumn,$adminrelationid)
	{
		
		$related = $adminmodel->$admincolumn()->getRelated();
		$relation = $related::find($adminrelationid);
		if(!isset($relation))
		{
		    throw new ApiException('bad_relation_id', 'bad relation id '.$admincolumn.' '.$adminrelationid);
		}
		try
		{
		    $adminmodel->$admincolumn()->save($relation);
		}catch(\Exception $e)
		{
		    throw new ApiException('error');
		}
		return $adminrelationid;
		    
	}

	/**
	* @myno\Param(name="adminmodelid",type="App\User",required=true,prop="adminmodel")
	* @myno\Param(name="adminrelationid",required=true)
	* @myno\Param(name="admincolumn",required=true)
	**/
	public function removeMany(Request $request, $adminmodel, $admincolumn,$adminrelationid)
	{
		
		$field = static::$resource::field($admincolumn);
		if(!isset($field))
		{
			throw new ApiException('bad_column', 'column '.$admincolumn.' not found');
		}
		$pivotModel = $this->getManyModel($request, $adminmodel, $admincolumn);
		if(!isset($pivotModel))
		{
			throw new ApiException('bad_pivot','unable to retrieve pivot data');
		}
		$pivot = $pivotModel->pivot;
		$pivot->delete();
		    
		    
	}

	/**
	* @myno\Param(name="adminmodelid",type="App\User",required=true,prop="adminmodel")
	* @myno\Param(name="admincolumn",required=true)
	**/
	public function updateMany(Request $request, $adminmodel, $admincolumn)
	{
		
		$field = static::$resource::field($admincolumn);
		if(!isset($field))
		{
			throw new ApiException('bad_column', 'column '.$admincolumn.' not found');
		}
		$data = $this->getManyModel($request, $adminmodel, $admincolumn);
		if(!isset($data))
		{
			throw new ApiException('bad_pivot','unable to retrieve pivot data');
		}
		return $this->updateModel($request, $data->pivot, $field->getFields());
	}

	/**
	* @param Request $request
	* @param \App\User $adminmodel $model to change
	* @param string $admincolumn $column of moidel to change
	* @return \App\User
	*/
	protected function getManyModel(Request $request, $adminmodel, $admincolumn)
	{
		
		$field = static::$resource::field($admincolumn);
		if(!isset($field))
		{
			throw new ApiException('bad_column', 'column '.$admincolumn.' not found');
		}
		$relationResource = Resource::getResourceFromModel($field->getModel());
		if(!isset($field))
		{
			throw new ApiException('bad_resource', 'column '.$admincolumn.' not found');
		}
		$columns = [$adminmodel->$admincolumn()->getRelatedPivotKeyName(),$adminmodel->$admincolumn()->getForeignPivotKeyName()];
		$tablename = $adminmodel->$admincolumn()->getTable();
		$table = Table::getTable($tablename);
		if(!isset($table))
		{
		    throw new \Exception('bad_table','Table name '.$tablename.' not found - have you run table:cache ?');
		}
		$cols = array_merge($columns, $field->getFields()->map->column->toArray());
		if($table->isUnique(...$columns))
		{
		    $query = array_reduce($columns, function($previous, $column) use($request)
		    {
		        if(!isset($request->$column))
		        {
		            throw new \Exception('bad_data');
		        }
		        $previous[$column] = $request->$column;
		        return $previous;
		    }, []);
		}else{
			if(!isset($request->old))
			{
				throw new ApiException('old_missing','old data is required');
			}
			$old = std($request->old);
			$query = array_reduce($cols, function($previous, $column) use($old, $columns)
		    {
				if(!property_exists($old, $column))
		        {
					if(!in_array($column, $columns))
		            	throw new \Exception('bad_data');
				}else
				{
					$previous[$column] = $old->$column;
				}
		        return $previous;
			}, []);
		}
		return $adminmodel->$admincolumn()->where($query)->withPivot($cols)->first();
			
	}
}