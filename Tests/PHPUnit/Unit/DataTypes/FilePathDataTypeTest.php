<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Interfaces\Selectors\DataTypeSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Expected values reflect the former webmozart/path-util behavior, which must be identical on every OS.
 */
class FilePathDataTypeTest extends TestCase
{
    public static function normalizeProvider(): array
    {
        return [
            'windows with ..' => ['C:\\wamp\\www\\exface\\..\\x', '/', 'C:/wamp/www/x'],
            'unix with ..' => ['/a/b/../c', '/', '/a/c'],
            'dots and double slashes' => ['a/./b//c', '/', 'a/b/c'],
            'relative leading ..' => ['../a/../../b', '/', '../../b'],
            '.. above root' => ['/../a', '/', '/a'],
            'drive only' => ['C:', '/', 'C:/'],
            'backslash root' => ['\\a\\b', '/', '/a/b'],
            'scheme' => ['phar://C:/a/../b', '/', 'phar://C:/b'],
            'empty' => ['', '/', ''],
            'custom separator' => ['C:/a/./b', '\\', 'C:\\a\\b'],
        ];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $path, string $separator, string $expected): void
    {
        $this->assertSame($expected, FilePathDataType::normalize($path, $separator));
    }

    public static function isAbsoluteProvider(): array
    {
        return [
            'unix root' => ['/a', true],
            'backslash root' => ['\\a', true],
            'drive only' => ['C:', true],
            'drive with slash' => ['C:/a', true],
            'drive with backslash' => ['C:\\a', true],
            'drive relative' => ['C:a', false],
            'relative' => ['a/b', false],
            'scheme with absolute path' => ['phar:///a', true],
            'scheme with host' => ['http://host/a', false],
            'empty' => ['', false],
            'null' => [null, false],
        ];
    }

    #[DataProvider('isAbsoluteProvider')]
    public function testIsAbsolute(?string $path, bool $expected): void
    {
        $this->assertSame($expected, FilePathDataType::isAbsolute($path));
        $this->assertSame(! $expected, FilePathDataType::isRelative($path));
    }

    public static function joinProvider(): array
    {
        return [
            'simple' => [['a', 'b'], 'a/b'],
            'slashes' => [['/a/', '/b'], '/a/b'],
            'windows' => [['C:\\a', 'b\\c'], 'C:/a/b/c'],
            'empty parts' => [['', 'a', '', 'b'], 'a/b'],
            'nothing' => [[], ''],
            'scheme' => [['phar://C:/a', '/b'], 'phar://C:/a/b'],
            'with ..' => [['a', '../b'], 'b'],
        ];
    }

    #[DataProvider('joinProvider')]
    public function testJoin(array $paths, string $expected): void
    {
        $this->assertSame($expected, FilePathDataType::join($paths));
    }

    public function testMakeAbsolute(): void
    {
        $this->assertSame('C:/base/a/b', FilePathDataType::makeAbsolute('a/./b', 'C:\\base', '/'));
        $this->assertSame('/x/y', FilePathDataType::makeAbsolute('/x/y', 'C:\\base', '/'));
    }

    public static function findCommonBaseProvider(): array
    {
        return [
            'common parent' => [['/a/b/c', '/a/b/d'], '/', '/a/b'],
            'no false prefix match' => [['/a/bc', '/a/bd'], '/', '/a'],
            'different drives' => [['C:/a/b', 'D:/a/b'], '/', null],
            'mixed separators' => [['C:\\a\\b', 'C:/a/c'], '/', 'C:/a'],
            'nested' => [['/a/b', '/a/b/c'], '\\', '\\a\\b'],
            'unrelated relative' => [['a/b', 'c/d'], '/', ''],
            'empty path' => [['', '/a'], '/', ''],
        ];
    }

    #[DataProvider('findCommonBaseProvider')]
    public function testFindCommonBase(array $paths, string $separator, ?string $expected): void
    {
        $this->assertSame($expected, FilePathDataType::findCommonBase($paths, $separator));
    }

    public static function findExtensionProvider(): array
    {
        return [
            'keeps case' => ['a/file.TXT', 'TXT'],
            'last extension only' => ['a/b.tar.gz', 'gz'],
            'none' => ['a/b', null],
        ];
    }

    #[DataProvider('findExtensionProvider')]
    public function testFindExtension(string $path, ?string $expected): void
    {
        $this->assertSame($expected, FilePathDataType::findExtension($path));
    }

    public function testParseAcceptsRequiredExtensionCaseInsensitive(): void
    {
        $type = $this->createDataType()->setExtension('pdf');
        $this->assertSame('docs/file.PDF', $type->parse('docs/file.PDF'));
    }

    public function testParseRejectsWrongExtension(): void
    {
        $type = $this->createDataType()->setExtension('pdf');
        $this->expectException(DataTypeValidationError::class);
        $type->parse('docs/file.txt');
    }

    public function testParseRejectsMissingExtension(): void
    {
        $type = $this->createDataType()->setExtension('pdf');
        $this->expectException(DataTypeValidationError::class);
        $type->parse('docs/file');
    }

    public function testParseWithoutRequiredExtension(): void
    {
        $this->assertSame('docs/file.txt', $this->createDataType()->parse('docs/file.txt'));
    }

    private function createDataType(): FilePathDataType
    {
        // Global constants are normally loaded by the Workbench
        require_once __DIR__ . '/../../../../CommonLogic/Constants.php';
        $selector = $this->createStub(DataTypeSelectorInterface::class);
        $selector->method('getWorkbench')->willReturn($this->createStub(WorkbenchInterface::class));
        return new FilePathDataType($selector);
    }

    public function testHomeDirectoryFromHome(): void
    {
        $this->withEnv(['HOME' => 'C:\\Users\\test\\..\\demo', 'HOMEDRIVE' => null, 'HOMEPATH' => null], function () {
            $this->assertSame('C:/Users/demo/exface-home', FilePathDataType::normalize('~/exface-home'));
        });
    }

    public function testHomeDirectoryFromHomeDriveAndPath(): void
    {
        $this->withEnv(['HOME' => null, 'HOMEDRIVE' => 'D:', 'HOMEPATH' => '\\Users\\demo'], function () {
            $this->assertSame('D:/Users/demo/exface-drive', FilePathDataType::normalize('~/exface-drive'));
        });
    }

    public function testHomeDirectoryNotAvailable(): void
    {
        $this->withEnv(['HOME' => null, 'HOMEDRIVE' => null, 'HOMEPATH' => null], function () {
            $this->expectException(\RuntimeException::class);
            FilePathDataType::normalize('~/exface-none');
        });
    }

    public function testResultsStayCorrectAfterCacheCleanup(): void
    {
        // More unique paths than the internal cache limit (1250) to trigger its cleanup
        for ($i = 0; $i < 1300; $i++) {
            $this->assertSame("/cache-test/{$i}/b", FilePathDataType::normalize("\\cache-test\\{$i}\\a\\..\\b"));
        }
        $this->assertSame('/cache-test/0/b', FilePathDataType::normalize('\\cache-test\\0\\a\\..\\b'));
    }

    /**
     * Runs $test with the given environment variables (NULL = unset) and restores them afterwards.
     */
    private function withEnv(array $vars, callable $test): void
    {
        $backup = [];
        foreach ($vars as $name => $value) {
            $backup[$name] = getenv($name);
            putenv($value === null ? $name : "{$name}={$value}");
        }
        try {
            $test();
        } finally {
            foreach ($backup as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
    }
}
