<?php
namespace exface\Core\Tests\PHPUnit\Unit\DataTypes;

use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\ModelComponentDataType;
use exface\Core\Exceptions\DataTypes\DataTypeValidationError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Verifies that registry labels cannot diverge from runtime component validation.
 */
class ModelComponentRegistryValidationTest extends TestCase
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
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exface-component-validation-' . bin2hex(random_bytes(8));
        $this->filesystem->mkdir($this->directory);
        $this->workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'model-component-validation-test',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        (new \ReflectionProperty(Workbench::class, 'installation_path'))->setValue($this->workbench, $this->directory);
        $this->filesystem->dumpFile(
            $this->directory . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'ComponentRegistry.config.json',
            '{"COMPONENTS":{"object":{"name":"Meta object"},"ai_tool":{"name":"AI tool"}},"SOURCES":{"exface.Core":["object"],"axenox.GenAI":["ai_tool"]}}'
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
     * Every advertised key parses even when its label is different.
     *
     * @return void
     */
    public function testParsesAdvertisedKeysAndConstructorValue() : void
    {
        $type = new ModelComponentDataType(
            new DataTypeSelector($this->workbench, ModelComponentDataType::class),
            'ai_tool'
        );

        self::assertSame('ai_tool', $type->getValue());
        self::assertSame(['object' => 'Meta object', 'ai_tool' => 'AI tool'], $type->getLabels());
        self::assertSame($type->getLabels(), $type->toArray());
        foreach ($type->getValues() as $key) {
            self::assertSame($key, $type->parse(' ' . $key . ' '));
        }
        self::assertSame('ai_tool', $type->parse('AI tool'));
        self::assertNull($type->parse(null));
    }

    /**
     * Populating registry defaults does not allow unregistered component keys.
     *
     * @return void
     */
    public function testRejectsUnregisteredKeys() : void
    {
        $type = new ModelComponentDataType(new DataTypeSelector($this->workbench, ModelComponentDataType::class));

        $this->expectException(DataTypeValidationError::class);
        $type->parse('unknown_component');
    }

    /**
     * Configured subsets restrict both schema values and runtime parsing.
     *
     * @return void
     */
    public function testConfiguredSubsetReplacesRegistryDefaults() : void
    {
        $type = new ModelComponentDataType(
            new DataTypeSelector($this->workbench, ModelComponentDataType::class),
            'ai_tool',
            new UxonObject(['values' => ['ai_tool' => 'AI tool']])
        );

        self::assertSame(['ai_tool'], $type->getValues());
        self::assertSame(['ai_tool' => 'AI tool'], $type->getLabels());
        self::assertSame('ai_tool', $type->parse('ai_tool'));

        $this->expectException(DataTypeValidationError::class);
        $type->parse('object');
    }
}
