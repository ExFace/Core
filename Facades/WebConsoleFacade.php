<?php
namespace exface\Core\Facades;

use exface\Core\DataTypes\StringDataType;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use exface\Core\Facades\AbstractHttpFacade\IteratorStream;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\UiPageFactory;
use Psr\Http\Message\RequestInterface;
use exface\Core\Widgets\Console;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Factories\FacadeFactory;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Facades\AbstractHttpFacade\Middleware\AuthenticationMiddleware;
use exface\Core\Facades\AbstractHttpFacade\HttpRequestHandler;
use exface\Core\Facades\AbstractHttpFacade\OKHandler;

/***
 * This is the Facade for Console Widgets
 * It streams the cmd outputs to the Console while the commands are executed
 * 
 * @author Ralf Mulansky
 *
 */
class WebConsoleFacade extends AbstractHttpFacade
{
    
    /***
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\RequestHandlerInterface::handle()
     */
    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            if ($this->getWorkbench()->isStarted() === false) {
                $this->getWorkbench()->start();
            }
            
            $handler = new HttpRequestHandler(new OKHandler());
            $handler->add(new AuthenticationMiddleware($this));
            $responseTpl = $handler->handle($request);
            if ($responseTpl->getStatusCode() >= 400) {
                return $responseTpl;
            }
            $response = $this->performCommand($request);
        } catch (\Throwable $e) {
            if ($e instanceof ExceptionInterface) {
                $statusCode = $e->getStatusCode();
            } else {
                $statusCode = 500;
            }
            $response = new Response($statusCode, $responseTpl->getHeaders(), $this->getWorkbench()->getDebugger()->printException($e));
        }  
        
        // Don't stop the workbench here!!! The response might include a generator, that
        // will still need the workbench. The workbench will be stopped automatically
        // before it' destroyed!
        
        return $response;
    }
    
    /***
     * Perform commands from request
     * 
     * @param RequestInterface $request
     * @throws RuntimeException
     * @return ResponseInterface
     */
    protected function performCommand(RequestInterface $request) : ResponseInterface
    {
        // tests/psr7_console/server.php?cmd=cd
        $cmd = $request->getParsedBody()['cmd'];
        $cmd = trim($cmd);
        $command = $this->getCommand($cmd);
        $widget = $this->getWidgetFromRequest($request);
        
        // Current directory
        $cwd = $request->getParsedBody()['cwd'];
        if ($cwd) {
            if (Filemanager::pathIsAbsolute($cwd)) {
                throw new RuntimeException('Absolute Path syntax not allowed!');
            }
            /*if (is_dir($this->getRootDirectory() . DIRECTORY_SEPARATOR . $cwd) === false){
                throw new RuntimeException('Working Directory is not a folder!');
            }*/
        }
        chdir($this->getRootDirectory() . DIRECTORY_SEPARATOR . $cwd);
        
        // Check if command allowed
        $allowed = FALSE;
        foreach ($widget->getAllowedCommands() as $allowedCommand){
            $match = preg_match($allowedCommand, $cmd);
            if($match !=0){
                $allowed = TRUE;
            }
        }
        if ($allowed === FALSE){
            $headers = [
                'X-CWD' => StringDataType::substringAfter(getcwd(), $this->getRootDirectory() . DIRECTORY_SEPARATOR)
            ];
            $body = 'Command not allowed!';
            return new Response(200, $headers, $body);
        }
        $cmdNormalized = str_replace('/', DIRECTORY_SEPARATOR, $command);
        if ($cmdNormalized !== $command) {
            $cmd = str_replace($command, $cmdNormalized, $cmd);
        }
           
        // Process command
        switch (true) {
            case ($command == 'cd' && StringDataType::substringAfter($cmd, ' ') != false):
                $newDir = StringDataType::substringAfter($cmd, ' ');
                if (Filemanager::pathIsAbsolute($newDir)) {
                    throw new RuntimeException('Absolute Path syntax ' . $newDir .'  not allowed! Use relative paths!');
                }
                chdir($newDir);
                $stream = stream_for('');
                break;
            case $command === 'test': 
                $generator = function ($bytes) {
                    for ($i = 0; $i < $bytes; $i++) {
                        sleep(1);
                        yield '.'.$i.'.';
                    }
                };
                $stream = new IteratorStream($generator(10));
                break;
            case $this->isCliAction($command, $cwd) === true:
                /* @var $console \exface\Core\Facades\ConsoleFacade */
                $console = FacadeFactory::createFromString(ConsoleFacade::class, $this->getWorkbench());
                $stream = new IteratorStream($console->getOutputGenerator($cmd));
                break;
            default:
                $envVars = [];
                if (! empty($inheritVars = $widget->getEnvironmentVarsInherit())) {
                    foreach (getenv() as $var => $val) {
                        if (in_array($var, $inheritVars)) {
                            $envVars[$var] = $val;
                        }
                    }
                }
                $envVars = array_merge($envVars, $widget->getEnvironmentVars());
                $process = Process::fromShellCommandline($cmd, null, $envVars, null, $widget->getCommandTimeout());
                $process->start();
                $generator = function ($process) {
                    foreach ($process as $output) {
                        yield $output;
                    }
                };
                
                $stream = new IteratorStream($generator($process));
        }
        
        try {
            $this->setupStreaming();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
        
        $headers = [
            'X-CWD' => StringDataType::substringAfter(getcwd(), $this->getRootDirectory() . DIRECTORY_SEPARATOR),
            'Content-Type' => 'text/plain-stream'
        ];
        
        $response = new Response(200, $headers, $stream);
        
        return $response;
    }
    
    /**
     * Configurates the Server to be able to stream the output
     * 
     * @return WebConsoleFacade
     */
    protected function setupStreaming() : WebConsoleFacade
    {
        ob_end_clean();
        
        if (ini_get("zlib.output_compression") == 1) {
            ini_set('zlib.output_compression', 'Off');
            
            // throw new FacadeLogicError('Cannot stream output from WebConsole facade, because "zlib.output_compression" is turned on in PHP ini!', '75IRH3L');
        }
        
        set_time_limit(0);
        ob_implicit_flush(true);
        ob_end_flush();
        
        return $this;
    }    
      
    /**
     * Returns the part of $cmd preceding the first ' ' or the complete command if it is a complex command
     * 
     * @param string $cmd
     * @return string
     */
    protected function getCommand(string $cmd) : string
    {
        if ($this->isComplexCommand($cmd)) {
            return $cmd;
        }
        if (StringDataType::substringBefore($cmd, ' ') == false){
            return $cmd;
        } else {
            return StringDataType::substringBefore($cmd, ' ');
        }
    }
    
    /**
     * Checks if a command is complex, means it includes '&&' or '|'
     * 
     * @param string $cmd
     * @return bool
     */
    protected function isComplexCommand(string $cmd) : bool
    {
        if (strpos($cmd, ' && ') !== false || strpos($cmd, ' | ') !== false) {
            return true;
        }
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::getUrlRouteDefault()
     */
    public function getUrlRouteDefault() : string
    {
        return 'api/webconsole';
    }
    
    /**
     * 
     * @param RequestInterface $request
     * @return Console
     */
    protected function getWidgetFromRequest(RequestInterface $request) : Console
    {
        $pageSelector = $request->getParsedBody()['page'];
        $page = UiPageFactory::createFromModel($this->getWorkbench(), $pageSelector);
        $widgetId = $request->getParsedBody()['widget'];
        return $page->getWidget($widgetId);
    }
    
    /***
     * 
     * @return string
     */
    protected function getRootDirectory() : string
    {
        return $this->getWorkbench()->filemanager()->getPathToBaseFolder();
    }
    
    protected function isCliAction(string $command, string $workingDir) : bool
    {
        return (strcasecmp($command, 'action') === 0 && strcasecmp(FilePathDataType::normalize($workingDir, '/'), 'vendor/bin') === 0) 
        || (stripos($command, 'vendor/bin/action') !== false || stripos($command, 'vendor\bin\action') !== false);
    }
}