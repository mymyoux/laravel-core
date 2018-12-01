<?php

namespace Core\Console\Commands\Table;
use Logger;
use Core\Model\Connector;


use DB;
use Illuminate\Console\Command;
use Core\Util\ClassWriter;
use Core\Util\ClassWriter\Body\Table;
use Core\Util\ClassWriter\Body\General;
use Schema;
use ReflectionClass;
use File;
use Core\Util\ModuleHelper;
use Core\Util\ClassHelper;
use Core\Database\Eloquent\Table as TableStructure;
class Cache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'table:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates table files';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $start = microtime(True);

        //TODO:remove old tables

        $reserved = ['__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'parent', 'print', 'private', 'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor'];

        $files = [];

       

        //Prepare folder
        $destination_folder = $folder = base_path('bootstrap/tables');
        if(!file_exists($folder))
        {
            mkdir($folder, 0777);
        }
        $structure_folder = join_paths($destination_folder, 'Structure');
        if(!file_exists($structure_folder))
        {
            mkdir($structure_folder, 0777);
        }
        //check composer
        $path = base_path('composer.json');
        $composer = json_decode(file_get_contents($path), True);
        $modules = array_keys($composer['autoload']['psr-4']);
        if(!in_array('Tables\\',$modules))
        {
            $composer['autoload']['psr-4']['Tables\\'] = 'bootstrap/tables/';
            file_put_contents($path, json_encode($composer, \JSON_PRETTY_PRINT));
            $this->call('composer:install');
        }
        $previous = array_map(function($item) use($folder)
            {
                return substr($item, strlen($folder)+1);
            }, File::files($folder));

        $platform = Schema::getConnection()->getDoctrineSchemaManager()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');


        //get existing models
        $files = $this->getModelFiles(app_path());
       $tablenames = $files->map(function($item)
       {
           return $item->table;
       })->toArray();
       $files = $files->keyBy('table');

       $database = config('database.connections.mysql.database');
       $cast_mapping = ["int"=>"integer","varchar"=>"string","lontext"=>"string","timestamp"=>"datetime","text"=>"string","datetime"=>"datetime","float"=>"float","tinytext"=>"text","bigint"=>"integer","tinyint"=>"integer","date"=>"date","smallint"=>"integer","double"=>"double","enum"=>"string","longtext"=>"string"];
       $structure = DB::select("SELECT INFORMATION_SCHEMA.COLUMNS.* FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='".$database."' ORDER BY TABLE_NAME ASC, INFORMATION_SCHEMA.COLUMNS.ORDINAL_POSITION ASC");


       $indexes = DB::select("SELECT GROUP_CONCAT(INFORMATION_SCHEMA.STATISTICS.COLUMN_NAME ORDER BY INFORMATION_SCHEMA.STATISTICS.COLUMN_NAME ASC) as columns, IF(INFORMATION_SCHEMA.STATISTICS.NON_UNIQUE,0,1) as 'unique', INFORMATION_SCHEMA.STATISTICS.* FROM INFORMATION_SCHEMA.STATISTICS WHERE INFORMATION_SCHEMA.STATISTICS.TABLE_SCHEMA = '".$database."' GROUP BY INFORMATION_SCHEMA.STATISTICS.INDEX_NAME");
       $structure = array_reduce($structure, function($previous, $item) use($cast_mapping, $indexes)
       {
           if(!isset($previous[$item->TABLE_NAME]))
           {
               $previous[$item->TABLE_NAME] = new \stdClass;
               $previous[$item->TABLE_NAME]->table = $item->TABLE_NAME;
               $previous[$item->TABLE_NAME]->columns = [];
               $previous[$item->TABLE_NAME]->primaries = [];
               $previous[$item->TABLE_NAME]->indexes = [];
           }
           foreach($indexes as $index)
           {
               if($index->TABLE_NAME == $item->TABLE_NAME)
               {
                $previous[$item->TABLE_NAME]->indexes[$index->columns] = ['columns'=>explode(',',$index->columns),'unique'=>$index->NON_UNIQUE == 0,'primary'=>False];
               }
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
           //indexes
           $item->is_unique = $item->COLUMN_KEY == 'UNI';
           $item->is_primary = $item->COLUMN_KEY == 'PRI';
           if($item->is_primary)
           {
               $previous[$item->TABLE_NAME]->primaries[] = $item;
           }
           $previous[$item->TABLE_NAME]->columns[$item->COLUMN_NAME] = $item;

           if(!empty( $previous[$item->TABLE_NAME]->primaries))
           {
               $primaries = array_map(function($item){return $item->COLUMN_NAME;},$previous[$item->TABLE_NAME]->primaries);
               sort($primaries);

                $previous[$item->TABLE_NAME]->indexes[join(',',$primaries)] = ['columns'=>$primaries, 'unique'=>True,'primary'=>True];
           }
           return $previous;
       }, []);
       

       $structure_files = File::allfiles($structure_folder);
       collect($structure_files)->each(function($file)
       {    
           @unlink($file->getPathName());
       });
       //TABLES
       collect($structure)->each(function($table_structure) use($structure_folder)
       {
           
           $cls = new ClassWriter();
           $cls->setNamespace('Tables\Structure');
           $cls->setClassName(TableStructure::sanitize($table_structure->table));
           $cls->addUseTrait('\Core\Database\Eloquent\StructureTableTrait');
           $cls->setType('class');
           $cls->addProperty('casts', 'protected', 
           False, 
           array_reduce($table_structure->columns, function($previous, $item)
           {
               if(isset($item->column_type))
               {
                   $previous[$item->COLUMN_NAME] = $item->column_type;
               }
               return $previous;
           }, [])
           ,"/**\n\t* Cast database columns\n*/");

           $primary = collect($table_structure->primaries)->map->COLUMN_NAME;
           
            $cls->addProperty('primary', 'protected', False,
            $primary->isEmpty()?NULL:($primary->count() == 1 ?$primary->first():$primary),"/**\n\t* Primary key\n*/");

            $cls->addProperty('indexes', 'protected', False,
            $table_structure->indexes,"/**\n\t* Indexes\n*/");
            $path = join_paths($structure_folder, $cls->getclassName().'.php');
            $cls->write($path);
       });










       $ignore = ['migrations'];
       $models = collect($structure)->filter(function($table) use($ignore)
       {
            if(count($table->primaries)!=1)
            {
                return False;
            }
            if(mb_strpos($table->table, '_')!==False)
            {
                return False;
            }
            //TODO:check table commment ? 
            return !in_array($table->table, $ignore);
       });
       $core_files = $this->getModelFiles(core_path());
       $core_names = $core_files->map(function($item)
       {
           return $item->table;
       })->toArray();
       $missings = collect(array_diff($models->keys()->toArray(), array_merge($tablenames, $core_names)));
       if(!$missings->isEmpty())
       {
           $missings->each(function($name) use($models)
           {
                $table = $models[$name];
                $cls = new ClassWriter;
                $cls->setNamespace('App\Model');
                $clsname = studly_case(str_singular($name));
                $cls->setClassName($clsname);
                //$cls->addUse('Core\Database\Eloquent\Model');
                $cls->setExtends('\Core\Database\Eloquent\Model');
                $path = app_path('Model/'.$clsname.'.php');
                if(!file_exists($path))
                {
                    Logger::warn('Created Model file '.$clsname.'.php');
                    $cls->write($path);
                }
           });
           $files = $this->getModelFiles(app_path());
           $tablenames = $files->map(function($item)
           {
               return $item->table;
           })->toArray();
    
           $files = $files->keyBy('table');
       }
        
        DB::statement("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        $result = DB::select("SELECT INFORMATION_SCHEMA.KEY_COLUMN_USAGE.TABLE_NAME, GROUP_CONCAT(INFORMATION_SCHEMA.KEY_COLUMN_USAGE.COLUMN_NAME) as `columns`, INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME, INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_NAME, GROUP_CONCAT(INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_COLUMN_NAME) as referenced_columns, INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.UPDATE_RULE, INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.DELETE_RULE FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS ON INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.CONSTRAINT_SCHEMA = INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_SCHEMA AND  INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS.CONSTRAINT_NAME = INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME WHERE INFORMATION_SCHEMA.KEY_COLUMN_USAGE.REFERENCED_TABLE_SCHEMA = '".$database."' GROUP BY INFORMATION_SCHEMA.KEY_COLUMN_USAGE.CONSTRAINT_NAME;");
        DB::statement("SET SQL_MODE=@OLD_SQL_MODE");

        $relations = collect($result);

        $relations = $relations->filter(function($item) use($tablenames)
        {
            $table = False;
            $referenced = False;

            if(in_array($item->TABLE_NAME, $tablenames))
            {
                $table = True;
            }
            if(in_array($item->REFERENCED_TABLE_NAME, $tablenames))
            {
                $referenced = True;
            }
            if(!$table && strpos($item->TABLE_NAME, '_') !== False)
            {
                //split
                $names = explode('_', $item->TABLE_NAME);
                if(count($names) == 2)
                {
                    if(in_array(str_plural($names[0]), $tablenames) && in_array(str_plural($names[1]), $tablenames))
                    {
                        $table = True;
                    }
                }
            }
            if(!$referenced && strpos($item->REFERENCED_TABLE_NAME, '_') !== False)
            {
                //split
                $names = explode('_', $item->REFERENCED_TABLE_NAME);
                if(count($names) == 2)
                {
                    if(in_array(str_plural($names[0]), $tablenames) && in_array(str_plural($names[1]), $tablenames))
                    {
                        $referenced = True;
                    }
                }
            }
            return $table && $referenced;
        })->map(function($item) use($tablenames)
        {
            $table = False;
            $referenced = False;

            if(in_array($item->TABLE_NAME, $tablenames))
            {
                $table = True;
            }
            if(in_array($item->REFERENCED_TABLE_NAME, $tablenames))
            {
                $referenced = True;
            }
            $item->simple = $table && $referenced;
            return $item;
        });

        $multiple = $relations->filter(function($item)
        {
            return !$item->simple;
        })->reduce(function($previous, $item)
        {
            if(!isset($previous[$item->TABLE_NAME]))
            {
                $previous[$item->TABLE_NAME] = [];
            }
            $previous[$item->TABLE_NAME][] = $item;
            return $previous;
        }, []);
        $relations = $relations->filter(function($item)
        {
            return $item->simple;
        });

       
        

        //belongsTo
        foreach($relations as $relation)
        {
            $relation_name = $relation->columns;
            //ignore multiple columns relations
            if(strpos($relation_name,',')!==False)
                continue;

            //remove id
            $relation_name = join('_',array_filter(explode('_',$relation_name), function($item)
            {
                return $item != 'id';
            }));
            $table = $files[$relation->TABLE_NAME];
            $table->relations[$relation_name] = std(['type'=>'belongsTo','from_num'=>1, 'from'=>$relation->columns,'to_num'=>$structure[$table->table]->columns[$relation->columns]->is_unique?'1':'n', 'to'=>$relation->referenced_columns,'table'=>$relation->REFERENCED_TABLE_NAME]);
        }
        //hasOne or hasMany
        foreach($relations as $relation)
        {
            $table = $files[$relation->TABLE_NAME];
            $relation_name = $relation->columns;
            //ignore multiple columns relations
            if(strpos($relation_name,',')!==False)
                continue;


            $referenced_table = $files[$relation->REFERENCED_TABLE_NAME];
            $referenced_relation_name = $table->table;
            $type = 'hasMany';
            if($structure[$table->table]->columns[$relation->columns]->is_unique)
            {
                $type = 'hasOne';
                $referenced_relation_name = str_singular($table->table);

            }
            if(isset($referenced_table->relations[$referenced_relation_name]))
            {
                $relation_name = join('_',array_filter(explode('_',$relation_name), function($item)
                {
                    return $item != 'id';
                }));
                $referenced_relation_name .= '_as_'.$relation_name;
            }
            // $referenced_relation_name = 
            $referenced_table->relations[$referenced_relation_name] = std(['type'=>$type,'from_num'=>1, 'to_num'=>$type == 'hasOne'?1:'n','from'=>$relation->referenced_columns,'to'=>$relation->columns,'table'=>$relation->TABLE_NAME]);
        }
        foreach($multiple as $relation)
        {
            $relation1 = $relation[0];
            $relation2 = $relation[1];
            if(strpos($relation1->columns,',')!==False || strpos($relation2->columns,',')!==False)
                continue;

            $table1 = $files[$relation1->REFERENCED_TABLE_NAME];
            $table2 = $files[$relation2->REFERENCED_TABLE_NAME];

            $name1 = $table2->table;
            $name2 = $table1->table;
            //dd($name1);
            //first
            if(!isset($table1->relations[$name1]))
            {
                $table1->relations[$name1] = std(['type'=>"belongsToMany",'from_num'=>'n', 'to_num'=>'n','table'=>$relation1->TABLE_NAME,'from'=>$relation1->columns,'to'=>$relation2->columns,'model'=>$table2->table,'from_id'=>$relation1->referenced_columns,'to_id'=>$relation2->referenced_columns]);   
                $table2->relations[$name2] = std(['type'=>"belongsToMany",'from_num'=>'n', 'to_num'=>'n','table'=>$relation2->TABLE_NAME,'from'=>$relation2->columns,'to'=>$relation1->columns,'model'=>$table1->table,'from_id'=>$relation2->referenced_columns,'to_id'=>$relation1->referenced_columns]);   
            }

        }

        // dd($files->map(function($item)
        // {
        //     return std(['table'=>$item->table,'relations'=>$item->relations]);
        // }));

        $paths = [];
        foreach($files as $file)
        {
            // dd(array_keys((array)$file));
            $name = $file->class;
            $cls = new ClassWriter();
            $cls->setNamespace('Tables'.substr($file->namespace,3));
            $cls->setClassName($file->class);
            $cls->addUseTrait('\Core\Database\Eloquent\TableTrait');
            
            $cls->setType('class');

            //relations
            foreach($file->relations as $name=>$relation)
            {
                if($relation->type == 'belongsToMany')
                {
                    $withTimestamps = '';
                    if(isset($structure[$relation->table]->columns['created_at']) && isset($structure[$relation->table]->columns['updated_at']))
                    {
                        $withTimestamps = '->withTimestamps()';
                    }
                    $cls->addFunction($name, NULL, 'return $this->'.$relation->type.'('."'".$files[$relation->model]->fullname."', '".$relation->table."', '".$relation->from."', '".$relation->to."', '".$relation->from_id."', '".$relation->to_id."')".$withTimestamps.";", 'public', False, "/**\n* (".$relation->from_num.") ".$file->table.".".$relation->from_id." -> ".$relation->table.".".$relation->from." -> ".$relation->model.".".$relation->to_id." (".$relation->to_num.")\n*/");
                }else if($relation->type == 'hasMany')
                {

                    $cls->addFunction($name, NULL, 'return $this->'.$relation->type.'('."'".$files[$relation->table]->fullname."', '".$relation->to."', '".$relation->from."');", 'public', False, "/**\n* (".$relation->from_num.") ".$file->table.".".$relation->from." -> ".$relation->table.".".$relation->to." (".$relation->to_num.")\n*/");
                }else
                {
                    $cls->addFunction($name, NULL, 'return $this->'.$relation->type.'('."'".$files[$relation->table]->fullname."', '".$relation->from."', '".$relation->to."');", 'public', False, "/**\n* (".$relation->from_num.") ".$file->table.".".$relation->from." -> ".$relation->table.".".$relation->to." (".$relation->to_num.")\n*/");
                }
            }
            //dd($struct_table);
            
            
         



            $dirname = substr(str_replace('\\','/',$cls->getNamespace()),6);
            if(starts_with($dirname, '/'))
            {
                $dirname = substr($dirname, 1);
            }
            $path = join_paths($destination_folder, $dirname, $cls->getClassName().'.php');
            $directory = dirname($path);
            if (!File::exists( $directory)) {
                File::makeDirectory($directory, 0755, true);
            }
            
            $paths[] = $path;

            //Check extends of current model
            $loadfile = ClassWriter::load($file->fullname);
            $parent = $loadfile->getExtend();
            if(!starts_with($parent, "Table"))
            {
                //replace parent class with current one
                if(!starts_with($parent, '\\'))
                {
                    $parent = '\\'.$parent;
                }
                $cls->setExtends($parent);
                $content = File::get($file->file);
                $rows = explode("\n", $content);
                $index = 0;
                for($i=0; $i<$file->cls->getStartLine()-1; $i++)
                {
                    $index+=mb_strlen($rows[$i])+1;
                }
                $end = mb_strpos($content, '{', $index);
                //change extends
                $loadfile->setExtends('\\'.$cls->getFullName());
                $content = mb_substr($content, 0, $index).$loadfile->getClassDefinition()."\n".mb_substr($content, $end);

                Logger::warn('change extends of '.$file->fullname.' '.$file->file->getPathName());
                file_put_contents($file->file->getPathName(), $content);
            }else {
                //previous table model already exists get its current extends
                if(file_exists($path))
                {
                    $previous = ClassWriter::load($cls->getFullName());
                    $previous_extends = $previous->getExtend();
                    if(!starts_with($previous_extends, '\\'))
                    {
                        $previous_extends = '\\'.$previous_extends;
                    }
                    $cls->setExtends($previous_extends);
                }else {
                    Logger::error('Using default extends for model: '.$file->fullname.' '.$file->getPathName());
                    $cls->setExtends('\Core\Database\Eloquent\Model');
                }
            }
            //id 

            if(!isset($structure[$file->table]))
            {
                Logger::warn('no table '.$file->table);
                continue;
            }
            $table_structure = $structure[$file->table];


            $cls->addProperty('table', 'protected', False, $file->table,"/**\n\t* Table name\n*/");

            //one primary
            if(count($table_structure->primaries) == 1)
            {
                $primary = $table_structure->primaries[0];
                //not auto_increment
                if($primary->EXTRA != "auto_increment")
                {
                    $cls->addProperty('incrementing', 'public', False, False);
                }
                if($primary->DATA_TYPE != "int")
                {
                    $cls->addProperty('keyType', 'protected', False, isset($primary->column_type)?$primary->column_type:'string');
                }
                $cls->addProperty('primaryKey', 'protected', False, $primary->COLUMN_NAME, "/**\n\t* Primary key\n*/");

            //several primaries
            }else if(count($table_structure->primaries)>1){
                $cls->addUse('Core\Traits\HasCompositePrimaryKey');
                $cls->addUseTrait('HasCompositePrimaryKey');
                $cls->addProperty('primaryKey', 'protected', False, array_map(function($item)
                {
                    return $item->COLUMN_NAME;
                }, $table_structure->primaries), "/**\n\t* Multiple primaries keys\n*/");
            }
            $use_storage = False;
            foreach($table_structure->columns as $name=>$column)
            {
                if(mb_strpos($name, 'avatar') !== False || mb_strpos($name, 'picture') !== False )
                {
                    $use_storage = True;
                    $ref = "'".$name."'";
                    $body = <<<'EOD'
if(!isset($this->attributes[%ref%]))
{
    return NULL;
}
if(starts_with($this->attributes[%ref%],'http'))
{
    return $this->attributes[%ref%];
}
return config('app.url').Storage::url($this->attributes[%ref%]);
EOD;
                    $body = str_replace('%ref%', $ref, $body);
                    

                    $cls->addFunction('get'.ucfirst($name).'Attribute', [],
$body, 'public', False, "/**\n\t * Gives public url of ".$name."\n**/");
                    
                }
            }
            if($use_storage)
            {
                $cls->addUse('Storage');
            }
            $cls->addProperty('casts', 'protected', 
            False, 
            array_reduce($table_structure->columns, function($previous, $item)
            {
                if(isset($item->column_type))
                {
                    $previous[$item->COLUMN_NAME] = $item->column_type;
                }
                return $previous;
            }, [])
            ,"/**\n\t* Cast database columns\n*/");



            $content = $cls->export();
            if(file_exists($path))
            {
                $previous = file_get_contents($path);
                if($content != $previous)
                {
                    $cls->write($path);
                    Logger::info('Update class '.$cls->getFullName().' '.$path);
                }
            }else
            {
                $cls->write($path);
                Logger::warn('write class '.$cls->getFullName().' '.$path);
            }
        }

        $files = File::allfiles($destination_folder, True);
        foreach($files as $file)
        {
            if(!in_array($file->getPathName(), $paths))
            {
                if(starts_with($file->getPathName(), $structure_folder))
                {
                    continue;
                }
                Logger::error('remove '.$file->getPathName());
                @unlink($file->getPathName());
            }
        }
    }
    protected function insertAt($content, $position, $insert)
    {
        return mb_substr($content, 0, $position+1)
        .$insert
        .mb_substr($content, $position+1);
    }
    protected function getTraits($cls)
    {
        $traits = $cls->getTraits();
        if($cls->getParentClass())
        {
            $traits = array_merge($traits, $this->getTraits($cls->getParentClass()));
        }
        return $traits;
    }
    protected function getModelFiles($path)
    {
        $files = collect(File::allfiles($path));
        $files = $files->map(function($item)
        {
            $infos = std(ClassHelper::getInformations($item->getPathName()));
            $infos->file = $item;
            try
            {
                $infos->cls = new ReflectionClass($infos->fullname);
            }catch(\Exception $e)
            {
                return NULL;
            }
                
            return $infos;
        })->filter(function($item)
        {
            if(!isset($item))
                return False;
            if($item->cls->isAbstract())
            {
                return False;
            }
            return $item->cls->isSubclassOf(\Illuminate\Database\Eloquent\Model::class);
        })
        ->map(function($item)
        {
            try
            {
                $item->instance = new $item->fullname;

            }catch(\Exception $e)
            {
                return NULL;
            }catch(\ErrorException $e)
            {
                return NULL;
            }
             $item->table = $item->instance->getTable();
             $item->relations = [];
             return $item;
        })
        ->filter(function($item)
        {
            return isset($item);
        })
        ->values();
        return $files;
    }
}
