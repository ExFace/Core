<?php
namespace exface\Core\Tests\PHPUnit\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verifies error-handler registration in subprocesses that can terminate on real PHP fatals.
 */
class DebuggerFatalErrorLoggingTest extends TestCase
{
    private const DECLARATION_FATAL = 'interface FatalLoggingContract { public function invoke(object $task): void; }
        class IncompatibleFatalLoggingTool implements FatalLoggingContract {
            public function invoke(\stdClass $task): void {}
        }';

    /**
     * Declaration failures must reach the application logger without enabling pretty output.
     *
     * @return void
     */
    public function testDeclarationFatalIsLoggedWithoutPrettification(): void
    {
        $process = $this->runDebugger(false, self::DECLARATION_FATAL);
        $records = $this->getLogRecords($process);

        $this->assertFalse($process->isSuccessful());
        $this->assertCount(1, $records, $process->getErrorOutput());
        $this->assertSame('critical', $records[0]['level']);
        $this->assertSame(E_COMPILE_ERROR, $records[0]['code']);
        $this->assertStringContainsString('must be compatible', $records[0]['message']);
        $this->assertNotEmpty($records[0]['file']);
        $this->assertGreaterThan(0, $records[0]['line']);
        $this->assertSame('CORE_LOG:' . json_encode($records[0]) . PHP_EOL, $process->getOutput());
    }

    /**
     * The normal logger must report fatal errors without any Debugger registration.
     *
     * @return void
     */
    public function testDeclarationFatalIsLoggedWithoutDebugger(): void
    {
        $process = $this->runDebugger(null, self::DECLARATION_FATAL);
        $records = $this->getLogRecords($process);

        $this->assertFalse($process->isSuccessful());
        $this->assertCount(1, $records, $process->getErrorOutput());
        $this->assertSame(E_COMPILE_ERROR, $records[0]['code']);
        $this->assertStringContainsString('must be compatible', $records[0]['message']);
    }

    /**
     * A fatal must be logged once even though every logger registers its own shutdown callback.
     *
     * @return void
     */
    public function testAdditionalLoggerDoesNotDuplicateFatal(): void
    {
        $code = <<<'PHP'
$secondLogger = new \exface\Core\CommonLogic\Log\Logger();
$secondLogger->appendHandler(new class implements \exface\Core\Interfaces\Log\LogHandlerInterface {
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = [], \exface\Core\Interfaces\iCanGenerateDebugWidgets $sender = null): void
    {
        echo 'SECOND_LOG:' . $message . PHP_EOL;
    }
});
PHP;
        $process = $this->runDebugger(false, $code . self::DECLARATION_FATAL);

        $this->assertFalse($process->isSuccessful());
        $this->assertCount(1, $this->getLogRecords($process), $process->getErrorOutput());
        $this->assertStringNotContainsString('SECOND_LOG:', $process->getOutput());
    }

    /**
     * A logger without handlers must not consume the fatal that a later logger can still record.
     *
     * @return void
     */
    public function testHandlerlessLoggerDoesNotSwallowFatal(): void
    {
        $process = $this->runDebugger(false, self::DECLARATION_FATAL, 'new \exface\Core\CommonLogic\Log\Logger();');

        $this->assertFalse($process->isSuccessful());
        $this->assertCount(1, $this->getLogRecords($process), $process->getErrorOutput());
    }

    /**
     * Pretty mode must retain its existing handler without introducing duplicate fatal records.
     *
     * @return void
     */
    public function testDeclarationFatalIsLoggedOnceWithPrettification(): void
    {
        $process = $this->runDebugger(true, self::DECLARATION_FATAL);
        $records = $this->getLogRecords($process);

        $this->assertFalse($process->isSuccessful());
        $this->assertCount(1, $records, $process->getErrorOutput());
        $this->assertStringContainsString('must be compatible', $records[0]['message']);
    }

    /**
     * Normal shutdown must not create an error record merely because a callback is registered.
     *
     * @return void
     */
    public function testSuccessfulShutdownDoesNotLogErrors(): void
    {
        $process = $this->runDebugger(false, '');

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('', $process->getOutput());
    }

    /**
     * A reported warning must not be mistaken for a fatal error during shutdown.
     *
     * @return void
     */
    public function testWarningIsNotLoggedAsFatal(): void
    {
        $process = $this->runDebugger(false, "trigger_error('Non-fatal warning', E_USER_WARNING);");

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('', $process->getOutput());
    }

    /**
     * Fatal-only registration must leave existing error and exception handling untouched.
     *
     * @return void
     */
    public function testNonPrettyModePreservesExistingHandlers(): void
    {
        $setup = <<<'PHP'
$errorHandler = static function () { return false; };
$exceptionHandler = static function () {};
set_error_handler($errorHandler);
set_exception_handler($exceptionHandler);
PHP;
        $code = <<<'PHP'
$registeredErrorHandler = set_error_handler($errorHandler);
restore_error_handler();
$registeredExceptionHandler = set_exception_handler($exceptionHandler);
restore_exception_handler();
echo json_encode([
    'error_handler_unchanged' => $registeredErrorHandler === $errorHandler,
    'exception_handler_unchanged' => $registeredExceptionHandler === $exceptionHandler
]);
PHP;
        $process = $this->runDebugger(false, $code, $setup);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame([
            'error_handler_unchanged' => true,
            'exception_handler_unchanged' => true
        ], json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
    * Exercises the real logger with an optional Debugger, without touching installation logs/config.
     *
    * @param bool|null $pretty Null skips Debugger initialization.
     * @param string $code
     * @param string $setup
     * @return Process
     */
    private function runDebugger(?bool $pretty, string $code, string $setup = ''): Process
    {
        $script = 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';' . $setup;
        $script .= <<<'PHP'
$logger = new \exface\Core\CommonLogic\Log\Logger();
$logger->appendHandler(new class implements \exface\Core\Interfaces\Log\LogHandlerInterface {
    /**
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Log\LogHandlerInterface::handle()
     */
    public function handle($level, $message, array $context = [], \exface\Core\Interfaces\iCanGenerateDebugWidgets $sender = null): void
    {
        $exception = $context['exception'] ?? null;
        echo 'CORE_LOG:' . json_encode([
            'level' => $level,
            'message' => $message,
            'code' => $context['code'] ?? ($exception instanceof \Symfony\Component\ErrorHandler\Error\FatalError ? $exception->getError()['type'] : null),
            'file' => $context['file'] ?? ($exception !== null ? $exception->getFile() : null),
            'line' => $context['line'] ?? ($exception !== null ? $exception->getLine() : null)
        ]) . PHP_EOL;
    }
});
PHP;
        if ($pretty !== null) {
            $script .= <<<'PHP'
$debuggerClass = new \ReflectionClass(\exface\Core\CommonLogic\Debugger::class);
$debugger = $debuggerClass->newInstanceWithoutConstructor();
$debuggerClass->getProperty('logger')->setValue($debugger, $logger);
$debuggerClass->getProperty('error_reporting')->setValue($debugger, E_ALL);
$configure = $debuggerClass->getMethod('setPrettifyErrors');
PHP;
        $script .= '$configure->invoke($debugger, ' . ($pretty ? 'true' : 'false') . ');';
    }
    $script .= $code;

        $process = new Process([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', '-r', $script]);
        $process->setTimeout(10);
        $process->run();

        return $process;
    }

    /**
     * Separates captured application records from Symfony's optional rendered exception output.
     *
     * @param Process $process
     * @return array
     */
    private function getLogRecords(Process $process): array
    {
        preg_match_all('/^CORE_LOG:(.*)$/m', $process->getOutput(), $matches);

        return array_map(
            static fn(string $record): array => json_decode($record, true, 512, JSON_THROW_ON_ERROR),
            $matches[1]
        );
    }
}