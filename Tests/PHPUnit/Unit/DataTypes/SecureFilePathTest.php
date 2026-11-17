<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\FilePathDataType;
use exface\Core\Exceptions\FileNotFoundError;
use exface\Core\Exceptions\FilePathNotAllowedError;
use ErrorException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that canonical file reads cannot escape explicitly allowed directories.
 */
class SecureFilePathTest extends TestCase
{
    private Workbench $workbench;

    private string $fixtureRoot;

    private string $allowedBase;

    private string $outsideFile;

    /**
     * Creates isolated real filesystem fixtures because traversal and symlink behavior cannot be tested with mocks.
     *
     * @return void
     */
    protected function setUp() : void
    {
        $this->workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);
        $this->fixtureRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-secure-path-' . bin2hex(random_bytes(8));
        $this->allowedBase = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'vendor';
        mkdir($this->allowedBase . DIRECTORY_SEPARATOR . 'nested', 0777, true);
        mkdir($this->fixtureRoot . DIRECTORY_SEPARATOR . 'vendor-evil', 0777, true);
        $this->outsideFile = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'outside.txt';
        file_put_contents($this->outsideFile, 'outside');
        file_put_contents($this->allowedBase . DIRECTORY_SEPARATOR . 'inside.txt', 'inside');
        file_put_contents($this->fixtureRoot . DIRECTORY_SEPARATOR . 'vendor-evil' . DIRECTORY_SEPARATOR . 'sibling.txt', 'sibling');
    }

    /**
     * Removes only the unique fixture directory so symlink tests cannot leave files behind.
     *
     * @return void
     */
    protected function tearDown() : void
    {
        if (! isset($this->fixtureRoot) || ! is_dir($this->fixtureRoot)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->fixtureRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() && $item->isDir()) {
                rmdir($item->getPathname());
            } elseif ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } else {
                rmdir($item->getPathname());
            }
        }
        rmdir($this->fixtureRoot);
    }

    /**
     * Confirms that a regular file canonically contained by an allowed base is accepted.
     *
     * @return void
     */
    public function testValidFileInsideBaseReturnsCanonicalPath() : void
    {
        $path = $this->allowedBase . DIRECTORY_SEPARATOR . 'inside.txt';
        $this->assertSame(realpath($path), FilePathDataType::makeSecure($path, $this->workbench, [$this->allowedBase]));
    }

    /**
     * Confirms that parent traversal cannot escape an allowed directory.
     *
     * @return void
     */
    public function testParentTraversalIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->allowedBase . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'outside.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that an absolute path outside every approved base is rejected.
     *
     * @return void
     */
    public function testAbsolutePathOutsideBaseIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure($this->outsideFile, $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that mixing Windows and Unix separators cannot hide traversal.
     *
     * @return void
     */
    public function testMixedSeparatorsAreRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->allowedBase . '\\nested/../../outside.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that a sibling sharing the allowed directory prefix is not treated as contained.
     *
     * @return void
     */
    public function testSiblingPrefixIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->fixtureRoot . DIRECTORY_SEPARATOR . 'vendor-evil' . DIRECTORY_SEPARATOR . 'sibling.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that a symlink inside an approved base cannot point readers to an outside file.
     *
     * @return void
     */
    public function testSymlinkInsideBasePointingOutsideIsRejected() : void
    {
        $link = $this->allowedBase . DIRECTORY_SEPARATOR . 'outside-link.txt';
        if (! @symlink($this->outsideFile, $link)) {
            $this->markTestSkipped('Creating file symlinks is not permitted on this system.');
        }
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure($link, $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that a symlinked deployment base such as current remains usable when its target contains the file.
     *
     * @return void
     */
    public function testSymlinkedBaseWithContainedFileIsAccepted() : void
    {
        $current = $this->fixtureRoot . DIRECTORY_SEPARATOR . 'current';
        if (! @symlink($this->allowedBase, $current)) {
            $this->markTestSkipped('Creating directory symlinks is not permitted on this system.');
        }
        $path = $current . DIRECTORY_SEPARATOR . 'inside.txt';
        $this->assertSame(realpath($path), FilePathDataType::makeSecure($path, $this->workbench, [$current]));
    }

    /**
     * Confirms that PHP streams cannot be substituted for local filesystem paths.
     *
     * @return void
     */
    public function testPhpStreamWrapperIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure('php://memory', $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that file URLs cannot bypass local path validation.
     *
     * @return void
     */
    public function testFileStreamWrapperIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure('file://' . $this->outsideFile, $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that PHAR streams cannot trigger archive reads outside approved roots.
     *
     * @return void
     */
    public function testPharStreamWrapperIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure('phar://' . $this->outsideFile, $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that an empty path cannot resolve implicitly to a process or installation directory.
     *
     * @return void
     */
    public function testEmptyPathIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure('', $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that NUL bytes cannot truncate a path inside filesystem functions.
     *
     * @return void
     */
    public function testNulByteIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->allowedBase . DIRECTORY_SEPARATOR . "inside.txt\0outside.txt",
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that a missing target outside approved roots does not reveal whether the target exists.
     *
     * @return void
     */
    public function testNonExistentFileOutsideBaseIsNotAllowed() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->fixtureRoot . DIRECTORY_SEPARATOR . 'missing-outside.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that only a missing target inside an approved root receives the not-found classification.
     *
     * @return void
     */
    public function testNonExistentFileInsideBaseIsNotFound() : void
    {
        $this->expectException(FileNotFoundError::class);
        FilePathDataType::makeSecure(
            $this->allowedBase . DIRECTORY_SEPARATOR . 'missing-inside.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that dot traversal in a non-canonicalizable missing suffix is rejected rather than normalized.
     *
     * @return void
     */
    public function testParentTraversalInMissingRemainderIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure(
            $this->allowedBase . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . 'inside.txt',
            $this->workbench,
            [$this->allowedBase]
        );
    }

    /**
     * Confirms that a completely missing root is denied without emitting filesystem warnings or notices.
     *
     * @return void
     */
    public function testNonExistentRootIsRejectedWithoutWarnings() : void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $path = null;
            foreach (range('Z', 'D') as $drive) {
                $candidate = $drive . ':\\foo\\bar.txt';
                if (@realpath($drive . ':\\') === false) {
                    $path = $candidate;
                    break;
                }
            }
            if ($path === null) {
                $this->markTestSkipped('No non-existing Windows drive is available for this test.');
            }
        } else {
            $path = DIRECTORY_SEPARATOR . 'exface-nonexistent-root-' . bin2hex(random_bytes(8))
                . DIRECTORY_SEPARATOR . 'x';
        }

        set_error_handler(static function (int $severity, string $message) : bool {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            $this->expectException(FilePathNotAllowedError::class);
            FilePathDataType::makeSecure($path, $this->workbench, [$this->allowedBase]);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Confirms that directories cannot be passed to APIs intended for immediate file reads.
     *
     * @return void
     */
    public function testDirectoryIsRejected() : void
    {
        $this->expectException(FilePathNotAllowedError::class);
        FilePathDataType::makeSecure($this->allowedBase, $this->workbench, [$this->allowedBase]);
    }

    /**
     * Confirms that Windows containment comparisons follow the platform's case-insensitive path semantics.
     *
     * @return void
     */
    public function testWindowsBasePathWithDifferentCaseIsAccepted() : void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows path case behavior is platform-specific.');
        }
        $path = $this->allowedBase . DIRECTORY_SEPARATOR . 'inside.txt';
        $this->assertSame(
            realpath($path),
            FilePathDataType::makeSecure(strtoupper($path), $this->workbench, [strtolower($this->allowedBase)])
        );
    }

    /**
     * Confirms that the Filemanager wrapper reads only the canonical path returned by the guard.
     *
     * @return void
     */
    public function testFilemanagerReadFileSecureReturnsContent() : void
    {
        $path = $this->allowedBase . DIRECTORY_SEPARATOR . 'inside.txt';
        $this->assertSame('inside', $this->workbench->filemanager()->readFileSecure($path, [$this->allowedBase]));
    }
}
