<?php
namespace exface\Core\CommonLogic\AppInstallers;


use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Exceptions\CliExecException;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Exceptions\RuntimeException;

/**
 * Registeres a CLI facade command as scheduled task in the current operating system.
 * 
 * Can be disabled in the configuration of the app by adding the option
 * `"INSTALLER.SCHEDULERINSTALLER.DISABLED": true`.
 * 
 * @author Andrej Kabachnik
 *        
 */
class SchedulerInstaller extends AbstractAppInstaller
{
    const CONFIG_OPTION_DISABLED = 'INSTALLER.SCHEDULERINSTALLER.DISABLED';
    
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
        if ($this->getApp()->getConfig()->hasOption(self::CONFIG_OPTION_DISABLED) && $this->getApp()->getConfig()->getOption(self::CONFIG_OPTION_DISABLED)) {
            yield 'Scheduled tasks installer disabled';
            return;
        } 
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
                $cmd = "schtasks /create /sc minute /mo {$intervalInMinutes} /tn {$name} /ru system /np /tr \"cmd /c {$vendorPath}\bin\action.bat {$command}\"";
                if ($overwrite === true) {
                    $cmd .= ' /f';
                }
                $cmd .= " 2>&1";
                exec($cmd, $output, $returnVar);
                if ($returnVar === 1) {
                    throw new CliExecException($cmd, $output);
                }
                break;
                /* TODO has yet to be verified if it is working correctly on Linux based systems
            case 'Linux':
                $cronExpr = $this->getCronExpression($intervalInMinutes);
                $cmd = "crontab -e $cronExpr {$vendorPath}\bin\action {$command} >/dev/null 2>&1 #{$name}";
                if ($overwrite === true) {
                    $delCmd = "crontab -l | sed '/#{$name}/d' | crontab -";
                    exec($delCmd, $output, $returnVar);
                    if ($returnVar === 1) {
                        throw new CliExecException($delCmd, $output);
                    }
                }
                exec($cmd, $output, $returnVar);
                if ($returnVar === 1) {
                    throw new CliExecException($cmd, $output);
                }
                break;*/
            default:
                // TODO
                throw new RuntimeException('SchedulerInstaller does not (yet) support OS ' . $this->getOsFamily());
        }
        
        return $this;
    }
    
    /*
    protected function getCronExpression(int $intervallInMinutes) : string
    {
        $min = '* ';
        $hour = '* ';
        $day = '* ';
        $month = '* ';
        if ($monthInterval = floor($intervallInMinutes / (60*24*12)) >= 1) {
            $month = "*\/{$monthInterval} ";
            $intervallInMinutes = $intervallInMinutes - ($monthInterval * 60*24*12);
        }
        if ($dayInterval = floor($intervallInMinutes / (60*24)) >= 1) {
            $month = "*\/{$dayInterval} ";
            $intervallInMinutes = $intervallInMinutes - ($dayInterval * 60*24);
        }
        if ($hourInterval = floor($intervallInMinutes / (60)) >= 1) {
            $month = "*\/{$hourInterval} ";
            $intervallInMinutes = $intervallInMinutes - ($hourInterval * 60);
        }
        $min = "*\/{$intervallInMinutes} ";
        return $min . $hour . $day . $month . '*';
    }*/
    
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