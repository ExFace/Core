<?php
namespace exface\Core\Tests\PHPUnit\Unit\Factories;

use exface\Core\CommonLogic\Selectors\DataConnectorSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataConnectors\LocalFileConnector;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Tests\PHPUnit\Support\VendorFolderConnector;
use PHPUnit\Framework\TestCase;

/**
 * Ensures model-specific recovery defaults remain confined to the factory.
 */
class LocalFileConnectionDefaultTest extends TestCase
{
    /**
     * Assigning an alias after the factory returns must not change connector defaults.
     *
     * @return void
     */
    public function testDoesNotDefaultPrototypeAfterFactoryReturns(): void
    {
        $workbench = $this->workbench();
        $connection = DataConnectionFactory::createFromPrototype($workbench, LocalFileConnector::class);
        $connection->setAlias('LOCAL_VENDOR_FOLDERS', 'exface.Core');

        $this->assertFalse($connection->getUseVendorFolderAsBase());
        $this->assertSame($workbench->getInstallationPath(), $connection->getBasePath());
    }

    /**
     * Prototype selectors skip model defaults even when the connector already has an alias.
     *
     * @return void
     */
    public function testPrototypeSelectorDoesNotApplyModelDefaults(): void
    {
        $connection = DataConnectionFactory::createFromSelector(
            new DataConnectorSelector($this->workbench(), VendorFolderConnector::class)
        );

        $this->assertSame('exface.Core.LOCAL_VENDOR_FOLDERS', $connection->getAliasWithNamespace());
        $this->assertFalse($connection->getUseVendorFolderAsBase());
    }

    /**
     * Configured creation applies the default after the bare prototype selector skips it.
     *
     * @return void
     */
    public function testConfiguredCreationAppliesModelDefaults(): void
    {
        $workbench = $this->workbench();
        $connection = DataConnectionFactory::create(
            new DataConnectorSelector($workbench, VendorFolderConnector::class),
            new UxonObject()
        );

        $this->assertTrue($connection->getUseVendorFolderAsBase());
        $this->assertSame($workbench->filemanager()->getPathToVendorFolder(), $connection->getBasePath());
    }

    /**
     * The prototype helper retains its recovery fallback for an already identified connection.
     *
     * @return void
     */
    public function testPrototypeHelperDefaultsIdentifiedConnectionWithMissingConfiguration(): void
    {
        $workbench = $this->workbench();
        foreach ([null, new UxonObject()] as $config) {
            $connection = DataConnectionFactory::createFromPrototype(
                $workbench,
                VendorFolderConnector::class,
                $config
            );

            $this->assertTrue($connection->getUseVendorFolderAsBase());
            $this->assertSame($workbench->filemanager()->getPathToVendorFolder(), $connection->getBasePath());
        }
    }

    /**
     * Neither prototype selection nor the prototype helper may override explicit configuration.
     *
     * @return void
     */
    public function testPrototypeHelperPreservesExplicitConfiguration(): void
    {
        $connection = DataConnectionFactory::createFromPrototype(
            $this->workbench(),
            VendorFolderConnector::class,
            new UxonObject(['use_vendor_folder_as_base' => false])
        );

        $this->assertFalse($connection->getUseVendorFolderAsBase());
    }

    /**
     * Direct construction must not apply factory workarounds based on model aliases.
     *
     * @return void
     */
    public function testDirectConstructionRemainsModelAgnostic(): void
    {
        $connection = new LocalFileConnector(
            new DataConnectorSelector($this->workbench(), LocalFileConnector::class)
        );
        $connection->setAlias('LOCAL_VENDOR_FOLDERS', 'exface.Core');
        $this->assertFalse($connection->getUseVendorFolderAsBase());
    }

    /**
     * Prototype configuration stays authoritative when the alias is assigned later.
     *
     * @return void
     */
    public function testPreservesConfigurationImportedBeforeAlias(): void
    {
        $connection = DataConnectionFactory::createFromPrototype(
            $this->workbench(),
            LocalFileConnector::class,
            new UxonObject(['base_path' => 'custom-vendor'])
        );
        $connection->setAlias('LOCAL_VENDOR_FOLDERS', 'exface.Core');

        $this->assertFalse($connection->getUseVendorFolderAsBase());
    }

    /**
     * Creates a real workbench without starting services or accessing the model database.
     *
     * @return Workbench
     */
    private function workbench(): Workbench
    {
        return new Workbench([
            'SERVER.INSTALLATION_NAME' => 'local-file-connection-default-unit-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
    }
}
