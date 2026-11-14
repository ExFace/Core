<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\Log\Logger;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\FilePathDataType;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Verifies that configured read roots are resolved without weakening the filesystem allowlist.
 */
class FilemanagerAllowedBasePathsTest extends TestCase
{
    private const TEST_OPTION = 'TESTS.ALLOWED_BASE_PATHS';

    /**
     * Confirms relative entries are installation-relative and never depend on the process working directory.
     *
     * @return void
     */
    public function testRelativeEntryIsResolvedAgainstInstallationPath() : void
    {
        $workbench = $this->createWorkbench(['vendor']);

        $this->assertSame(
            [FilePathDataType::makeAbsolute('vendor', $workbench->getInstallationPath())],
            $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION)
        );
    }

    /**
     * Confirms valid entries never initialize the logger because resolution must not require unrelated event services.
     *
     * @return void
     */
    public function testValidEntriesDoNotInitializeLogger() : void
    {
        $workbench = new Workbench([
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false,
            self::TEST_OPTION => ['vendor']
        ]);
        $loggerProperty = new ReflectionProperty(Workbench::class, 'logger');
        $this->assertNull($loggerProperty->getValue($workbench));

        for ($call = 0; $call < 2; $call++) {
            $this->assertSame(
                [FilePathDataType::makeAbsolute('vendor', $workbench->getInstallationPath())],
                $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION)
            );
            $this->assertNull($loggerProperty->getValue($workbench));
        }
    }

    /**
     * Confirms absolute entries retain their configured form so symlinked deployment paths are not canonicalized early.
     *
     * @return void
     */
    public function testAbsoluteEntryIsKept() : void
    {
        $absolutePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-allowed-base';
        $workbench = $this->createWorkbench([$absolutePath]);

        $this->assertSame(
            [$absolutePath],
            $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION)
        );
    }

    /**
     * Confirms an installation-root entry cannot silently allow every installation file.
     *
     * @return void
     */
    public function testInstallationRootIsRejected() : void
    {
        $workbench = $this->createWorkbench([]);
        $workbench->getConfig()->setOption(self::TEST_OPTION, [$workbench->getInstallationPath()]);

        $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
    }

    /**
     * Confirms relative entries that normalize to the installation root cannot broaden the allowlist.
     *
     * @return void
     */
    public function testEntriesResolvingToInstallationRootAreRejected() : void
    {
        $workbench = $this->createWorkbench(['vendor/..', '.']);

        $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
    }

    /**
     * Confirms the installation config directory cannot be exposed as an approved read root.
     *
     * @return void
     */
    public function testConfigFolderIsRejected() : void
    {
        $workbench = $this->createWorkbench([]);
        $configPath = $workbench->filemanager()->getPathToConfigFolder();
        $workbench->getConfig()->setOption(self::TEST_OPTION, [$configPath]);

        $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
    }

    /**
     * Confirms a parent of the config directory cannot indirectly expose installation configuration.
     *
     * @return void
     */
    public function testParentOfConfigFolderIsRejected() : void
    {
        $workbench = $this->createWorkbench([]);
        $configParent = dirname($workbench->filemanager()->getPathToConfigFolder(), 2);
        $workbench->getConfig()->setOption(self::TEST_OPTION, [$configParent]);

        $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
    }

    /**
     * Confirms a symlink to the installation root is rejected because reads canonicalize the link target.
     *
     * @return void
     */
    public function testSymlinkToInstallationRootIsRejected() : void
    {
        $workbench = $this->createWorkbench([]);
        $link = $this->createDirectorySymlinkOrSkip($workbench->getInstallationPath());
        try {
            $workbench->getConfig()->setOption(self::TEST_OPTION, [$link]);

            $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
        } finally {
            $this->removeDirectorySymlink($link);
        }
    }

    /**
     * Confirms a symlink to the config folder cannot bypass the lexical config-path restriction.
     *
     * @return void
     */
    public function testSymlinkToConfigFolderIsRejected() : void
    {
        $workbench = $this->createWorkbench([]);
        $link = $this->createDirectorySymlinkOrSkip($workbench->filemanager()->getPathToConfigFolder());
        try {
            $workbench->getConfig()->setOption(self::TEST_OPTION, [$link]);

            $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
        } finally {
            $this->removeDirectorySymlink($link);
        }
    }

    /**
     * Confirms a symlinked installation root is resolved for checks while its configured form is returned.
     *
     * @return void
     */
    public function testSymlinkedInstallationRootKeepsNormalVendorEntry() : void
    {
        $workbench = $this->createWorkbench(['vendor']);
        $link = $this->createDirectorySymlinkOrSkip($workbench->getInstallationPath());
        try {
            $installationPathProperty = new ReflectionProperty(Workbench::class, 'installation_path');
            $installationPathProperty->setValue($workbench, $link);

            $this->assertSame(
                [FilePathDataType::makeAbsolute('vendor', $link)],
                $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION)
            );
        } finally {
            $this->removeDirectorySymlink($link);
        }
    }

    /**
     * Confirms rejected values and their option are logged once as errors across fresh Filemanager instances.
     *
     * @return void
     */
    public function testSkippedEntryIsLoggedOnceAsErrorWithValueAndOption() : void
    {
        $entry = 'vendor/..';
        $workbench = $this->createWorkbench([$entry]);
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'Ignoring unsafe path entry ' . var_export($entry, true)
                    . ' in configuration option "' . self::TEST_OPTION . '".',
                [],
                null
            );
        $loggerProperty = new ReflectionProperty(Workbench::class, 'logger');
        $loggerProperty->setValue($workbench, $logger);

        $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION);
        $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION);
    }

    /**
     * Confirms empty, non-string, and stream-wrapper entries are ignored instead of broadening the allowlist.
     *
     * @return void
     */
    public function testEmptyAndWrapperEntriesAreSkipped() : void
    {
        $workbench = $this->createWorkbench(['', '   ', null, 'php://memory', 'file:///tmp/file']);

        $this->assertSame([], $workbench->filemanager()->resolveAllowedBasePaths(self::TEST_OPTION));
    }

    /**
     * Confirms the system default keeps app-owned vendor templates readable after configuration becomes shared.
     *
     * @return void
     */
    public function testUxonReadDefaultAllowsVendor() : void
    {
        $workbench = new Workbench(['DEBUG.LIVE_CLASS_AUTOLOADER' => false]);

        $this->assertSame(
            [FilePathDataType::makeAbsolute('vendor', $workbench->getInstallationPath())],
            $workbench->filemanager()->resolveAllowedBasePaths('FILES.UXON_ALLOWED_BASE_PATHS')
        );
    }

    /**
     * Creates a real Workbench because the resolver contract depends on its actual installation and configuration.
     *
     * @param array $paths
     * @return Workbench
     */
    private function createWorkbench(array $paths) : Workbench
    {
        $workbench = new Workbench([
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false,
            self::TEST_OPTION => $paths
        ]);
        $loggerProperty = new ReflectionProperty(Workbench::class, 'logger');
        $loggerProperty->setValue($workbench, new Logger());
        return $workbench;
    }

    /**
     * Creates a directory link for filesystem tests and skips when the host forbids symlink creation.
     *
     * @param string $target
     * @return string
     */
    private function createDirectorySymlinkOrSkip(string $target) : string
    {
        $link = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-allowed-' . bin2hex(random_bytes(8));
        if (! @symlink($target, $link)) {
            $this->removeDirectorySymlink($link);
            $this->markTestSkipped('The current host does not permit creating directory symlinks.');
        }

        return $link;
    }

    /**
     * Removes a directory link with the operation Windows requires for directory entries.
     *
     * @param string $link
     * @return void
     */
    private function removeDirectorySymlink(string $link) : void
    {
        if (! is_link($link)) {
            return;
        }

        if (is_dir($link)) {
            rmdir($link);
        } else {
            unlink($link);
        }
    }
}
