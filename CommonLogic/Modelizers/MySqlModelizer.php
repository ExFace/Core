<?php
namespace exface\Core\CommonLogic\Modelizers;

use exface\Core\CommonLogic\Model\Object;

class MySqlModelizer extends AbstractSqlModelizer
{

    public function getAttributePropertiesFromTable(Object $meta_object, $table_name)
    {
        $columns_sql = "
					SHOW FULL COLUMNS FROM " . $table_name . "
				";
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $rows[] = array(
                'LABEL' => $this->generateLabel($col['Field']),
                'ALIAS' => $col['Field'],
                'DATATYPE' => $this->getDataTypeId($this->getDataType($col['Type'])),
                'DATA_ADDRESS' => $col['Field'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['Null'] == 'NO' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['Comment'] ? $col['Comment'] : '')
            );
        }
        return $rows;
    }

    public function getDataType($data_type, $length = null, $number_scale = null)
    {
        $data_type = trim($data_type);
        $details = array();
        $type = substr($data_type, strpos($data_type, '('));
        if (strpos($data_type, '(') !== false) {
            $details = explode(',', substr($data_type, (strpos($data_type, '(')) + 1, (strlen($data_type) - strrpos($data_type, ')'))));
        }
        
        return parent::getDataType($type, $details[0], $details[1]);
    }
}
?>