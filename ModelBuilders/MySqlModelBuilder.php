<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BinaryDataType;

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
            $dataType = $this->guessDataType($meta_object, $col['Type']);
            $row = [
                'NAME' => $this->generateLabel($col['Field'], $col['Comment']),
                'ALIAS' => $col['Field'],
                'DATATYPE' => $this->getDataTypeId($dataType),
                'DATA_ADDRESS' => $col['Field'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['Null'] === 'NO' && $col['Extra'] !== 'auto_increment' ? 1 : 0),
                'SHORT_DESCRIPTION' => ($col['Comment'] ? $col['Comment'] : ''),
                'UIDFLAG' => $col['Key'] === 'PRI' ? 1 : 0
            ];
            
            if (($def = $col['Default']) !== null) {
                if ($row['REQUIREDFLAG'] === 1) {
                    $row['REQUIREDFLAG'] = 0;
                }
                
                switch (true) {
                    case $def === 'CURRENT_TIMESTAMP':
                        $row['DEFAULT_VALUE'] = '=Now()';
                        break;
                    case $def !== '':
                        $row['DEFAULT_VALUE'] = is_numeric($def) ? $def : "'$def'";
                        break;
                }
            }
            
            $addrProps = new UxonObject();
            if (stripos($col['Type'], 'binary') !== false || stripos($col['Type'], 'blob') !== false) {
               $addrProps->setProperty('SQL_DATA_TYPE', 'binary');
            }
            // Add mor data address properties here, if neccessary
            if ($addrProps->isEmpty() === false) {
                $row['DATA_ADDRESS_PROPS'] = $addrProps->toJson();
            }
            
            $dataTypeProps = $this->getDataTypeConfig($dataType, $col['Type']);
            if (! $dataTypeProps->isEmpty()) {
                $row['CUSTOM_DATA_TYPE'] = $dataTypeProps->toJson();
            }
                
            $rows[] = $row;
        }
        
        return $rows;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::guessDataType()
     */
    protected function guessDataType(MetaObjectInterface $object, string $data_type, $length = null, $number_scale = null) : DataTypeInterface
    {
        $data_type = trim($data_type);
        $details = [];
        $type = trim(StringDataType::substringBefore($data_type, '(', $data_type));
        if ($type !== $data_type) {
            $details = explode(',', substr($data_type, (strlen($type)+1), -1));
        }
        
        switch (mb_strtoupper($type)) {
            case 'TINYINT':
                $type = 'INT';
                break;
        }
        
        return parent::guessDataType($object, $type, trim($details[0]), trim($details[1]));
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
        
        $sql = "SELECT table_name as ALIAS, table_name as NAME, table_name as DATA_ADDRESS, table_comment as SHORT_DESCRIPTION FROM information_schema.tables where table_schema='{$this->getDataConnection()->getDbase()}' {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            // MySQL views have the table_comment "VIEW" by default - ignore it
            if ($row['SHORT_DESCRIPTION'] === 'VIEW') {
                $row['SHORT_DESCRIPTION'] = '';
            }
            
            if (substr($row['ALIAS'], 0, 1) === '_') {
                $rows[$nr]['ALIAS'] = ltrim($row['ALIAS'], '_');
            }
            
            $rows[$nr]['NAME'] = $this->generateLabel($row['NAME'], $row['SHORT_DESCRIPTION']);
        }
        
        return $rows;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getDataTypeConfig()
     */
    protected function getDataTypeConfig(DataTypeInterface $type, string $source_data_type, $length = null, $scale = null) : UxonObject
    {
        $uxon = parent::getDataTypeConfig($type, $source_data_type, $length, $scale);
        
        $source_data_type = strtoupper($source_data_type);
        $srcTypeParts = explode('(', $source_data_type);
        if (count($srcTypeParts) > 1) {
            $source_data_type = $srcTypeParts[0];
            $srcTypeOptions = rtrim($srcTypeParts[1], ")");
        }
        
        $source_data_type = mb_strtoupper($source_data_type);
        switch (true) {
            /* TODO how to give a MAX to a hex number?
            case StringDataType::endsWith($source_data_type, 'BINARY') && $srcTypeOptions:
                $uxon->setProperty('size_max', $srcTypeOptions);
                break;*/
            case StringDataType::endsWith($source_data_type, 'CHAR') && $srcTypeOptions:
                $uxon->setProperty('length_max', $srcTypeOptions);
                break;
            case $source_data_type === 'TINYBLOB':
            case $source_data_type === 'TINYTEXT':
                $uxon->setProperty('length_max', 255);
                break;
            case $source_data_type === 'BLOB':
            case $source_data_type === 'TEXT':
                $uxon->setProperty('length_max', 65535);
                break;
            case $source_data_type === 'MEDIUMBLOB':
            case $source_data_type === 'MEDIUMTEXT':
                $uxon->setProperty('length_max', 16777215);
                break;
            case $source_data_type === 'LONGBLOB':
            case $source_data_type === 'LONGTEXT':
                $uxon->setProperty('length_max', 4294967295);
                break;
        }
        
        // Tell the data type, that the binary data will be encoded as HEX,
        // because that's what the query builder will do by default.
        if ($type instanceof BinaryDataType && StringDataType::endsWith($source_data_type, 'BLOB')) {
            $uxon->setProperty('encoding', BinaryDataType::ENCODING_HEX);
        }
        
        return $uxon;
    }
}
?>