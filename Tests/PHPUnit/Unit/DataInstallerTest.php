<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\AppInstallers\DataInstaller;
use exface\Core\CommonLogic\UxonObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Checks export ordering without requiring a persisted metamodel or a Workbench mock.
 */
class DataInstallerTest extends TestCase
{
    /**
     * Unnamed columns in older files must retain their position alongside explicitly named columns.
     * 
     * @return void
     */
    public function testImplicitNamesPreserveColumnAndRowOrder(): void
    {
        $previous = [
            ['name' => '_EXPORT_SUMMARY', 'attribute_alias' => 'LABEL'],
            ['attribute_alias' => 'DATA_SOURCE'],
            ['name' => 'ALIAS', 'attribute_alias' => 'ALIAS'],
            ['attribute_alias' => 'INHERIT_DATA_SOURCE_BASE_OBJECT']
        ];
        $current = [
            ['name' => 'INHERIT_DATA_SOURCE_BASE_OBJECT', 'attribute_alias' => 'INHERIT_DATA_SOURCE_BASE_OBJECT'],
            ['name' => 'ALIAS', 'attribute_alias' => 'ALIAS'],
            ['name' => 'DATA_SOURCE', 'attribute_alias' => 'DATA_SOURCE'],
            ['name' => '_EXPORT_SUMMARY', 'attribute_alias' => 'LABEL']
        ];
        $row = ['ALIAS' => 'Object', 'DATA_SOURCE' => 'source', '_EXPORT_SUMMARY' => 'Object', 'INHERIT_DATA_SOURCE_BASE_OBJECT' => 1];
        $expected = ['_EXPORT_SUMMARY', 'DATA_SOURCE', 'ALIAS', 'INHERIT_DATA_SOURCE_BASE_OBJECT'];

        $result = $this->reorder($previous, $current, $row);

        $this->assertSame($expected, array_column($result['columns'], 'name'));
        $this->assertSame($expected, array_keys($result['rows'][0]));
        $this->assertEquals($row, $result['rows'][0]);

        foreach ($previous as $index => $column) {
            $previous[$index]['name'] = $column['name'] ?? $column['attribute_alias'];
        }
        $this->assertSame($result, $this->reorder($previous, $current, $row));
    }

    /**
     * Implicit names must follow factory expression precedence and DataColumn sanitization.
     * 
     * @return void
     */
    public function testImplicitExpressionsAndFormulasUseSanitizedNames(): void
    {
        $previous = [
            ['attribute_alias' => 'AMOUNT:SUM'],
            ['expression' => 'PRICE:SUM', 'attribute_alias' => 'IGNORED'],
            ['formula' => '=1+2']
        ];
        $current = array_reverse($previous);
        $row = ['_1_2' => 3, 'PRICE_SUM' => 20, 'AMOUNT_SUM' => 10];

        $result = $this->reorder($previous, $current, $row);

        $this->assertSame($previous, $result['columns']);
        $this->assertSame(['AMOUNT_SUM', 'PRICE_SUM', '_1_2'], array_keys($result['rows'][0]));
        $this->assertEquals($row, $result['rows'][0]);
    }

    /**
     * Restoring order must still append new columns, drop removed columns and retain extra row keys.
     * 
     * @return void
     */
    public function testAddedAndRemovedColumnsPreserveExistingOrder(): void
    {
        $previous = [['attribute_alias' => 'FIRST'], ['attribute_alias' => 'REMOVED'], ['attribute_alias' => 'LAST']];
        $current = [['name' => 'LAST'], ['name' => 'NEW'], ['name' => 'FIRST']];
        $row = ['NEW' => null, 'LAST' => 2, 'FIRST' => 1, 'EXTRA' => 'value'];

        $result = $this->reorder($previous, $current, $row);

        $this->assertSame(['FIRST', 'LAST', 'NEW'], array_column($result['columns'], 'name'));
        $this->assertSame(['FIRST', 'LAST', 'NEW', 'EXTRA'], array_keys($result['rows'][0]));
        $this->assertEquals($row, $result['rows'][0]);
    }

    /**
     * Exercises the real protected helper with disposable files without starting the installer lifecycle.
     * 
     * @param array[] $previousColumns
     * @param array[] $currentColumns
     * @param array $row
     * @return array
     */
    private function reorder(array $previousColumns, array $currentColumns, array $row): array
    {
        $file = tmpfile();
        $this->assertIsResource($file);
        try {
            fwrite($file, json_encode(['columns' => $previousColumns], JSON_THROW_ON_ERROR));
            $installer = (new ReflectionClass(DataInstaller::class))->newInstanceWithoutConstructor();
            $method = new ReflectionMethod(DataInstaller::class, 'reorderColumnsLikePreviousFile');
            $method->setAccessible(true);
            $uxon = new UxonObject(['columns' => $currentColumns, 'rows' => [$row]]);

            return $method->invoke($installer, $uxon, stream_get_meta_data($file)['uri'])->toArray();
        } finally {
            fclose($file);
        }
    }
}