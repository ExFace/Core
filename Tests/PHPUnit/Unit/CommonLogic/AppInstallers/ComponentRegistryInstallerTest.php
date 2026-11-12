<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic\AppInstallers;

use exface\Core\CommonLogic\AppInstallers\ComponentRegistryInstaller;
use exface\Core\CommonLogic\ComponentRegistry;
use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Exceptions\RuntimeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Exercises registry installation and runtime loading in an isolated installation folder.
 */
class ComponentRegistryInstallerTest extends TestCase
{
    private string $directory;
    private Workbench $workbench;
    private Filesystem $filesystem;

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::setUp()
     */
    protected function setUp() : void
    {
        parent::setUp();
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-registry-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
        $this->workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'component-registry-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $path = new \ReflectionProperty(Workbench::class, 'installation_path');
        $path->setValue($this->workbench, $this->directory);
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'exface'
                . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'Config'
                . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME,
            '{"core_type":{"name":"Core type","uxon_properties":[]}}'
        );
    }

    /**
     * {@inheritDoc}
     * @see \PHPUnit\Framework\TestCase::tearDown()
     */
    protected function tearDown() : void
    {
        $this->filesystem->remove($this->directory);
        parent::tearDown();
    }

    /**
     * App fragments augment Core and retain nested metadata and ordered lists exactly.
     *
     * @return void
     */
    public function testInstallsAppFragmentsAndRefreshesAnExistingRegistry() : void
    {
        $registry = $this->workbench->getComponentRegistry();
        self::assertSame(['core_type'], $registry->getComponentKeys());
        $fragment = [
            'agent' => [
                'name' => 'Agent',
                'save_component_data' => [
                    'object_alias' => 'test.Example.AGENT',
                    'columns' => [['attribute_alias' => 'UID'], ['attribute_alias' => 'NAME']]
                ]
            ]
        ];
        $source = $this->writeFragment($fragment);
        iterator_to_array($this->installer()->install($source));

        self::assertSame(['core_type', 'agent'], $registry->getComponentKeys());
        self::assertSame(['agent'], array_values($registry->getComponentKeys('save_component_data')));
        self::assertSame($fragment['agent']['save_component_data'], $registry->getComponentSaveData('agent')->toArray());
        $installed = json_decode(file_get_contents($this->target()), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($fragment['agent'], $installed['COMPONENTS']['agent']);
        self::assertSame(['core_type'], $installed['SOURCES']['exface.Core']);
        self::assertSame(['agent'], $installed['SOURCES']['test.Example']);
    }

    /**
     * Reinstallation replaces one app's metadata and deletes obsolete keys.
     *
     * @return void
     */
    public function testUpdatesAreIdempotentAndPreserveOtherApps() : void
    {
        $source = $this->writeFragment(['old_type' => ['name' => 'Old']]);
        iterator_to_array($this->installer()->install($source));
        $other = $this->writeFragment(['step' => ['name' => 'Step']], 'Other');
        iterator_to_array($this->installer('test.Other')->install($other));

        $this->writeFragment(['agent' => ['name' => 'Updated']]);
        iterator_to_array($this->installer()->install($source));
        $first = file_get_contents($this->target());
        iterator_to_array($this->installer()->install($source));

        self::assertSame($first, file_get_contents($this->target()));
        self::assertSame(['core_type', 'step', 'agent'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * Removing a fragment must also remove its previously installed contribution.
     *
     * @return void
     */
    public function testDeletedFragmentRemovesOldEntries() : void
    {
        $source = $this->writeFragment(['agent' => ['name' => 'Agent']]);
        iterator_to_array($this->installer()->install($source));
        $this->filesystem->remove($source . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME);
        iterator_to_array($this->installer()->install($source));

        self::assertSame(['core_type'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * Uninstallation removes only the selected app and is safe to repeat.
     *
     * @return void
     */
    public function testUninstallPreservesOtherContributions() : void
    {
        iterator_to_array($this->installer()->install($this->writeFragment(['agent' => ['name' => 'Agent']])));
        iterator_to_array($this->installer('test.Other')->install($this->writeFragment(['step' => ['name' => 'Step']], 'Other')));
        iterator_to_array($this->installer()->uninstall());
        iterator_to_array($this->installer()->uninstall());

        self::assertSame(['core_type', 'step'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * Conflicting keys fail without removing the app's previous valid metadata.
     *
     * @return void
     */
    public function testCollisionLeavesInstalledRegistryUnchanged() : void
    {
        $source = $this->writeFragment(['agent' => ['name' => 'Agent']]);
        iterator_to_array($this->installer()->install($source));
        $before = file_get_contents($this->target());
        $this->writeFragment(['core_type' => ['name' => 'Conflicting type']]);

        try {
            iterator_to_array($this->installer()->install($source));
            self::fail('Expected a component ownership conflict');
        } catch (InstallerRuntimeError $e) {
            self::assertStringContainsString('already owned by another app', $e->getMessage());
        }
        self::assertSame($before, file_get_contents($this->target()));
        self::assertSame(['core_type', 'agent'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * A malformed fragment must not replace the existing valid registry.
     *
     * @return void
     */
    public function testInvalidFragmentLeavesInstalledRegistryUnchanged() : void
    {
        $source = $this->writeFragment(['agent' => ['name' => 'Agent']]);
        iterator_to_array($this->installer()->install($source));
        $before = file_get_contents($this->target());
        $this->filesystem->dumpFile($source . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME, '{invalid');
        try {
            iterator_to_array($this->installer()->install($source));
            self::fail('Expected invalid JSON to fail');
        } catch (InstallerRuntimeError $e) {
            self::assertStringContainsString('Cannot update component registry', $e->getMessage());
        }
        self::assertSame($before, file_get_contents($this->target()));
    }

    /**
     * The installed registry takes precedence, including an intentionally empty registry.
     *
     * @return void
     */
    public function testRootConfigTakesPrecedenceOverCoreFallback() : void
    {
        $this->filesystem->dumpFile($this->target(), '{"COMPONENTS":{"agent":{"name":"Installed agent"}},"SOURCES":{"test.Example":["agent"]}}');
        $registry = new ComponentRegistry($this->workbench);
        self::assertSame(['agent'], $registry->getComponentKeys());

        $this->filesystem->dumpFile($this->target(), '{"COMPONENTS":{},"SOURCES":{}}');
        $registry->reload();
        self::assertSame([], $registry->getComponentKeys());
        $this->filesystem->remove($this->target());
        $registry->reload();
        self::assertSame(['core_type'], $registry->getComponentKeys());
    }

    /**
     * An existing but invalid installation file must not silently use Core defaults.
     *
     * @return void
     */
    public function testInvalidInstalledConfigDoesNotFallBack() : void
    {
        $this->filesystem->dumpFile($this->target(), '{"SOURCES":{}}');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a component object');
        new ComponentRegistry($this->workbench);
    }

    /**
     * All apps get the installer without adding a custom app class.
     *
     * @return void
     */
    public function testBaseAppRegistersTheInstaller() : void
    {
        $app = new App(new AppSelector($this->workbench, 'test.Example'));
        $installers = $app->getInstaller()->getInstallers();
        self::assertCount(1, array_filter($installers, static function ($installer) {
            return $installer instanceof ComponentRegistryInstaller;
        }));
    }

    /**
     * Reinstalling Core replaces the seeded defaults without deleting another app's types.
     *
     * @return void
     */
    public function testCoreUpdateReplacesSeededDefaults() : void
    {
        iterator_to_array($this->installer()->install($this->writeFragment(['agent' => ['name' => 'Agent']])));
        $core = $this->writeFragment(['new_core_type' => ['name' => 'New Core type']], 'Core');
        iterator_to_array($this->installer('exface.Core')->install($core));

        self::assertSame(['agent', 'new_core_type'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * Apps without contributions do not create an incomplete installation registry.
     *
     * @return void
     */
    public function testMissingFragmentAndUninstallBeforeInstallationAreNoOps() : void
    {
        iterator_to_array($this->installer()->install($this->directory . DIRECTORY_SEPARATOR . 'missing'));
        iterator_to_array($this->installer()->uninstall());

        self::assertFileDoesNotExist($this->target());
        self::assertSame(['core_type'], $this->workbench->getComponentRegistry()->getComponentKeys());
    }

    /**
     * Inconsistent ownership must not allow an app to delete another app's definition.
     *
     * @return void
     */
    public function testInvalidOwnershipLeavesRegistryUnchanged() : void
    {
        $invalid = '{"COMPONENTS":{"agent":{}},"SOURCES":{"test.Example":["agent"],"test.Other":["agent"]}}';
        $this->filesystem->dumpFile($this->target(), $invalid);
        try {
            iterator_to_array($this->installer()->uninstall());
            self::fail('Expected invalid component ownership to fail');
        } catch (InstallerRuntimeError $e) {
            self::assertStringContainsString('Invalid component ownership', $e->getMessage());
        }
        self::assertSame($invalid, file_get_contents($this->target()));
    }

    /**
     * @param array $models
     * @param string $app
     * @return string
     */
    private function writeFragment(array $models, string $app = 'Example') : string
    {
        $source = $this->directory . DIRECTORY_SEPARATOR . 'apps' . DIRECTORY_SEPARATOR . $app;
        $this->filesystem->dumpFile(
            $source . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME,
            json_encode((object) $models, JSON_THROW_ON_ERROR)
        );
        return $source;
    }

    /**
     * @param string $alias
     * @return ComponentRegistryInstaller
     */
    private function installer(string $alias = 'test.Example') : ComponentRegistryInstaller
    {
        return new ComponentRegistryInstaller(new AppSelector($this->workbench, $alias));
    }

    /**
     * @return string
     */
    private function target() : string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . ComponentRegistry::CONFIG_FILENAME;
    }
}
