<?php
namespace exface\Core\Facades;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Facades\AbstractFacade\AbstractFacade;
use GuzzleHttp\Psr7\ServerRequest;
use Symfony\Component\Process\Process;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Facades\AbstractHttpFacade\IteratorStream;


class WebConsoleFacade extends AbstractFacade
{
    private $directory = '';
    
    public function handle(ServerRequest $request)
    {
        //$request = ServerRequest::fromGlobals();
        
        // tests/psr7_console/server.php?cmd=cd
        $cmd = $request->getParsedBody()['cmd'];
        $cmd = trim($cmd);
        $command = getCommand($cmd);
        if (empty($this->getDirectory())){
            $this->setDirectory(getcwd());
        }
        
        if ($command == 'cd' && StringDataType::substringAfter($cmd, ' ') != false){
            $cmd .= ' & cd';
        }
        
        
        if ($command === 'test') {
            $generator = function ($bytes) {
                for ($i = 0; $i < $bytes; $i++) {
                    sleep(1);
                    yield '.'.$i.'.';
                }
            };
            
            $stream = new IteratorStream($generator(10));
        } else {
            $process = Process::fromShellCommandline($cmd, $this->getDirectory(), ['APPDATA' => 'C:\Users\RML\AppData\Roaming'], null, 10000);
            $process->start();
            
            if ($command == 'cd'){
                $generator = function ($process) {
                    foreach ($process as $output) {
                        $this->setDirectory($output);
                        yield $output;
                    }
                };
            } else {
                $generator = function ($process) {
                    foreach ($process as $output) {
                        yield $output;
                    }
                };
            }
            
            $stream = new IteratorStream($generator($process));
        }
        
        set_time_limit(0);
        ob_implicit_flush(true);
        ob_end_flush();
        
        $response = new Response(200, [], $stream);
        
        send($response, 1);
    }
    
    protected function setDirectory($directory) : WebConsoleFacade{
        $this->directory = $directory;
        return $this;
    }
    
    protected function getDirectory() : string{
        return $this->directory;
    }
    
    protected function getCommand($cmd){
        if (StringDataType::substringBefore($cmd, ' ') == false){
            return $cmd;
        } else {
            return StringDataType::substringBefore($cmd, ' ');
        }
    }
}

?>