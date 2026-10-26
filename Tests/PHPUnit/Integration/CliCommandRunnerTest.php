<?php
namespace exface\Core\Tests\PHPUnit\Integration;

use exface\Core\Exceptions\CliRuntimeException;
use exface\Core\Facades\ConsoleFacade\CliCommandRunner;
use PHPUnit\Framework\TestCase;

/**
 * Verifies CLI diagnostics using real local PHP subprocesses.
 */
class CliCommandRunnerTest extends TestCase
{
    /**
     * Stderr must be visible without an ExFace LogID.
     *
     * @return void
     */
    public function testStderrIsIncludedInFailureMessage(): void
    {
        $exception = $this->runFailingCommand("fwrite(STDERR, 'Download failed'); exit(100);");

        $this->assertStringContainsString('Download failed', $exception->getMessage());
        $this->assertStringContainsString('exit code 100', $exception->getMessage());
        $this->assertStringNotContainsString('no error output', $exception->getMessage());
        $this->assertSame(100, $exception->getExitCode());
    }

    /**
     * Stdout diagnostics must also appear in the exception.
     *
     * @return void
     */
    public function testStdoutIsIncludedInFailureMessage(): void
    {
        $exception = $this->runFailingCommand("fwrite(STDOUT, 'stdout diagnostic'); exit(100);");

        $this->assertStringContainsString('stdout diagnostic', $exception->getMessage());
    }

    /**
     * Neither stream may be discarded when both contain output.
     *
     * @return void
     */
    public function testBothStreamsAreRetained(): void
    {
        $exception = $this->runFailingCommand("fwrite(STDOUT, 'stdout diagnostic'); fwrite(STDERR, 'stderr diagnostic'); exit(100);");

        foreach (['stdout diagnostic', 'stderr diagnostic'] as $diagnostic) {
            $this->assertStringContainsString($diagnostic, $exception->getMessage());
            $this->assertStringContainsString($diagnostic, implode('', $exception->getCliOutput()));
        }
    }

    /**
     * A LogID must not replace the remaining diagnostics.
     *
     * @return void
     */
    public function testLogIdDoesNotHideDiagnostics(): void
    {
        $exception = $this->runFailingCommand("fwrite(STDOUT, 'LogID: ABC123'); fwrite(STDERR, 'Download failed'); exit(100);");

        $this->assertStringContainsString('LogID: ABC123', $exception->getMessage());
        $this->assertStringContainsString('Download failed', $exception->getMessage());
    }

    /**
     * The empty-output fallback applies only to a truly empty command.
     *
     * @return void
     */
    public function testEmptyOutputIncludesFailureContext(): void
    {
        $exception = $this->runFailingCommand('exit(100);');

        $this->assertStringContainsString('exit code 100', $exception->getMessage());
        $this->assertStringContainsString('no error output', $exception->getMessage());
    }

    /**
     * The Windows IIS exec fallback must expose diagnostics too.
     *
     * @return void
     */
    public function testExecFallbackIncludesBothStreams(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('The IIS exec fallback is Windows-specific.');
        }

        $exception = $this->runFailingCommand("fwrite(STDOUT, 'stdout diagnostic'); fwrite(STDERR, 'stderr diagnostic'); exit(100);", true);

        $this->assertStringContainsString('stdout diagnostic', $exception->getMessage());
        $this->assertStringContainsString('stderr diagnostic', $exception->getMessage());
        $this->assertStringContainsString('exit code 100', $exception->getMessage());
    }

    /**
     * Silent failures still stream output and a failure marker without throwing.
     *
     * @return void
     */
    public function testSilentFailureStillStreamsOutput(): void
    {
        $command = $this->buildPhpCommand("fwrite(STDERR, 'Download failed'); exit(100);");
        $output = implode('', iterator_to_array(CliCommandRunner::runCliCommand($command, [], 10)));

        $this->assertStringContainsString('Download failed', $output);
        $this->assertStringContainsString('failed with exit code 100', $output);
    }

    /**
     * Ignored exit codes still stream diagnostics without signaling failure.
     *
     * @return void
     */
    public function testIgnoredExitCodeDoesNotThrow(): void
    {
        $command = $this->buildPhpCommand("fwrite(STDERR, 'Expected diagnostic'); exit(100);");
        $output = implode('', iterator_to_array(CliCommandRunner::runCliCommand($command, [], 10, null, false, [100])));

        $this->assertSame('Expected diagnostic', $output);
    }

    /**
     * Runs a failing PHP command and restores server metadata afterward.
     *
     * @param string $code
     * @param bool $useExec
     * @return CliRuntimeException
     */
    private function runFailingCommand(string $code, bool $useExec = false): CliRuntimeException
    {
        $hadServerSoftware = array_key_exists('SERVER_SOFTWARE', $_SERVER);
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? null;
        $_SERVER['SERVER_SOFTWARE'] = $useExec ? 'Microsoft-IIS' : '';

        try {
            foreach (CliCommandRunner::runCliCommand($this->buildPhpCommand($code), [], 10, null, false) as $buffer) {
            }
        } catch (CliRuntimeException $exception) {
            return $exception;
        } finally {
            if ($hadServerSoftware) {
                $_SERVER['SERVER_SOFTWARE'] = $serverSoftware;
            } else {
                unset($_SERVER['SERVER_SOFTWARE']);
            }
        }

        $this->fail('Expected the command to throw CliRuntimeException.');
    }

    /**
     * Builds a shell command using the current PHP interpreter.
     *
     * @param string $code
     * @return string
     */
    private function buildPhpCommand(string $code): string
    {
        return escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code);
    }
}