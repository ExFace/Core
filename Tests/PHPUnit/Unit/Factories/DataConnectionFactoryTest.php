<?php
namespace exface\Core\Tests\PHPUnit\Unit\Factories;

use exface\Core\CommonLogic\Selectors\DataConnectorSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\LocalFileConnector;
use exface\Core\Factories\DataConnectionFactory;
use PHPUnit\Framework\TestCase;

/**
 * Protects the vendor connection's recovery default without a persisted metamodel.
 */
class DataConnectionFactoryTest extends TestCase
{
    /**
     * Missing or empty model configuration must still resolve paths inside vendor.
     *
     * @return void
     */
    public function testDefaultsVendorConnectionWithEmptyModelConfiguration(): void
    {
        $workbench = $this->workbench();
        foreach ([null, '', '{}'] as $modelConfig) {
            $connection = DataConnectionFactory::create(
                new DataConnectorSelector($workbench, LocalFileConnector::class),
                UxonObject::fromJson($modelConfig),
                null,
                'LOCAL_VENDOR_FOLDERS',
                'exface.Core'
            );

            $this->assertTrue($connection->getUseVendorFolderAsBase());
            $this->assertSame($workbench->filemanager()->getPathToVendorFolder(), $connection->getBasePath());
        }
    }

    /**
     * Connection aliases are case-insensitive, including the recovery default.
     *
     * @return void
     */
    public function testMatchesVendorConnectionCaseInsensitively(): void
    {
        $connection = DataConnectionFactory::create(
            new DataConnectorSelector($this->workbench(), LocalFileConnector::class),
            new UxonObject(),
            null,
            'local_vendor_folders',
            'exface.core'
        );

        $this->assertTrue($connection->getUseVendorFolderAsBase());
    }

    /**
     * A non-empty model configuration remains authoritative.
     *
     * @return void
     */
    public function testPreservesExplicitVendorConnectionConfiguration(): void
    {
        $workbench = $this->workbench();
        foreach ([['use_vendor_folder_as_base' => false], ['base_path' => 'custom-vendor']] as $config) {
            $connection = DataConnectionFactory::create(
                new DataConnectorSelector($workbench, LocalFileConnector::class),
                new UxonObject($config),
                null,
                'LOCAL_VENDOR_FOLDERS',
                'exface.Core'
            );

            $this->assertFalse($connection->getUseVendorFolderAsBase());
        }
    }

    /**
     * Other local file connections must keep their original default.
     *
     * @return void
     */
    public function testDoesNotDefaultOtherConnectionsToVendor(): void
    {
        $workbench = $this->workbench();
        foreach ([['OTHER_FILES', 'exface.Core'], ['LOCAL_VENDOR_FOLDERS', 'other.App']] as [$alias, $namespace]) {
            $connection = DataConnectionFactory::create(
                new DataConnectorSelector($workbench, LocalFileConnector::class),
                new UxonObject(),
                null,
                $alias,
                $namespace
            );

            $this->assertFalse($connection->getUseVendorFolderAsBase());
        }
    }

    /**
     * Unnamed prototypes must not inherit a model connection's default.
     *
     * @return void
     */
    public function testDoesNotDefaultPrototypeToVendor(): void
    {
        $connection = DataConnectionFactory::createFromPrototype($this->workbench(), LocalFileConnector::class);

        $this->assertFalse($connection->getUseVendorFolderAsBase());
    }

    /**
     * Creates a real workbench without starting it or accessing the model database.
     *
     * @return Workbench
     */
    private function workbench(): Workbench
    {
        return new Workbench([
            'SERVER.INSTALLATION_NAME' => 'data-connection-factory-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
    }
}
