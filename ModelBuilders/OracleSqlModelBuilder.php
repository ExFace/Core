<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataConnectors\OracleSqlConnector;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;

/**
 * 
 * @method OracleSqlConnector getDataConnection()
 * 
 * @author Andrej Kabachnik
 *
 */
class OracleSqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */
    protected function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name) : array
    {
        $columns_sql = "
					SELECT
						tc.column_name,
						tc.nullable,
						tc.data_type,
						tc.data_precision,
						tc.data_scale,
						tc.data_length,
						cc.comments
					FROM user_col_comments cc
						JOIN user_tab_columns tc ON cc.column_name = tc.column_name AND cc.table_name = tc.table_name
					WHERE UPPER(cc.table_name) = UPPER('" . $table_name . "')
				";
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $rows[] = array(
                'NAME' => $this->generateLabel($col['COLUMN_NAME'], $col['COMMENTS']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object, $col['DATA_TYPE'], ($col['DATA_PRECISION'] ? $col['DATA_PRECISION'] : $col['DATA_LENGTH']), $col['DATA_SCALE'])),
                'DATA_ADDRESS' => $col['COLUMN_NAME'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['NULLABLE'] == 'N' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['COMMENTS'] ? $col['COMMENTS'] : '')
            );
        }
        return $rows;
    }
    
    
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findObjectTables()
     */
    protected function findObjectTables(string $mask = null) : array
    {
        if ($mask) {
            $filter = "AND table_name LIKE '{$mask}'";
        }
        $owner = mb_strtolower($this->getDataConnection()->getUser());
        
        $sql = "SELECT table_name AS NAME, table_name AS DATA_ADDRESS, table_name AS ALIAS FROM all_tables WHERE LOWER(OWNER)='{$owner}' {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            $rows[$nr]['NAME'] = $this->generateLabel($row['NAME']);
        }
        return $rows;
    }

    /**
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findForeignKeyRelations()
     */
    protected function findForeignKeyRelations(string $table, SqlDataConnectorInterface $connector) : array
    {
        $owner = static::getSchemaFromAlias($table);
        $tableName = static::getTableNameFromAlias($table);
        $sql = "SELECT
                    source_column.COLUMN_NAME,
                    relation.OWNER AS TABLE_SCHEMA,
                    target_column.OWNER AS REFERENCED_TABLE_SCHEMA,
                    target_column.TABLE_NAME AS REFERENCED_TABLE_NAME,
                    target_column.COLUMN_NAME AS REFERENCED_COLUMN_NAME
                FROM ALL_CONSTRAINTS relation
                JOIN ALL_CONS_COLUMNS source_column
                    ON source_column.OWNER = relation.OWNER
                    AND source_column.CONSTRAINT_NAME = relation.CONSTRAINT_NAME
                JOIN ALL_CONS_COLUMNS target_column
                    ON target_column.OWNER = relation.R_OWNER
                    AND target_column.CONSTRAINT_NAME = relation.R_CONSTRAINT_NAME
                    AND target_column.POSITION = source_column.POSITION
                WHERE relation.CONSTRAINT_TYPE = 'R'
                AND relation.OWNER = " . ($owner ? 'UPPER(' . static::quoteSqlLiteral($owner) . ')' : 'USER') . "
                AND relation.TABLE_NAME = UPPER(" . static::quoteSqlLiteral($tableName) . ")
                ORDER BY source_column.POSITION";

        $relations = [];
        foreach ($connector->runSql($sql)->getResultArray() as $row) {
            $relatedTable = $row['REFERENCED_TABLE_NAME'];
            if (strcasecmp($row['REFERENCED_TABLE_SCHEMA'], $row['TABLE_SCHEMA']) !== 0) {
                $relatedTable = $row['REFERENCED_TABLE_SCHEMA'] . '.' . $relatedTable;
            }
            $relations[] = [
                'column' => $row['COLUMN_NAME'],
                'table' => $relatedTable,
                'key' => $row['REFERENCED_COLUMN_NAME']
            ];
        }
        return $relations;
    }
}
?>