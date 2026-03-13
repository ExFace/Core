<?php
namespace exface\Core\Facades\ConsoleFacade;

use exface\Core\DataTypes\FilePathDataType;
use exface\Core\DataTypes\ServerSoftwareDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\RuntimeException;
use Symfony\Component\Process\Process;

class CliCommandRunner
{
    /**
     * 
     * @param string $cmd
     * @param array $envVars
     * @param float $timeout
     * @param string|null $cwd
     * @param bool $silent
     * @return \Generator
     * @throws \Throwable
     */
    public static function runCliCommand(
        string  $cmd,
        array   $envVars = [],
        float   $timeout = 60,
        ?string $cwd = null,
        bool    $silent = true
    ) : \Generator {
        if (static::canUseSymfonyProcess()) {
            $process = Process::fromShellCommandline($cmd, $cwd, $envVars, null, $timeout);
            $process->start();
            
            $generator = function (Process $process, bool $silent) : \Generator {
                // Keep copies because iterating over $process consumes incremental buffers
                $stdout = '';
                $stderr = '';
                
                foreach ($process as $type => $buffer) {
                    if ($buffer !== '') {
                        if ($type === Process::OUT) {
                            $stdout .= $buffer;
                        } else {
                            $stderr .= $buffer;
                        }
                        yield $buffer;
                    }
                }
                $process->wait(); // ensure completion
                
                // opt-in failure signaling
                if (! $process->isSuccessful()) {
                    $exit = $process->getExitCode();    
                    yield 'Command `' . $process->getCommandLine() . '` failed with exit code ' . $exit . '.';
                    // If caller wants hard failure, throw AFTER emitting the error marker
                    if (! $silent) {
                        $errorMessage = '';
                        if (preg_match('/LogID:\s*([A-Z0-9]+)/', $stdout, $matches)) {
                            $logId = $matches[1];
                            $errorMessage = "LogID: $logId\n";
                        } else {
                            $errorMessage =  "no error output.\n";
                        }
                        throw new RuntimeException('CLI command "' . $process->getCommandLine() . '" failed: ' . ($stderr !== '' ? $stderr : $errorMessage));
                    }
                }                
            };
            return $generator($process, $silent);
        } else {
            $generator = function() use ($cmd, $envVars, $silent) {
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
                // If command failed, emit error line AFTER output
                if ($code !== 0) {
                    yield 'Command `' . $cmd . '` failed with exit code ' . $code . '.';
                    // If caller wants hard failure, throw AFTER emitting the error marker
                    if (! $silent) {
                        throw new RuntimeException('CLI command "' . $cmd . '" failed: ' . ($stderr !== '' ? $stderr : 'no error output'));
                    }
                }
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
                return false;
                /* TODO solve remaining probelms with Symfony Process with IIS: it seems,
                // it cannot read errors now. So the below check should be a command, that
                // produces an error. Successful commands seem fine now (01.2025)
                // Check, if symfony process will return non-empty output: 
                // `whoami` should always return something
                $process = new Process(['whoami']);
                $process->run();                
                if (! $process->isSuccessful() || $process->getOutput() === '') {
                    return false;
                }*/
            }
        }
        return true;
    }

    public static function setPermissionsForPath(string $path, string $user) : array
    {        
        if (ServerSoftwareDataType::isOsWindows()) {
            // Grant Modify (M) to user. (Closer to old CACLS ":c" change permission.)
            // /T optional (propagate through subfolders). /C continue on errors.
            // $cmd = "CACLS {$path} /e /p {$user}:c";
            // CACLS is deprecated (use icacls on Windows instead) - but for now it works and is battle-tested
            $cmd = 'icacls ' . escapeshellarg($path) . ' /grant ' . escapeshellarg($user . ':(M)') . ' /C';
        } else {
            // Linux / Unix
            // Prefer ACLs: give rwX (X applies execute only to dirs / already-executable files)
            // -m modifies ACL; -R would be recursive (only if you need it!)
            $setfacl = trim((string)@shell_exec('command -v setfacl 2>/dev/null'));
            if ($setfacl !== '') {
                $cmd = 'setfacl -m ' . escapeshellarg('u:' . $user . ':rwX') . ' ' . escapeshellarg($path);
            } else {
                // Fallback: basic chmod. This does NOT target a specific user—only adjusts mode bits.
                // Choose something sensible for your use case; here: add user rwX.
                $cmd = 'chmod u+rwX ' . escapeshellarg($path);
            }
        }

        $output = [];
        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $filename = FilePathDataType::findFileName($path, true);
            throw new RuntimeException(
                "Permission for the user '{$user}' and folder/file '{$filename}' could not be changed! "
                . "Command: {$cmd} Output: " . implode("\n", $output)
            );
        }
        
        return $output;
    }
}