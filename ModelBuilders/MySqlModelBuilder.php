<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\StringDataType;

class MySqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */
    public function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, $table_name)
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
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object->getWorkbench(), $col['Type'])),
                'DATA_ADDRESS' => $col['Field'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['Null'] == 'NO' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['Comment'] ? $col['Comment'] : '')
            );
        }
        return $rows;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::guessDataType()
     */
    protected function guessDataType(Workbench $workbench, $data_type, $length = null, $number_scale = null)
    {
        $data_type = trim($data_type);
        $details = [];
        $type = StringDataType::substringBefore($data_type, '(', $data_type);
        if ($type !== $data_type) {
            $details = explode(',', substr($data_type, (strlen($type)+1), -1));
        }
        
        return parent::guessDataType($workbench, $type, $details[0], $details[1]);
    }
}
?>