<?php

namespace Core\Console\Commands\Admin;
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
class Build extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:build {--model=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build Admin Resources';

    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $model = $this->option('model');
        
        $models = collect(File::allfiles(app_path()));
        
        $this->modelContent = std([
            'create',
            'update',
            'viewableMany',
            'getMany',
            'updateModel',
            'addMany',
            'removeMany',
            'updateMany',
            'getManyModel'
        ]);



        $database = config('database.connections.mysql.database');
        $cast_mapping = ["int"=>"integer","varchar"=>"string","lontext"=>"string","timestamp"=>"datetime","text"=>"string","datetime"=>"datetime","float"=>"float","tinytext"=>"text","bigint"=>"integer","tinyint"=>"integer","date"=>"date","smallint"=>"integer","double"=>"double","enum"=>"string","longtext"=>"string"];
        $structure = DB::select("SELECT INFORMATION_SCHEMA.COLUMNS.* FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".$database."' ORDER BY TABLE_NAME ASC, INFORMATION_SCHEMA.COLUMNS.ORDINAL_POSITION ASC");
        $structure = array_reduce($structure, function($previous, $item) use($cast_mapping)
        {
            if(!isset($previous[$item->TABLE_NAME]))
            {
                $previous[$item->TABLE_NAME] = new \stdClass;
                $previous[$item->TABLE_NAME]->table = $item->TABLE_NAME;
                $previous[$item->TABLE_NAME]->columns = [];
                $previous[$item->TABLE_NAME]->primaries = [];
            }
 
            if(isset($cast_mapping[$item->DATA_TYPE]))
            {
                if(starts_with($item->COLUMN_NAME, 'is_') || (isset($item->COLUMN_COMMENT) && strpos($item->COLUMN_COMMENT, "bool")!==False))
                {
                    $item->column_type = "boolean";
                }else
                {
                    $item->column_type = $cast_mapping[$item->DATA_TYPE];
                }
                //timestamp
                if($item->DATA_TYPE == 'timestamp')
                {
                    preg_match("/timestamp\(([0-9]+)\)/", $item->COLUMN_TYPE, $result);
                    if(!empty($result))
                    {
                        $length = (int)$result[1];
                        if($length > 0)
                        {
                            $item->column_type = 'datetime:Y-m-d H:i:s.u';
                        }
                    }
                }
                //json
                if($item->DATA_TYPE == 'longtext')
                {
                    if(isset($item->COLUMN_COMMENT) && strpos($item->COLUMN_COMMENT, "json")!==False)
                    {
                        $item->column_type = 'array';
                    }
                }
            }else
            {
                Logger::error($item->DATA_TYPE." not recognized");
                //default
                $item->column_type = "string";
            }
            if($item->column_type == 'string' && (strpos($item->COLUMN_NAME, 'avatar') !== False || strpos($item->COLUMN_NAME, 'picture') !== False))
            {
                $item->column_type = 'picture';
            }
            //indexes
            $item->is_unique = $item->COLUMN_KEY == 'UNI';
            $item->is_primary = $item->COLUMN_KEY == 'PRI';
            $item->is_nullable = $item->IS_NULLABLE == 'YES';
            $item->size = $item->CHARACTER_MAXIMUM_LENGTH??$item->NUMERIC_PRECISION;
            $item->has_default = isset($item->COLUMN_DEFAULT);
            if($item->is_primary)
            {
                $previous[$item->TABLE_NAME]->primaries[] = $item;
            }
            $previous[$item->TABLE_NAME]->columns[$item->COLUMN_NAME] = $item;
            
            return $previous;
        }, []);




        $models = $models->map(function($rawitem)
        {
            $item = new \stdClass;
            $item->file = $rawitem;
            $item->infos = ClassHelper::getInformations($item->file->getPathName());
            $item->cls = new ReflectionClass($item->infos->fullname);
            
            return $item;
        })->filter(function($item)
        {
            return $item->cls->isSubclassOf('Illuminate\Database\Eloquent\Model');
        })->map(function($item) use($structure)
        {
            $item->properties = $item->cls->getDefaultProperties();
            $item->instance = $item->cls->newInstance();
            $item->structure = $structure[$item->instance->getTable()];
            $item->methods = collect($item->cls->getMethods())->filter(function($method) use($item)
            {
                return $method->isPublic() && $method->getNumberOfRequiredParameters () == 0 && $method->getDeclaringClass()->isSubclassOf('Illuminate\Database\Eloquent\Model');
            })->values();
            $item->relations = $item->methods->map(function($method) use($item)
            {
                try{
                    $name = $method->getName();
                    $relation = $item->instance->$name();
                    if(!isset($relation))
                        return NULL;
                    if($relation instanceof Relation)
                    {
                        $r = new \stdClass;
                        $r->relation = $relation;
                        $r->name = $name;
                        $r->column = $relation->getForeignKey();
                        return $r;
                    }
                    return NULL;
                }catch(\Exception $e)
                {
                    return NULL;
                }
            })->filter(function($method){
                return isset($method);
            })->values();
            $item->relation_names = $item->relations->pluck('column')->flatten()->toArray();
            $item->relations = $item->relations->keyBy('column');
            return $item;
        });
        $core_resources_path = base_path('bootstrap/admin/Resources');
        $core_controllers_path = base_path('bootstrap/admin/Controllers');
        $app_resources_path = app_path('Admin/Resources');
        $app_controllers_path = app_path('Http/Controllers/Admin');
        if(!File::exists($app_resources_path))
        {
            File::makeDirectory($app_resources_path, 0755, true);
        }
        if(!File::exists($app_controllers_path))
        {
            File::makeDirectory($app_controllers_path, 0755, true);
        }
        if(!File::exists($core_resources_path))
        {
            File::makeDirectory($core_resources_path, 0755, true);
        }
        if(!File::exists($core_controllers_path))
        {
            File::makeDirectory($core_controllers_path, 0755, true);
        }
        $models->each(function($item) use($core_resources_path, $app_resources_path, $core_controllers_path, $app_controllers_path, $structure, $models)
        {
            //// ------- resources ------- ////
            $path_core = join_paths($core_resources_path, $item->infos->class.'Resource.php');
            $path_app= join_paths($app_resources_path, $item->infos->class.'Resource.php');


            //write core files
            $coreResource = new ClassWriter;
            $coreResource->setNamespace('Admin\Resources');
            $coreResource->setClassName($item->infos->class.'Resource');
            $coreResource->setExtends('\Core\Admin\Resource');
            $coreResource->addProperty('model','protected',False,$item->infos->fullname);
            //menu name
            $coreResource->addProperty('menu','protected',False,["label"=>$item->infos->class, "url"=>'/list/'.kebab_case($item->infos->class),"icon"=>NULL],"/**\n\t* Array to represent menu item.\n\t* If null will not be present on the admin menu\n\t**/");
            $fields = [];
            if(isset($item->properties['casts']))
            {
                //$columns = $item->properties['casts'];
                $columns = array_map(function($item){return $item->column_type;}, $item->structure->columns);
                $primaries = isset($item->properties['primaryKey'])?$item->properties['primaryKey']:[];
                if(!is_array($primaries))
                {
                    $primaries = isset($primaries)?[$primaries]:[];
                }
                $fields = [];
                $used = [];
                $fields = $this->getFields($item, $columns, $primaries, $item->structure);
               
            }

            //TODO:belongsToMany
            //TODO:hasOne
            //TODO:hasMany

            $relations = [];

            foreach($item->methods as $name=>$method)
            {
                try
                {
                    if($this->isRelationMethod($item, $method))
                    {
                        $method_name = $method->name;
                        $relation = $item->instance->$method_name();
                        $relations[$method->name] =last(explode('\\',get_class($relation)));
                    }
                }catch(\Exception $e)
                {

                }
            }

            
            $item->model_relations = $relations;

            $models_names = $models->map(function($item){return $item->infos->fullname;});
            foreach($item->model_relations as $key=>$type)
            {
                if($type == 'BelongsToMany')
                {



                    $relation = $item->instance->$key();
                    if($models_names->search(get_class($relation->getModel())) === False)
                    {
                        //ignore non handled models
                        continue;
                    }
                    $table = $relation->getTable();
                    $primaries = [$relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName()];

                    $table_structure = $structure[$table];

                    $columns =   array_filter($table_structure->columns, function($item)
                    {
                        return !$item->is_primary;
                    });
                    $columns =  array_map(function($item){
                        return $item->column_type;}
                    , $columns);
                    $columns = array_filter($columns, function( $key) use($primaries)
                    {
                        return !in_array($key, $primaries);
                    }, \ARRAY_FILTER_USE_KEY);
                    $f = $this->getFields($item, $columns, [], $table_structure);
                    if($item->infos->class == 'User' && $key == 'connectors')
                    {
                        dd($models->map(function($item){return $item->infos->fullname;}));
                        dd($relation->getModel());
                    }
                    $field = std([
                        'field'=>'HasManyField',
                        'name'=>$key,
                        'options'=>
                        ['model'=>"'".get_class($relation->getModel())."'",
                        'listable',
                        'orderable',
                        'searchable'
                        ],
                        'fields'=>$f,
                        'fullname'=>'Core\Admin\Fields\HasManyField'
                    ]);
                    $fields[] = $field;
                    // dd($structure[$table]);
                    // dd($columns);
                }
            }




            foreach($fields as $field)
            {
                if(!in_array($field->fullname, $used))
                {
                    $coreResource->addUse($field->fullname);
                    $used[] = $field->fullname;
                }
            }


            $method = $this->fieldsToMethod($fields, True);
            $coreResource->addFunction('fields', '', $method, 'protected');
            $coreResource->write($path_core);

            
            $appResource = new ClassWriter;
            $appResource->setNamespace('App\Admin\Resources');
            $appResource->setClassName($item->infos->class.'Resource');
            $appResource->setExtends('\\'.$coreResource->getFullName());
            
            if(!File::exists($path_app))
            {
                $appResource->write($path_app);
            }



            //// ----------      Controllers ------------- ////
            $path_core = join_paths($core_controllers_path, $item->infos->class.'Controller.php');
            $path_app= join_paths($app_controllers_path, $item->infos->class.'Controller.php');
            

            $core = new ClassWriter;
            $core->setNamespace('Admin\Controllers');
            $core->setClassName($item->infos->class.'Controller');
            $core->setExtends('Controller');
            $core->addUse('Illuminate\Http\Request');
            $core->addUse('Illuminate\Database\Eloquent\Relations\BelongsToMany');
            $core->addUse('Core\Exception\ApiException');
            $core->addUse('Core\Admin\Fields\BelongsToField');
            $core->addUse('Core\Admin\Fields\PictureField');
            $core->addUse('Core\Admin\Fields\HasManyField');
            $core->addUse('Core\Admin\Resource');
            $core->addUse('Core\Api\Annotations', 'myno');
            $core->addUse('Core\Admin\Controller');
            $core->addUse('Core\Admin\Components\ListComponent');
            $core->addUse('Core\Database\Eloquent\Table');
            $core->addUse('Core\Database\Eloquent\Relations\Pivot');
            $core->addUse('Storage');
            $core->addUse($item->infos->fullname);
            $core->addUse($appResource->getFullName());
            
            $core->addProperty('resource','public',True, $appResource->getFullName(), "/**\n\t* Resource class\n\t**/");
        
            //list of components for list view
            $method = "return [\n".
            "\tListComponent::make(),\n".
            "];";
            $core->addFunction('components', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");

            //headers for list view
            $method = "return ".$appResource->getClassName()."::listable();";
            $core->addFunction('listable', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");

            //headers for edit view
            $method = "return ".$appResource->getClassName()."::editable();";
            $core->addFunction('editable', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");


            //headers for view from external item
            $method = "return ".$appResource->getClassName()."::viewable();";
            $core->addFunction('viewable', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");


            $method = "return ".$appResource->getClassName().'::search($request->search);';
            $core->addFunction('search', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Param(name=\"search\",required=true)\n\t**/");



            //data for list view
            $method = "return ".$appResource->getClassName()."::makeListRequest('listable');";
            $core->addFunction('list', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");

            //data for list view
            $method = "return ".$appResource->getClassName()."::makeRequest('editable');";
            $core->addFunction('edit', 'Request $request', $method, 'public', False,"/**\n\t* @myno\\Api\n\t**/");


            // foreach($this->modelContent as $key)
            // {
            //     $result = $this->make($key, $item, $appResource);
            //     $core->addfunction($result->name, $result->params, $result->body, $result->visibility, $result->static, $result->docs);
            // }
            

            $core->write($path_core);


            if(!File::exists($path_app))
            {
                $cls = new ClassWriter;
                $cls->setNamespace('App\Http\Controllers\Admin');
                $cls->setClassName($item->infos->class.'Controller');
                $cls->setExtends('\\'.$core->getFullName());
                $cls->write($path_app);
            }
        });
    }
    protected function fieldsToMethod($fields, $collect = False)
    {
        $fields = array_map(function($item)
        {
            $field = $item->field;
            $name = $item->name;
            $options = $item->options;

            $str = "\t".$field.'::make(\''.$name.'\')';
            foreach($options as $key=>$value)
            {
                if(is_numeric($key))
                {
                    $key = $value;
                    $value = null;
                }
                if(is_array($value))
                {
                    foreach($value as $v)
                    {
                        $str.='->'.$key.'('.(isset($v)?$v:'').')';
                    }
                }else
                {
                    $str.='->'.$key.'('.(isset($value)?$value:'').')';
                }
                
            }
            if(!empty($item->fields))
            {
                $str.= "->addFields([\n";
                $str.= str_replace("\t","\t\t",$this->fieldsToMethod($item->fields, False));
                $str.="\n\t])";
            }

            return $str.',';
        }, $fields);

        $fields = mb_substr(join($fields, "\n"), 0, -1);

        if($collect)
        {
            $method = "return collect([\n".
            $fields ."\n]);";            
        }else{
          return $fields;
        }
        return $method;
    }
    protected function getFields($item,$columns,$primaries, $table_structure)
    {
        $fields = [];
        foreach($columns as $name=>$type)
        {
            $column = $name;
            $options = ['orderable','validator'=>[]];
            //ignore relation for now
            if(in_array($name, $primaries))
            {
                $options['editable'] = 'False';
                $options[] = 'searchable';
            }
            if(in_array($name, $item->relation_names))
            {
                if(!isset($item->relations[$name]))
                {
                    Logger::warn('relation '.$name.' not found');
                    continue;
                }
                $type ='belongsTo';
                $options['model'] = "'".get_class($item->relations[$name]->relation->getRelated())."'";
                $name = $item->relations[$name]->name;
                //continue;
            }
            if($type == 'integer')
            {
                $options['numeric'] = 'True';
            }
            if($type == 'string')
            {
                $options[] = 'searchable';
            }
            $field = studly_case($type).'Field';
            $fullfield = 'Core\Admin\Fields\\'.$field;
            //TODO:utiliser string par défaut ? 
            if(!class_exists('\\'.$fullfield))
            {
                continue;
            }
            if(strpos($column, 'email') !== False)
            {
                $options['validator'][] = "'email'";
            }
            if(isset($table_structure->columns[$column]))
            {
                $dbColumn = $table_structure->columns[$column];
                if(!$dbColumn->is_nullable)
                {
                    $options['nullable'] = 'False';
                    if(!$dbColumn->has_default)
                    {
                        $options['required'] = 'True';
                    }
                }
                if(isset($dbColumn->size))
                {
                    $options['max'] = $dbColumn->size;
                }
            }else
            {
                Logger::warn($item->info->class.' has '.$name.' in cast but it doesn\'t exist in database');
            }

            if($name == 'id' || strpos($name, 'avatar') !== False || $type == 'string')
            {
                $options[] = 'listable';
            }
            if($name == 'id' || strpos($name, 'avatar') !== False || $name == 'name')
            {
                $options[] = 'viewable';
            }
            $fields[] = std([
                'field'=>$field,
                'name'=>$name,
                'options'=>$options,
                'fullname'=>$fullfield,
                'viewable'=>$name == 'id' || strpos($name, 'avatar') !== False || $name == 'name'
            ]);
        }
        usort($fields, function($a, $b)
        {
            if($a->name == 'id')
                return -1;
            if($b->name == 'id')
                return 1;

            $a_is_avatar = strpos($a->name, 'avatar') !== False;
            $b_is_avatar = strpos($b->name, 'avatar') !== False;

            if($a_is_avatar == $b_is_avatar)
                return 0;
            if($a_is_avatar)
                return -1;
            if($b_is_avatar)
                return 1;
            return 0;
        });
        return $fields;
    }
    protected function isRelationMethod($item, $method)
    {
        $names = ['belongsTo','hasMany','hasOne','belongsToMany'];
        $body = ClassHelper::getMethodBody($method->class.'@'.$method->name,False,True);
        foreach($names as $name)
        {
            if(preg_match('/:: *'.$name.' *\(/', $body, $matches))
            {
                return True;
            }
            if(preg_match('/-> *'.$name.' *\(/', $body, $matches))
            {
                return True;
            }
        }
        return False;
    }
    protected function make($type, $item, $appResource)
    {
        $local_name = $type.'Model';

        $reflectionClass = new ReflectionClass($this);
        $reflectionMethod = $reflectionClass->getMethod($local_name);
        $docs = $reflectionMethod->getDocComment();
        $docs = str_replace('%model%', $item->infos->fullname, $docs);

        $body = ClassHelper::getMethodBody(self::class.'@'.$local_name, False, True);
        $body = str_replace('$resource', $appResource->getClassName(), $body);
        $body = preg_replace("/^(\t\t|        )/m","", $body);


        $full_content = ClassHelper::getMethodBody(self::class.'@'.$local_name, True, False);

        $matches = [];
		preg_match("/([a-z]+) +(static)? *function +([^\( ]+)\(([^)]*)\)/i", $full_content, $matches);
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
			$params = $matches[4];
        }
        $copy = std(['name'=> $type]);
        $copy->body = $body;
        $copy->docs = $docs;
        $copy->static = $static;
        $copy->params = $params;
        $copy->visibility = $visibility;
       

        return $copy;
    }
   
}
