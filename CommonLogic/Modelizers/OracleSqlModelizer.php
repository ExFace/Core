<?php
namespace exface\Core\CommonLogic\Modelizers;

use exface\Core\CommonLogic\Model\Object;

class OracleSqlModelizer extends AbstractSqlModelizer
{

    public function getAttributePropertiesFromTable(Object $meta_object, $table_name)
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
                'LABEL' => $this->generateLabel($col['COLUMN_NAME']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->getDataType($col['DATA_TYPE'], ($col['DATA_PRECISION'] ? $col['DATA_PRECISION'] : $col['DATA_LENGTH']), $col['DATA_SCALE'])),
                'DATA_ADDRESS' => $col['COLUMN_NAME'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['NULLABLE'] == 'N' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['COMMENTS'] ? $col['COMMENTS'] : '')
            );
        }
        return $rows;
    }
}
?>