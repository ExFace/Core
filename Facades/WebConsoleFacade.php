<?php
namespace exface\Core\Facades;

use exface\Core\DataTypes\StringDataType;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use exface\Core\Facades\AbstractHttpFacade\IteratorStream;
use exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade;
use exface\Core\Factories\UiPageFactory;
use Psr\Http\Message\RequestInterface;
use exface\Core\Widgets\Console;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Factories\FacadeFactory;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\CommonLogic\Security\Authorization\UiPageAuthorizationPoint;

/**
 * This is the Facade for Console Widgets
 * It streams the cmd outputs to the Console while the commands are executed
 * 
 * @author Ralf Mulansky
 *
 */
class WebConsoleFacade extends AbstractHttpFacade
{
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractHttpFacade\AbstractHttpFacade::createResponse()
     */
    protected function createResponse(ServerRequestInterface $request) : ResponseInterface
    {
        try {
            $response = $this->performCommand($request);
        } catch (\Throwable $e) {
            $response = $this->createResponseFromError($e, $request);
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
        $cmd = $request->getParsedBody()['cmd'] ?? $request->getQueryParams()['cmd'];
        $cmd = trim($cmd);
        $command = $this->getCommand($cmd);
        $widget = $this->getWidgetFromRequest($request);
        
        // Make sure the the current user is allowed to interact with the console widget
        // This is important to ensure AJAX requests of a console are not intercepted and 
        // modified by other users!
        // Note, that merely access permissions to the web console facade itself would not
        // be enough as there could be multiple different consoles with different access
        // rights in the menu, etc.
        $this->getWorkbench()->getSecurity()->getAuthorizationPoint(UiPageAuthorizationPoint::class)->authorizeWidget($widget);
        
        // Current directory
        $cwd = $request->getParsedBody()['cwd'] ?? $request->getQueryParams()['cwd'];
        if ($cwd) {
            if (Filemanager::pathIsAbsolute($cwd)) {
                throw new RuntimeException('Absolute Path syntax not allowed!');
            }
            /*if (is_dir($this->getRootDirectory() . DIRECTORY_SEPARATOR . $cwd) === false){
                throw new RuntimeException('Working Directory is not a folder!');
            }*/
        }
        if (is_dir($this->getRootDirectory() . DIRECTORY_SEPARATOR . $cwd) === FALSE){
            $headers = [
                'X-CWD' => $cwd
            ];
            $body = 'Working directory is not a directory!';
            return new Response(200, $headers, $body);
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
                'X-CWD' => $cwd
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
                //chdir($newDir);
                $stream = Psr7\Utils::streamFor('');
                if ($newDir) {
                    $path = $cwd ? $cwd . DIRECTORY_SEPARATOR . $newDir : $newDir;
                    $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
                    $path = $this->normalizePath($path);
                    if ($path !== null) {
                        if (is_dir($this->getRootDirectory(). DIRECTORY_SEPARATOR . $path) || $path === '') {
                            $cwd = $path;
                        }
                    }
                }
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
                
                // TODO $process->start() seems not to produce any output with some versions of
                // Microsoft IIS. 
                // This returns an output though. So maybe we need an if() here. But how to find
                // out, when we need it?
                // dump(shell_exec('dir'));
                
                $stream = new IteratorStream($generator($process));
        }
        
        try {
            $this->setupStreaming();
        } catch (\Throwable $e) {
            $this->getWorkbench()->getLogger()->logException($e);
        }
        
        $headers = [
            'X-CWD' => $cwd,
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
    protected function getWidgetFromRequest(ServerRequestInterface $request) : Console
    {
        $pageSelector = $request->getParsedBody()['page'] ?? $request->getQueryParams()['page'];
        $page = UiPageFactory::createFromModel($this->getWorkbench(), $pageSelector);
        $widgetId = $request->getParsedBody()['widget'] ?? $request->getQueryParams()['widget'];
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
    
    protected function normalizePath(string $path) : ?string
    {
        $norml = $this->getWorkbench()->filemanager()->pathNormalize($path, DIRECTORY_SEPARATOR);
        if (StringDataType::startsWith($norml, '..')) {
            return null;
        }
        return $norml;       
    }
}