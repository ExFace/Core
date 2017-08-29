<?php
namespace exface\Core\CommonLogic\Modelizers;

use exface\Core\CommonLogic\Model\Object;

class SapHanaSqlModelizer extends AbstractSqlModelizer
{

    public function getAttributePropertiesFromTable(Object $meta_object, $table_name)
    {
        $columns_sql = "SELECT * FROM TABLE_COLUMNS WHERE SCHEMA_NAME = '" . static::getSchemaFromAlias($table_name) . "' AND TABLE_NAME = '" . static::getTableNameFromAlias($table_name) . "' ORDER BY POSITION";
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $rows[] = array(
                'LABEL' => $this->generateLabel($col['COLUMN_NAME']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->getDataType($col['DATA_TYPE_NAME'])),
                'DATA_ADDRESS' => $col['COLUMN_NAME'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['IS_NULLABLE'] == 'FALSE' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['COMMENTS'] ? $col['COMMENTS'] : '')
            );
        }
        return $rows;
    }
}
?>