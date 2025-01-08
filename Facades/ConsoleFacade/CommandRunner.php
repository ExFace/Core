<?php
namespace exface\Core\Facades\ConsoleFacade;

use Symfony\Component\Process\Process;

class CommandRunner
{
    /**
     * 
     * @param string $cmd
     * @param array $envVars
     * @param float $timeout
     * @return \Generator
     */
    public static function runCliCommand(string $cmd, array $envVars = [], float $timeout = 60) : \Generator
    {
        if (static::canUseSymfonyProcess()) {
            $process = Process::fromShellCommandline($cmd, null, $envVars, null, $timeout);
            $process->start();
            $generator = function ($process) {
                foreach ($process as $output) {
                    yield $output;
                }
            };
            return $generator($process);
        } else {
            $generator = function() use ($cmd, $envVars) {
                // This workaround resulted from an issue with Microsoft IIS:
                // `$process->start()` seems not to produce any output.
                // See https://github.com/symfony/symfony/issues/24924
                $result = null;
                $code = 0;
                foreach ($envVars as $var => $val) {
                    putenv($var . '=' . $val);
                }
                exec($cmd . ' 2>&1', $result, $code);
                $resultStr = implode("\n", $result);
                yield $resultStr;
            };
            return $generator();
        }
    }
    
    /**
     * Returns TRUE if Symfony process should work on the current server setup
     * 
     * Currently known systems not compatible with Symfony process:
     * - Some IIS versions on Windows
     * 
     * @return bool
     */
    protected static function canUseSymfonyProcess() : bool
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $isIIS = (stripos($_SERVER["SERVER_SOFTWARE"], "microsoft-iis") !== false);
            if ($isIIS) {
                // Check, if symfony process will return non-empty output: 
                // `whoami` should always return something
                $process = new Process(['whoami']);
                $process->run();                
                if (! $process->isSuccessful() || $process->getOutput() === '') {
                    return false;
                }
            }
        }
        return true;
    }
}