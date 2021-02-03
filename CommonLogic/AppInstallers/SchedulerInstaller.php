<?php
namespace exface\Core\CommonLogic\AppInstallers;


use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Exceptions\CliExecException;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Registeres a CLI facade command as scheduled task in the current operating system.
 * 
 * @author Andrej Kabachnik
 *        
 */
class SchedulerInstaller extends AbstractAppInstaller
{
    private $tasks;
    
    public function backup(string $absolute_path) : \Iterator
    {
        return new \EmptyIterator();
    }
    
    public function uninstall() : \Iterator
    {
        return new \EmptyIterator();
    }

    public function install(string $source_absolute_path): \Iterator
    {
        $indent = $this->getOutputIndentation();
        foreach ($this->tasks as $name => $args) {
            try {
                $this->registerScheduledTask($name, $args['command'], $args['intervalInMinutes'], $args['overwrite']);
                yield $indent . 'Scheduled task "' . $name . '" registered in ' . $this->getOsFamily() . '.' . PHP_EOL;
            } catch (\Throwable $e) {
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::NOTICE);
                yield $indent . 'Scheduled task "' . $name . '" NOT registered in ' . $this->getOsFamily() . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine() . PHP_EOL;
            }
        }
        return;
    }
    
    /**
     * 
     * @param string $name
     * @param string $command
     * @return SchedulerInstaller
     */
    public function addTask(string $name, string $command, int $intervalInMinutes = 60, bool $overwrite = false) : SchedulerInstaller
    {
        $this->tasks[$name] = [
            'command' => $command,
            'intervalInMinutes' => $intervalInMinutes,
            'overwrite' => $overwrite
        ];
        return $this;
    }
    
    /**
     * 
     * @param string $name
     * @param string $command
     * @throws InstallerRuntimeError
     * @return SchedulerInstaller
     */
    protected function registerScheduledTask(string $name, string $command, int $intervalInMinutes = 60, bool $overwrite = false) : SchedulerInstaller
    {
        $output = [];
        $returnVar = null;
        $name = json_encode($name);
        $vendorPath = $this->getWorkbench()->filemanager()->getPathToVendorFolder();
        
        switch ($this->getOsFamily()) {
            case 'Windows': 
                $cmd = "schtasks /create /sc minute /mo {$intervalInMinutes} /tn {$name} /tr \"cmd /c {$vendorPath}\bin\action.bat {$command}\"";
                if ($overwrite === true) {
                    $cmd .= ' /f';
                }
                $cmd .= " 2>&1";
                exec($cmd, $output, $returnVar);
                if ($returnVar === 1) {
                    throw new CliExecException($cmd, $output);
                }
                break;
            default:
                // TODO
                throw new RuntimeException('SchedulerInstaller does not (yet) support OS ' . $this->getOsFamily());
        }
        
        return $this;
    }
    
    /**
     * The operating system family PHP was built for. One of 'Windows', 'BSD', 'Darwin', 'Solaris', 'Linux' or 'Unknown'
     * 
     * @return string
     */
    protected function getOsFamily() : string
    {
        return PHP_OS_FAMILY;
    }
}