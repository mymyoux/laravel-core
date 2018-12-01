<?php
namespace Core\Admin;
use File;
use Core\Util\ClassHelper;
use ReflectionClass;
class Resource
{
    /**
     * Default model name
     */
    protected $model = NULL;

    /**
     * Indicates if this resources is ignored in menu
     * @var boolean
     */
    protected $menu = NULL;

    protected function editable()
    {
        return self::fields()->filter->editable->values();
    }
    protected function viewable()
    {
        return self::fields()->filter->viewable->values();
    }
    protected function listable()
    {
        return self::fields()->filter->listable->values();
    }
    protected function compact()
    {
        return self::fields()->filter->compact->values();
    }
    protected function searchable()
    {
        return self::fields()->filter->searchable->values();
    }
    protected function field($column)
    {
        return self::fields()->filter(function($item) use($column)
        {
            return $item->column === $column;
        })->first();
    }
    protected static function _getResources()
    {
        $resources = collect(File::allfiles(app_path()));
        $resources = $resources->map(function($rawitem)
        {
            $item = new \stdClass;
            $item->file = $rawitem;
            $item->infos = ClassHelper::getInformations($item->file->getPathName());
            $item->cls = new ReflectionClass($item->infos->fullname);
            return $item;
        })->filter(function($item)
        {
            return $item->cls->isSubclassOf('Core\Admin\Resource');
        })->map(function($item)
        {
            $item->properties = $item->cls->getDefaultProperties();
            $item->menu = $item->properties['menu']??NULL;
            $item->instance = $item->cls->newInstance();
            return $item;
        })->filter(function($item)
        {
            return isset($item->menu);
        });
        return $resources;
    }
    public static function getResources()
    {
        $resources = self::_getResources();
        return $resources->map(function($item)
        {
            return $item->menu;
        });
    }
    public static function getResourceFromModel($model)
    {
        return self::_getResources()->filter(function($item) use($model)
        {
            return $item->instance->model == $model;
        })->first()->instance;
    }
    protected function search($search, $endpoint = 'viewable', $request = NULL)
    {
        //dd($this->fields()->map->column);
        $fields = $this->fields()
        ->filter->$endpoint
        ->filter->searchable
        ->values();

        $columns = $fields->map->column;
        
        $query = $request??$this->model::select('*');
        if(mb_strlen($search))
        {
            $query->where(function($query) use($fields, $search)
            {
                foreach($fields as $field)
                {
                    $query = $field->search($query, $search);
                }
            });
        }

        return $query->paginate(15);
    }

    protected function makeRequest($endpoint)
    {
        $fields = $this->fields()->filter->$endpoint;
        $columns = $fields->map->column;


        $simples = $columns->filter(function($item)
        {
            return strpos($item, '.') === False;
        });
        $withs = $columns->filter(function($item)
        {
            return strpos($item, '.') !== False;
        })->map(function($item)
        {
            $parts = explode('.', $item);
            array_pop($parts);
            return join('.', $parts);
        });

        foreach($fields as $field)
        {
            if($field instanceof \Core\Admin\Fields\BelongsToField)
            {
                $withs->push($field->column);
            }
        }
        $withs = $withs->unique();



        $model = $this->model;
        $request = new $model;



        //with
        foreach($withs as $with)
        {
            $request = $request->with($with);
        }
        return $request->find(request()->id);

    }
    protected function makeListRequest($endpoint)
    {
        $fields = $this->fields()->filter->$endpoint;
        $columns = $fields->map->column;

        $simples = $columns->filter(function($item)
        {
            return strpos($item, '.') === False;
        });
        $withs = $columns->filter(function($item)
        {
            return strpos($item, '.') !== False;
        })->map(function($item)
        {
            $parts = explode('.', $item);
            array_pop($parts);
            return join('.', $parts);
        })->unique();


        //$order = $request->order??


        $model = $this->model;
        $list = new $model;


        //with
        foreach($withs as $with)
        {
            $list = $list->with($with);
        }

        return $list->paginate(15)->map(function($item) use($columns)
        {
            foreach($columns as $column)
            {
                if(strpos($column,'.') === False)
                    continue;
                $keys = explode('.', $column);

                $current = $item;
                foreach($keys as $key)
                {
                    $current = $current[$key];
                }
                $item->$column = $current;
            }
            return $item;
        })->map(function($item)
        {   
            //TODO:remove hidden columns


            return $item;  
        });
    }
      /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }
}