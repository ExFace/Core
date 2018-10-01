<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Exceptions\NotImplementedError;

class SapHanaSqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */
    public function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name) : array
    {
        $columns_sql = "SELECT * FROM TABLE_COLUMNS WHERE SCHEMA_NAME = '" . static::getSchemaFromAlias($table_name) . "' AND TABLE_NAME = '" . static::getTableNameFromAlias($table_name) . "' ORDER BY POSITION";
        
        // TODO check if it is the right data connector
        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $rows[] = array(
                'LABEL' => $this->generateLabel($col['COLUMN_NAME']),
                'ALIAS' => $col['COLUMN_NAME'],
                'DATATYPE' => $this->getDataTypeId($this->guessDataType($meta_object->getWorkbench(), $col['DATA_TYPE_NAME'])),
                'DATA_ADDRESS' => $col['COLUMN_NAME'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['IS_NULLABLE'] == 'FALSE' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['COMMENTS'] ? $col['COMMENTS'] : '')
            );
        }
        return $rows;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findTables()
     */
    protected function findObjectTables(string $mask = null) : array
    {
        throw new NotImplementedError('Generating meta objects from DB schema not yet available: create objects manually and then import attributes.');
    }
}
?>