<?php
namespace exface\Core\CommonLogic\Modelizers;

use exface\Core\CommonLogic\Model\Object;

class MSSqlModelizer extends AbstractSqlModelizer
{

    public function getAttributePropertiesFromTable(Object $meta_object, $table_name)
    {
        $table_name_parts = explode('.', $table_name);
        if (count($table_name_parts) == 2) {
            $columns_sql = "
					exec sp_columns '" . $table_name_parts[1] . "', '" . $table_name_parts[0] . "'
				";
        } else {
            $columns_sql = "
					exec sp_columns '" . $table_name . "'
				";
        }
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $rows[] = array(
                'LABEL' => $this->generateLabel($col['COLUMN_NAME']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->getDataType($col['TYPE_NAME'], $col['PRECISION'], $col['SCALE'])),
                'DATA_ADDRESS' => $col['COLUMN_NAME'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['NULLABLE'] == 0 ? 1 : 0),
                'DEFAULT_VALUE' => (! is_null($col['COLUMN_DEF']) ? $col['COLUMN_DEF'] : '')
            );
        }
        return $rows;
    }
}
?>