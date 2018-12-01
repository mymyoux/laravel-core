<?php

namespace Core\Services;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use App;
use Illuminate\Support\Debug\Dumper;
use Auth;

class Logger
{
	CONST LOG_CRITICAL  = 6;
    CONST LOG_ERROR     = 5;
    CONST LOG_BG_DEBUG  = 4;
    CONST LOG_WARN      = 3;
    CONST LOG_DEBUG     = 2;
    CONST LOG_INFO      = 1;
    CONST LOG_NONE      = 0;

	private $debug          = true;
    private $metrics        = [];
    private $critical       = null;
    private $display_time   = true;
    private $config_query   = null;
    private $output         = null;
    private $outputs         = null;
    public $timestamp = NULL;

    public function __construct()
    {

        if(App::runningInConsole())
        {
            $input  = new \Symfony\Component\Console\Input\ArgvInput();
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();

            $this->output = new \Illuminate\Console\OutputStyle($input, $output);
        }
    }

    public function warn( $message )
    {
        if (true === App::runningInConsole())
        {
             if (! $this->output->getFormatter()->hasStyle('warning')) {
                $style = new OutputFormatterStyle('yellow');

                $this->output->getFormatter()->setStyle('warning', $style);
            }
        }

    	$this->log($message, self::LOG_WARN);
    }

    public function error( $message )
    {
    	$this->log($message, self::LOG_ERROR);
    }

    public function critical( $message )
    {
        $this->critical = $message;
        $this->log($message, self::LOG_CRITICAL);
    }
    public function fatal($message)
    {
        $this->critical($message);
        exit();
    }

    public function normal( $message )
    {
    	$this->log($message, self::LOG_NONE);
    }

    public function info( $message )
    {
    	$this->log($message, self::LOG_INFO);
    }

    public function color( $message, $style, $rc = true )
    {
        $begin = "";
        if (true === $this->display_time)
            $begin = '[' . date('Y-m-d H:i:s') . '] ' . $begin;
        if(is_array($data))
        {

        }
    }
    public function debug( $message, $bg = false )
    {
    	$this->log($message, (false === $bg ? self::LOG_DEBUG : self::LOG_BG_DEBUG));
    }
    private function log( $msg, $type = self::LOG_NONE )
    {
        $message = $msg;
        if (false === $this->debug && $type < self::LOG_ERROR) return;
        $begin = $end = '';
        $style = null;

        switch ( $type )
        {
            case self::LOG_CRITICAL :
                $style = 'error';
                // $color 	= Color::RED;
                $begin 	= '/!\\ ';
            break;
            case self::LOG_BG_DEBUG :
                $style = 'error';
                // $color = Color::BLUE;
            break;
            case self::LOG_ERROR :
                $style = 'error';
                // $color = Color::LIGHT_RED;
            break;
            case self::LOG_WARN :
                $style = 'warning';
                // $color = Color::YELLOW;
            break;
            case self::LOG_INFO :
                $style = 'info';
                // $color = Color::GREEN;
            break;
            case self::LOG_DEBUG :
                $style = 'question';
                // $color = Color::LIGHT_BLUE;
            break;
            default:
            	// $color = Color::NORMAL;
            break;
        }

        if ($begin !== $end)
        {
            if (self::LOG_CRITICAL === $type)
                $end .= " /!\ ";
        }

        if (true === $this->display_time)
            $begin = '[' . date('Y-m-d H:i:s') . '] ' . $begin;
        if (true === App::runningInConsole())
        {
            if (App::runningInCron())
            {
                echo $begin;
                if(is_object($message) || is_array($message))
                {
                     (new Dumper)->dump($message);
                }else
                {
                    echo $message;
                }

                 echo $end . PHP_EOL;
            }
            else
            {
                if(is_object($message) || is_array($message))
                {
                    echo $begin;
                     (new Dumper)->dump($message);
                     echo $end;
                }else
                {
                    $message = "$begin$message$end";
                    if($style)
                    {
                        $message = "<$style>$message</$style>";
                    }
                     $this->output->writeln($message);
                }
            }
            if(is_object($msg) || is_array($msg))
            { 
                $msg = json_encode($msg, \JSON_PRETTY_PRINT);
            }
        }else
        {

        }
    }
}
