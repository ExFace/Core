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
}
