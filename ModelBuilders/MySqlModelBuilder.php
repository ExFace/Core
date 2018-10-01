<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataConnectors\MySqlConnector;

/**
 * 
 * @method MySqlConnector getDataConnection()
 * 
 * @author Andrej Kabachnik
 *
 */
class MySqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */
    public function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name) : array
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findTables()
     */
    protected function findObjectTables(string $mask = null) : array
    {
        if ($mask) {
            $filter = "AND table_name LIKE '{$mask}'";
        }
        
        $sql = "SELECT table_name as ALIAS, table_name as LABEL, table_name as DATA_ADDRESS, table_comment as SHORT_DESCRIPTION FROM information_schema.tables where table_schema='{$this->getDataConnection()->getDbase()}' {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            $rows[$nr]['LABEL'] = $this->generateLabel($row['LABEL'], $row['SHORT_DESCRIPTION']);
        }
        return $rows;
    }
}
?>