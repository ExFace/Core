<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\ModelComponentDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use exface\Core\Factories\DataTypeFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Verifies registry-backed enumeration values without an installed metamodel.
 */
class ModelComponentDataTypeTest extends TestCase
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
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-component-type-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
        $this->workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'model-component-type-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        (new \ReflectionProperty(Workbench::class, 'installation_path'))->setValue($this->workbench, $this->directory);
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ComponentRegistry.config.json',
            '{"COMPONENTS":{"object":{},"agent":{},"etl_step":{}},"SOURCES":{"exface.Core":["object"],"axenox.GenAI":["agent"],"axenox.ETL":["etl_step"]}}'
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
     * Values and labels include every app's installed component keys.
     *
     * @return void
     */
    public function testEnumeratesAndValidatesInstalledComponentKeys() : void
    {
        $type = DataTypeFactory::createFromPrototype($this->workbench, ModelComponentDataType::class);
        self::assertSame(['object', 'agent', 'etl_step'], $type->getValues());
        self::assertSame(['object' => 'object', 'agent' => 'agent', 'etl_step' => 'etl_step'], $type->getLabels());
        self::assertSame('etl_step', $type->parse(' etl_step '));
        self::assertNull($type->parse(null));

        $this->expectException(DataTypeValidationError::class);
        $type->parse('unregistered_type');
    }

    /**
     * An explicitly empty installed registry must not invent allowed values.
     *
     * @return void
     */
    public function testSupportsAnEmptyRegistry() : void
    {
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ComponentRegistry.config.json',
            '{"COMPONENTS":{},"SOURCES":{}}'
        );
        $type = DataTypeFactory::createFromPrototype($this->workbench, ModelComponentDataType::class);
        self::assertSame([], $type->getValues());
        self::assertSame([], $type->toArray());
    }

    /**
     * Inherited enum configuration may narrow the default registry-derived values.
     *
     * @return void
     */
    public function testAppliesConfigurationAfterRegistryDefaults() : void
    {
        $type = new ModelComponentDataType(
            new DataTypeSelector($this->workbench, ModelComponentDataType::class),
            'agent',
            new UxonObject(['values' => ['agent' => 'agent']])
        );
        self::assertSame(['agent'], $type->getValues());
        self::assertSame('agent', $type->parse('agent'));

        $this->expectException(DataTypeValidationError::class);
        $type->parse('object');
    }
}
