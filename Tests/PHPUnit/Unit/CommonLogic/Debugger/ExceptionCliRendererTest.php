<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\Debugger;

use exface\Core\CommonLogic\Debugger\ExceptionCliRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Covers vendor-relative exception locations without changing messages or external file locations.
 */
class ExceptionCliRendererTest extends TestCase
{
    /**
     * Every exception in a chain uses a shortened location, regardless of which traces are included.
     *
     * @return void
     */
    public function testChainedExceptionLocationsAreVendorRelative() : void
    {
        $cause = new \RuntimeException('Cause');
        $exception = new \RuntimeException('Failure involving ' . __FILE__, 0, $cause);
        $renderer = new ExceptionCliRenderer($exception);
        $parts = explode(DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR, __FILE__, 2);
        $expectedFile = $parts[1] ?? $parts[0];

        foreach ([[false, false], [true, false], [true, true]] as [$includeTrace, $onlyBottomTrace]) {
            $output = $renderer->renderPlainText($includeTrace, $onlyBottomTrace);
            self::assertStringContainsString("\n in " . $expectedFile . ':' . $exception->getLine(), $output);
            self::assertStringContainsString("\n in " . $expectedFile . ':' . $cause->getLine(), $output);
            self::assertStringContainsString('Failure involving ' . __FILE__, $output);
        }
    }

    /**
     * Exceptions originating outside this installation's vendor directory keep their complete location.
     *
     * @return void
     */
    public function testExternalExceptionLocationIsPreserved() : void
    {
        $path = tempnam(sys_get_temp_dir(), 'exception-location-');
        try {
            file_put_contents($path, '<?php return new \RuntimeException("External failure");');
            $exception = require $path;
            $renderer = new ExceptionCliRenderer($exception);

            self::assertStringContainsString("\n in " . $exception->getFile() . ':1', $renderer->renderPlainText(false));
        } finally {
            unlink($path);
        }
    }
}
