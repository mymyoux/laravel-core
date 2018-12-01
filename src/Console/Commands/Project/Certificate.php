<?php

namespace Core\Console\Commands\Project;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use App;
use Illuminate\Foundation\Providers\ArtisanServiceProvider;
use DB;
use Core\Model\Error;
use File;
use Logger;
use Illuminate\Console\Application;
use Log;
use Core\Model\Connector;


class Certificate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string 
     */
    protected $signature = 'project:certificate {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate certificate for domain';
 
    /**
     *
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->argument('domain');
        if(!is_domain_name($domain))
        {
            Logger::fatal($domain.' is not a valid domain name');
            return;
        }
        if(file_exists(base_path('certificates/certificate.cnf')))
        {
            $content = File::get(base_path('certificates/certificate.cnf'));
            if(preg_match("/^subjectAltName=DNS:(.*)$/m", $content, $matches))
            {
                if(trim($matches[1]) == $domain)
                {
                    Logger::info('certificate already generated - nothing to do');
                    return;
                }
            }
        }
        $global_config = [
            "linux"=> 
                ["apache_path"=> "/etc/apache2","ssl_config"=> ["/etc/ssl/openssl.cnf", "/etc/pki/tls/openssl.cnf"]],
            "mac"=>
            ["apache_path"=> "/usr/local/etc/apache2/2.4/","ssl_config"=> "/System/Library/OpenSSL/openssl.cnf"]];

        if(PHP_OS == 'Linux')
        {
            $config = $global_config["linux"];
        }else
        {
            $config = $global_config["mac"];
        }
        if(is_array($config['ssl_config']))
        {
            foreach($config['ssl_config'] as $path)
            {
                if(file_exists($path))
                {
                    $config['ssl_config'] = $path;
                    break;   
                }
            }
        }
        //TODO:test if current certificate are already good domain


        $default = base_path('certificates');
        $path = $this->anticipate('Certificate directory ['.$default.']', [$default,$config["apache_path"]]+array_map(function($item)
        {
            return $item['apache_path'];
        },$global_config));
        if(!isset($path))
        {
            $path = $default;
        }
        if(!file_exists($path))
        {
            Logger::fatal('Path '.$path.' doesn\'t exist');
            return;
        }
        $result = $this->cmd("cd $path && ssh-keygen", ['-f ' . 'certificate' . '.key'], true, [
            'Overwrite (y/n)?'  => 'y'
        ]);

        if(!$result["success"])
        {
            throw new \Exception("Error while creating the key");
        }
        $result = $this->cmd("cd $path && openssl req", ['-new -key ' . 'certificate' .  '.key', '-out ' . 'certificate' .  '.csr'], true, [
            'Common Name (e.g. server FQDN or YOUR name) []:' => $domain,
            'State or Province Name (full name) [Some-State]:' => '',
            'Locality Name (eg, city) []:' => '',
            'Country Name (2 letter code) [AU]:' => '',
            'Organization Name (eg, company) [Internet Widgits Pty Ltd]:' => '',
            'Organizational Unit Name (eg, section) []:'    => '',
            'Email Address []:' => '',
            'A challenge password []:'  => '',
            'An optional company name []:'  => ''
        ]);

        if(!$result["success"])
        {
            throw new \Exception("Error while creating the key");
        }

        $result = $this->cmd("cd $path && openssl x509", ['-req -days 3650', '-in '. 'certificate' .  '.csr', '-signkey ' . 'certificate' .  '.key', '-out ' . 'certificate' .  '.crt']);
        $sslconfig = $config['ssl_config'];
        $result = $this->cmd('cd ' . $path . ' && cat '.$config["ssl_config"].' > ' . $path . '/' . 'certificate' .  '.cnf && printf \'[SAN]\nsubjectAltName=DNS:' . $domain . '\n\' >> ' . $path . '/' . 'certificate' .  '.cnf');

        $result = $this->cmd("cd $path && openssl req", ['-x509 -nodes -new -days 3650', '-subj /CN=' . $domain . ' -reqexts SAN -extensions SAN -config ' . $path . '/'. 'certificate' .  '.cnf -sha256', '-key ' . 'certificate' . '.key', '-out ' . 'certificate' . '.crt']);
    }

    protected function cmd($command, $params = NULL, $execute = True, $stdin_autoinput = [])
    {
        if(isset($params))
        {
            $command.= " ".implode(" ", $params);
        }
        $this->info("execute: ".$command);
        // $command.=" 2>&1";
        $output = [];

        $returnValue = NULL;
        if($execute)
        {
            $descriptorspec = array(
               0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
               1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
               2 => array("pipe", "w")    // stderr is a pipe that the child will write to
            );
            $r = '';
            $process = proc_open($command, $descriptorspec, $pipes);

            stream_set_blocking($pipes[1], 0);
            stream_set_blocking($pipes[2], 0);
            stream_set_blocking(STDIN, 0);
            if (is_resource($process)) {

                $status = proc_get_status($process);
                if($status === FALSE) {
                    throw new Exception (sprintf(
                        'Failed to obtain status information '
                    ));
                }
                $pid = $status['pid'];
                $data = null;
                // now, poll for childs termination
                while(true) {
                    // detect if the child has terminated - the php way
                    $status = proc_get_status($process);
                    // check retval
                    if($status === FALSE) {
                        throw new Exception ("Failed to obtain status information for $pid");
                    }
                    if($status['running'] === FALSE) {
                        // $exitcode = $status['exitcode'];
                        // $pid = -1;
                        $returnValue = 0;
                        proc_close($process);
                        // echo "child exited with code: $exitcode\n";
                        return ["output"=>$output, "returnValue"=>$returnValue, "success"=>$returnValue==0];
                        // break;
                    }

                    // read from childs stdout and stderr
                    // avoid *forever* blocking through using a time out (50000usec)
                    foreach(array(1, 2) as $desc) {
                        // check stdout for data
                        $read = array($pipes[$desc]);
                        $write = NULL;
                        $except = NULL;
                        $tv = 0;
                        $utv = 50000;

                        $n = stream_select($read, $write, $except, $tv, $utv);
                        if($n > 0) {
                            do {
                                $data = fread($pipes[$desc], 8092);
                                fwrite(STDOUT, $data);

                                $output[] = $data;
                            } while (strlen($data) > 0);
                        }
                    }

                    if (count($stdin_autoinput) > 0)
                    {
                        foreach ($output as $o)
                        {
                            foreach ($stdin_autoinput as $key => $value)
                            {
                                if (!empty($o) && mb_strpos($o, $key) !== false)
                                {
                                    fwrite(STDOUT, "$value\n");
                                    fwrite($pipes[0], "$value\n");

                                    unset( $stdin_autoinput[$key] );
                                }
                            }
                        }
                    }

                    $read = array(STDIN);
                    $n = stream_select($read, $write, $except, $tv, $utv);
                    if($n > 0) {
                        $input = fread(STDIN, 8092);
                        // inpput to program
                        fwrite($pipes[0], $input);
                    }
                }
                $returnValue = proc_close($process);
            }
        }
        return ["output"=>$output, "returnValue"=>$returnValue, "success"=>$returnValue==0];
        
    }
}
