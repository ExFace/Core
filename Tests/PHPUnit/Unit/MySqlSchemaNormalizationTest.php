<?php
namespace exface\Core\Tests\PHPUnit\Unit;

use exface\Core\CommonLogic\AppInstallers\MySqlDatabaseInstaller;
use exface\Core\CommonLogic\AppInstallers\SqlSchemaComparator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Protects MySQL schema normalization without hiding meaningful structural differences.
 */
class MySqlSchemaNormalizationTest extends TestCase
{
    /**
     * Equivalent metadata produces identical canonical column artifacts.
     * 
     * @return void
     */
    public function testEquivalentColumnSpellings() : void
    {
        $pairs = [
            ['int', 'int(11)', 'int'],
            ['int(10) unsigned', 'int(11) unsigned', 'int unsigned'],
            ['tinyint', 'tinyint(4)', 'tinyint'],
            ['smallint', 'smallint(6)', 'smallint'],
            ['mediumint', 'mediumint(9)', 'mediumint'],
            ['bigint', 'bigint(20)', 'bigint'],
        ];
        foreach ($pairs as [$left, $right, $expected]) {
            $leftColumn = $this->column(['column_type' => $left]);
            $rightColumn = $this->column(['column_type' => $right]);
            $this->assertSame('"value" ' . $expected, $leftColumn);
            $this->assertSame($leftColumn, $rightColumn);
            $this->assertSame([], (new SqlSchemaComparator())->compare($this->schema($leftColumn), $this->schema($rightColumn)));
        }
        $this->assertSame($this->column(['column_type' => 'text']), $this->column([
            'column_type' => 'text', 'column_default' => 'NULL', 'server_version' => '10.11.6-MariaDB',
        ]));
        $this->assertSame('"value" enum(\'Open\',\'CLOSED\')', $this->column(['column_type' => "enum('Open','CLOSED')"]));
    }

    /**
     * Width-sensitive ZEROFILL, literal values and structural changes remain visible.
     * 
     * @return void
     */
    public function testMeaningfulDifferencesRemainVisible() : void
    {
        $pairs = [
            [['column_type' => 'int'], ['column_type' => 'tinyint']],
            [['column_type' => 'int'], ['column_type' => 'int unsigned']],
            [['column_type' => 'int(10) zerofill'], ['column_type' => 'int(11) zerofill']],
            [[], ['is_nullable' => 'NO']],
            [['column_type' => 'varchar(20)'], ['column_type' => 'varchar(21)']],
            [['column_type' => 'decimal(5,1)'], ['column_type' => 'decimal(5,2)']],
            [[], ['extra' => 'auto_increment']],
            [[], ['column_default' => '']],
            [[], ['column_default' => 'NULL']],
            [['column_default' => 'CURRENT_TIMESTAMP'], ['column_default' => 'current_timestamp()']],
            [['column_default' => 'int(11) DEFAULT NULL'], ['column_default' => 'int DEFAULT NULL']],
            [['column_default' => "a'CURRENT_TIMESTAMP"], ['column_default' => "a'current_timestamp()"]],
            [
                ['column_type' => 'datetime', 'column_default' => 'CURRENT_TIMESTAMP'],
                ['column_type' => 'datetime', 'column_default' => '1970-01-01 00:00:00'],
            ],
            [
                ['column_type' => 'timestamp', 'column_default' => 'CURRENT_TIMESTAMP'],
                ['column_type' => 'timestamp', 'column_default' => 'CURRENT_TIMESTAMP(6)'],
            ],
            [['column_type' => 'timestamp'], ['column_type' => 'timestamp', 'column_default' => 'CURRENT_TIMESTAMP']],
        ];
        foreach ($pairs as [$left, $right]) {
            $leftSchema = $this->schema($this->column($left));
            $rightSchema = $this->schema($this->column($right));
            $this->assertNotSame([], (new SqlSchemaComparator())->compare($leftSchema, $rightSchema), $leftSchema . $rightSchema);
        }
    }

    /**
     * Table, column, index and foreign-key changes must not be suppressed.
     * 
     * @return void
     */
    public function testStructuralDifferencesRemainVisible() : void
    {
        $schema = $this->schema($this->column(['column_type' => 'int']));
        $variants = [
            str_replace('"items"', '"other"', $schema),
            str_replace('"value"', '"other"', $schema),
            $schema . 'CREATE INDEX ON "items" ("value");',
            $schema . 'ALTER TABLE "items" ADD FOREIGN KEY ("value") REFERENCES "parents" ("id");',
        ];
        foreach ($variants as $variant) {
            $this->assertNotSame([], (new SqlSchemaComparator())->compare($schema, $variant));
        }
    }

    /**
     * The installer compares artifacts literally, without rewriting older exports.
     * 
     * @return void
     */
    public function testInstallerUsesSharedComparison() : void
    {
        $installer = (new ReflectionClass(MySqlDatabaseInstaller::class))->newInstanceWithoutConstructor();
        $left = $this->schema('"value" int');
        $right = $this->schema('"value" int(11) DEFAULT NULL');
        $expected = (new SqlSchemaComparator())->compare($left, $right);
        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $installer->performComparison($left, $right));
    }

    /**
     * Default metadata is rendered without losing literals or quoting expressions.
     * 
     * @return void
     */
    public function testDefaultRenderingAcrossServers() : void
    {
        $cases = [
            ['varchar(40)', '', '', '8.0.36', " DEFAULT ''"],
            ['varchar(40)', 'NULL', '', '8.0.36', " DEFAULT 'NULL'"],
            ['varchar(40)', 'CURRENT_TIMESTAMP', '', '8.0.36', " DEFAULT 'CURRENT_TIMESTAMP'"],
            ['varchar(40)', "O'Reilly", '', '8.0.36', " DEFAULT 'O''Reilly'"],
            ['varchar(40)', "'quoted'", '', '8.0.36', " DEFAULT '''quoted'''"],
            ['varchar(40)', 'a\\b', '', '8.0.36', " DEFAULT 'a\\\\b'"],
            ['varchar(40)', null, '', '8.0.36', ''],
            ['datetime', '1970-01-01 00:00:00', '', '8.0.36', " DEFAULT '1970-01-01 00:00:00'"],
            ['datetime', 'CURRENT_TIMESTAMP', 'DEFAULT_GENERATED', '8.0.36', ' DEFAULT CURRENT_TIMESTAMP'],
            ['datetime', 'current_timestamp(6)', '', '5.7.44', ' DEFAULT CURRENT_TIMESTAMP(6)'],
            ['datetime', 'current_timestamp()', '', '5.7.44', ' DEFAULT CURRENT_TIMESTAMP'],
            ['datetime', 'current_timestamp(0)', '', '5.7.44', ' DEFAULT CURRENT_TIMESTAMP'],
            ['int', '0', '', '8.0.36', ' DEFAULT 0'],
            ['varchar(40)', '123', '', '8.0.36', " DEFAULT '123'"],
            ['int', '(1 + 2)', 'DEFAULT_GENERATED', '8.0.36', ' DEFAULT (1 + 2)'],
            ['varchar(40)', "''", '', '10.11.6-MariaDB', " DEFAULT ''"],
            ['varchar(40)', "'NULL'", '', '10.11.6-MariaDB', " DEFAULT 'NULL'"],
            ['varchar(40)', 'NULL', '', '10.11.6-MariaDB', ''],
            ['datetime', "'1970-01-01 00:00:00'", '', '10.11.6-MariaDB', " DEFAULT '1970-01-01 00:00:00'"],
            ['datetime', 'current_timestamp()', '', '10.11.6-MariaDB', ' DEFAULT CURRENT_TIMESTAMP'],
            ['datetime', 'current_timestamp(0)', '', '10.11.6-MariaDB', ' DEFAULT CURRENT_TIMESTAMP'],
            ['datetime', 'current_timestamp(6)', '', '10.11.6-MariaDB', ' DEFAULT CURRENT_TIMESTAMP(6)'],
        ];
        $installer = (new ReflectionClass(MySqlDatabaseInstaller::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(MySqlDatabaseInstaller::class, 'buildColumnDefinition');
        foreach ($cases as [$type, $default, $extra, $version, $expected]) {
            $actual = $method->invoke($installer, [
                'column_name' => 'value', 'column_type' => $type, 'is_nullable' => 'YES',
                'column_default' => $default, 'extra' => $extra, 'server_version' => $version,
            ]);
            $this->assertSame('"value" ' . $type . $expected, $actual, $version . ': ' . var_export($default, true));
        }
    }

    /**
     * Renders real column metadata without starting or mocking a Workbench.
     * 
     * @param array $overrides
     * @return string
     */
    private function column(array $overrides) : string
    {
        $installer = (new ReflectionClass(MySqlDatabaseInstaller::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(MySqlDatabaseInstaller::class, 'buildColumnDefinition');
        return $method->invoke($installer, array_replace([
            'column_name' => 'value', 'column_type' => 'varchar(40)', 'is_nullable' => 'YES',
            'column_default' => null, 'extra' => '', 'server_version' => '8.0.36',
        ], $overrides));
    }

    /**
     * Wraps one column in the deterministic dump format used by the installer.
     * 
     * @param string $definition
     * @return string
     */
    private function schema(string $definition) : string
    {
        return "CREATE TABLE \"items\" (\n    " . $definition . "\n);\n";
    }
}