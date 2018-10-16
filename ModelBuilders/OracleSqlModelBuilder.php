<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataConnectors\OracleSqlConnector;

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
                'LABEL' => $this->generateLabel($col['COLUMN_NAME'], $col['COMMENTS']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object->getWorkbench(), $col['DATA_TYPE'], ($col['DATA_PRECISION'] ? $col['DATA_PRECISION'] : $col['DATA_LENGTH']), $col['DATA_SCALE'])),
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
        
        $sql = "SELECT table_name AS LABEL, table_name AS DATA_ADDRESS, table_name AS ALIAS FROM all_tables WHERE LOWER(OWNER)='{$owner}' {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            $rows[$nr]['LABEL'] = $this->generateLabel($row['LABEL']);
        }
        return $rows;
    }
}
?>