<?php 
namespace Core\Util;
use Core\Util\ClassWriter\Uses;
use Core\Util\ClassWriter\Property;
use Core\Util\ClassWriter\Constant;
use Core\Util\ClassWriter\Method;
use ReflectionMethod;
use Logger;
class Command
{
    public static function executeRaw($command, $params = NULL, $cwd = NULL)
    {
        if(!isset($cwd))
        {
            $cwd = getcwd();
        }
        if(isset($params))
        {
            $command.=" ".join(" ",$params);
        }

        $descriptors = array( /* 0 => array("pipe", "r"), */ // // stdin est un pipe où le processus va lire
            0 => array('file','php://stdin','r'),
            1 => array('file','php://stdout','w'),
            2 => array('file', 'php://stderr','w'),
        );
        $process = proc_open($command,  $descriptors, $pipes, $cwd);
        if (is_resource($process)) {
            return proc_close($process);
        }
        return 1;
    }
	public static function execute($command, $params = NULL, $execute = True, $silent = False)
    {
        if(isset($params))
        {
            $command.= " ".implode(" ", $params);
        }
        // if(!$silent)
        //     Logger::info("execute: ".$command);
        $command.=" 2>&1";
        $output = [];
        $returnValue = NULL;
        if($execute)
        {
            $descriptorspec = array(
               0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
               1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
               2 => array("pipe", "w")    // stderr is a pipe that the child will write to
            );

            $process = proc_open($command, $descriptorspec, $pipes, getcwd());
            if (is_resource($process)) {
                while ($s = fgets($pipes[1])) {
                   if(!$silent)
                    echo $s;
                   $output[] = $s;
                }
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $returnValue = proc_close($process);
            }
        }
        return ["output"=>$output, "returnValue"=>$returnValue, "success"=>$returnValue==0];
    }
}
