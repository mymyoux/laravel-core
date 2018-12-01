<?php

namespace Core\Database\Migrations;

use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Core\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use DateTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
class Migrator extends BaseMigrator
{
    public function getMigrationFiles($paths)
    {
        //handle files
        $all = parent::getMigrationFiles($paths);
        $files = Collection::make($paths)->filter(function($item)
        {
            return file_exists($item) && is_file($item);
        })->filter()->sortBy(function ($file) {
            return $this->getMigrationName($file);
        })->values()->keyBy(function ($file) {
            return $this->getMigrationName($file);
        })->all();
        $all = array_merge($all, $files);
        return $all;
    }
    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        if(mb_strpos($file, '_') === False)
            $class = Str::studly($file);
        else
            $class = Str::studly(implode('_', array_slice(explode('_', $file), 4)));
        return new $class;
    }

}
