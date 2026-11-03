<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\Core\CommonLogic\DataQueries\SqlDataQuery;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Protects schema metadata reads from MySQL's different multi-statement result shape.
 */
class MySqlDatabaseInstallerTest extends TestCase
{
    /**
     * All metadata rows must survive export, including composite keys and indexes.
     * 
     * @return void
     */
    public function testSchemaPreservesMultipleMetadataRows(): void
    {
        $schema = $this->buildTableSchema([
            [['x' => 1]],
            [
                ['column_name' => 'id', 'column_type' => 'int', 'is_nullable' => 'NO', 'column_default' => null, 'extra' => ''],
                ['column_name' => 'tenant_id', 'column_type' => 'int', 'is_nullable' => 'NO', 'column_default' => null, 'extra' => '']
            ],
            [['column_name' => 'tenant_id'], ['column_name' => 'id']],
            [
                ['cname' => 'parent_fk', 'column_from' => 'tenant_id', 'ref_table' => 'parents', 'column_to' => 'tenant_id', 'on_delete' => 'CASCADE', 'on_update' => 'RESTRICT'],
                ['cname' => 'parent_fk', 'column_from' => 'id', 'ref_table' => 'parents', 'column_to' => 'id', 'on_delete' => 'CASCADE', 'on_update' => 'RESTRICT']
            ],
            [
                ['idxname' => 'lookup', 'column_name' => 'tenant_id', 'non_unique' => 1],
                ['idxname' => 'lookup', 'column_name' => 'id', 'non_unique' => 1]
            ]
        ]);

        $expected = [
            'CREATE TABLE "items" (',
            '    "id" int NOT NULL,',
            '    "tenant_id" int NOT NULL,',
            '    PRIMARY KEY ("tenant_id", "id")',
            ');',
            'ALTER TABLE "items" ADD FOREIGN KEY ("tenant_id", "id") REFERENCES "parents" ("tenant_id", "id") ON DELETE CASCADE;',
            'CREATE INDEX ON "items" ("tenant_id", "id");',
            '',
            ''
        ];
        $this->assertSame(implode(PHP_EOL, $expected), $schema);
    }

    /**
     * Tables without primary keys, foreign keys or indexes must still export their columns.
     * 
     * @return void
     */
    public function testSchemaSupportsTablesWithoutKeys(): void
    {
        $schema = $this->buildTableSchema([
            [['x' => 1]],
            [['column_name' => 'value', 'column_type' => 'int', 'is_nullable' => 'YES', 'column_default' => null, 'extra' => '']],
            [],
            [],
            []
        ]);

        $this->assertSame(implode(PHP_EOL, ['CREATE TABLE "items" (', '    "value" int', ');', '', '']), $schema);
    }

    /**
     * Missing base tables must not trigger further metadata reads.
     * 
     * @return void
     */
    public function testMissingTableProducesNoSchema(): void
    {
        $this->assertSame('', $this->buildTableSchema([[]]));
    }

    /**
     * Exercises the real table renderer with a narrow SQL boundary instead of a Workbench mock.
     * 
     * @param array[] $resultSets Ordered metadata result sets.
     * @return string
     */
    private function buildTableSchema(array $resultSets): string
    {
        $connection = $this->createMock(SqlDataConnectorInterface::class);
        $connection->method('escapeString')->willReturnArgument(0);
        $connection->expects($this->exactly(count($resultSets)))
            ->method('runSql')
            ->with($this->isType('string'), false)
            ->willReturnCallback(function (string $sql, ?bool $multiquery) use (&$resultSets): SqlDataQuery {
                return (new SqlDataQuery())->setSql($sql)->setResultArray(array_shift($resultSets));
            });

        $installer = $this->getMockBuilder(MySqlDatabaseInstaller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDataConnection'])
            ->getMock();
        $installer->method('getDataConnection')->willReturn($connection);

        $method = new ReflectionMethod(MySqlDatabaseInstaller::class, 'buildSqlSchemaForTable');
        return $method->invoke($installer, 'test_schema.items');
    }
}