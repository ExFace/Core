<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\AppInstallers\DataInstaller;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\Installers\InstallerRuntimeError;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Factories\MetaObjectFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Checks how the DataInstaller combines model files of the same type with different columns.
 * 
 * Uses a real Workbench with an in-memory metamodel and isolates only file discovery.
 */
class DataInstallerSchemaMergeTest extends TestCase
{
    private ?Workbench $workbench = null;
    
    private array $objects = [];

    /**
     * Removes in-memory objects from the meta object cache.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->objects as $object) {
            MetaObjectFactory::clearCache($object, false);
        }
        $this->objects = [];
    }

    /**
     * Files with the same columns are concatenated as-is.
     *
     * @return void
     */
    public function testConcatenatesSameColumns(): void
    {
        $sheet = $this->read([
            [[['attribute_alias' => 'UID']], [['UID' => 'first']]],
            [[['attribute_alias' => 'UID']], [['UID' => 'second']]]
        ]);

        $this->assertSame(['UID'], $this->columnNames($sheet));
        $this->assertSame([['UID' => 'first'], ['UID' => 'second']], $sheet->getRows());
    }

    /**
     * Only names matter: different order and data types are still the same columns.
     *
     * @return void
     */
    public function testIgnoresColumnOrderAndDataTypes(): void
    {
        $sheet = $this->read([
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'ICON']], [['UID' => 'first', 'ICON' => 'a']]],
            [[['attribute_alias' => 'ICON', 'data_type' => NumberDataType::class], ['attribute_alias' => 'UID']], [['ICON' => 'b', 'UID' => 'second']]]
        ]);

        $this->assertSame(['UID', 'ICON'], $this->columnNames($sheet));
        $this->assertSame(['first', 'second'], $sheet->getColumnValues('UID'));
        $this->assertSame(['a', 'b'], $sheet->getColumnValues('ICON'));
    }

    /**
     * An older file read after a newer one gets NULL for the columns it lacks.
     *
     * @return void
     */
    public function testFillsColumnsMissingInLaterFile(): void
    {
        $sheet = $this->read([
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'ICON']], [['UID' => 'new', 'ICON' => 'star']]],
            [[['attribute_alias' => 'UID']], [['UID' => 'old']]]
        ]);

        $this->assertSame(['UID', 'ICON'], $this->columnNames($sheet));
        $this->assertSame([['UID' => 'new', 'ICON' => 'star'], ['UID' => 'old', 'ICON' => null]], $sheet->getRows());
    }

    /**
     * A newer file read after an older one adds its columns to the sheet and NULL to earlier rows.
     *
     * @return void
     */
    public function testAddsColumnsMissingInEarlierFiles(): void
    {
        $sheet = $this->read([
            [[['attribute_alias' => 'UID']], [['UID' => 'old']]],
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'ICON']], [['UID' => 'new', 'ICON' => 'star']]],
            [[['attribute_alias' => 'UID']], [['UID' => 'older']]]
        ]);

        $this->assertSame(['UID', 'ICON'], $this->columnNames($sheet));
        $this->assertSame([
            ['UID' => 'old', 'ICON' => null],
            ['UID' => 'new', 'ICON' => 'star'],
            ['UID' => 'older', 'ICON' => null]
        ], $sheet->getRows());
    }

    /**
     * A required column missing in a later file must be rejected with a clear message.
     *
     * @return void
     */
    public function testRejectsRequiredColumnMissingInLaterFile(): void
    {
        $this->expectException(InstallerRuntimeError::class);
        $this->expectExceptionMessage('column "REQ" is missing in model file "04_ATTRIBUTE.json@folder1"');

        $this->read([
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'REQ']], [['UID' => 'new', 'REQ' => 'a']]],
            [[['attribute_alias' => 'UID']], [['UID' => 'old']]]
        ]);
    }

    /**
     * A required column added by a later file must be rejected if earlier files have rows.
     *
     * @return void
     */
    public function testRejectsRequiredColumnMissingInEarlierFiles(): void
    {
        $this->expectException(InstallerRuntimeError::class);
        $this->expectExceptionMessage('column "REQ" is missing in model files read before "04_ATTRIBUTE.json@folder1"');

        $this->read([
            [[['attribute_alias' => 'UID']], [['UID' => 'old']]],
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'REQ']], [['UID' => 'new', 'REQ' => 'a']]]
        ]);
    }

    /**
     * Required columns with a default value, or missing only in files without rows, are fine.
     *
     * @return void
     */
    public function testAllowsMissingRequiredColumnWithDefaultOrWithoutRows(): void
    {
        $sheet = $this->read([
            [[['attribute_alias' => 'UID']], []],
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'REQ'], ['attribute_alias' => 'REQ_DEF']], [['UID' => 'new', 'REQ' => 'a', 'REQ_DEF' => 'b']]],
            [[['attribute_alias' => 'UID'], ['attribute_alias' => 'REQ']], [['UID' => 'old', 'REQ' => 'c']]]
        ]);

        $this->assertSame(['UID', 'REQ', 'REQ_DEF'], $this->columnNames($sheet));
        $this->assertSame([
            ['UID' => 'new', 'REQ' => 'a', 'REQ_DEF' => 'b'],
            ['UID' => 'old', 'REQ' => 'c', 'REQ_DEF' => null]
        ], $sheet->getRows());
    }

    /**
     * Files with the same name but for different objects cannot be combined.
     *
     * @return void
     */
    public function testRejectsDifferentObjects(): void
    {
        $this->expectException(InstallerRuntimeError::class);
        $this->expectExceptionMessage('Model sheet type mismatch');

        $this->read([
            [[['attribute_alias' => 'UID']], [['UID' => 'first']]],
            [[['attribute_alias' => 'UID']], [['UID' => 'second']], true]
        ]);
    }

    /**
     * Reads the given model files through the real folder reader.
     *
     * @param array $files List of [columns, rows, useOtherObject]
     * @return DataSheetInterface
     */
    private function read(array $files): DataSheetInterface
    {
        $workbench = $this->getWorkbench();
        $object = $this->createObject('MAIN');
        $other = $this->createObject('OTHER');
        $uxons = [];
        foreach ($files as $i => $file) {
            $uxons['04_ATTRIBUTE.json@folder' . $i] = new UxonObject([
                'object_alias' => (($file[2] ?? false) ? $other : $object)->getAliasWithNamespace(),
                'columns' => $file[0],
                'rows' => $file[1]
            ]);
        }
        $installer = $this->getMockBuilder(DataInstaller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench', 'readDataSheetUxonsFromFolder'])
            ->getMock();
        $installer->method('getWorkbench')->willReturn($workbench);
        $installer->method('readDataSheetUxonsFromFolder')->willReturn($uxons);
        $read = new ReflectionMethod(DataInstaller::class, 'readModelSheetsFromFolders');
        $read->setAccessible(true);

        $sheets = iterator_to_array($read->invoke($installer, 'unused-model-folder'));
        $this->assertCount(1, $sheets);
        return $sheets[0];
    }

    /**
     * Returns a Workbench with an empty in-memory model.
     *
     * @return Workbench
     */
    private function getWorkbench(): Workbench
    {
        if ($this->workbench === null) {
            $this->workbench = new Workbench();
            $modelProperty = new ReflectionProperty(Workbench::class, 'mm');
            $modelProperty->setAccessible(true);
            $modelProperty->setValue($this->workbench, new Model($this->workbench));
        }
        return $this->workbench;
    }

    /**
     * Creates and caches an in-memory object with attributes UID, ICON (optional), REQ (required)
     * and REQ_DEF (required with default value).
     *
     * @param string $suffix
     * @return MetaObject
     */
    private function createObject(string $suffix): MetaObject
    {
        $workbench = $this->getWorkbench();
        $object = new MetaObject($workbench->model());
        $object->setAlias(uniqid('SCHEMA_MERGE_' . $suffix . '_'));
        $object->setNamespace('exface.Core');
        $object->setId(uniqid('schema_merge_'));
        foreach (['UID' => [false, null], 'ICON' => [false, null], 'REQ' => [true, null], 'REQ_DEF' => [true, 'x']] as $alias => [$required, $default]) {
            // In-memory attributes need no UID; UUID v1 node discovery can invoke host tools.
            $attribute = (new ReflectionClass(Attribute::class))->newInstanceWithoutConstructor();
            $attribute->setObject($object);
            $attribute->setName($alias);
            $attribute->setAlias($alias);
            $attribute->setDataType(DataTypeFactory::createFromPrototype($workbench, StringDataType::class));
            $attribute->setRequired($required);
            if ($default !== null) {
                $attribute->setDefaultValue($default);
            }
            $object->addAttribute($attribute);
        }
        $cacheMethod = new ReflectionMethod(MetaObjectFactory::class, 'setCache');
        $cacheMethod->setAccessible(true);
        $cacheMethod->invoke(null, $object);
        $this->objects[] = $object;
        return $object;
    }

    /**
     * Returns the column names of a sheet in their order.
     *
     * @param DataSheetInterface $sheet
     * @return string[]
     */
    private function columnNames(DataSheetInterface $sheet): array
    {
        return array_keys($sheet->getColumns()->getAll());
    }
}
