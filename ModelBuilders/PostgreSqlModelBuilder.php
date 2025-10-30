<?php
namespace exface\Core\ModelBuilders;

use exface\Core\DataConnectors\PostgreSqlConnector;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\DateTimeDataType;
use exface\Core\DataTypes\HexadecimalNumberDataType;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\TextDataType;
use exface\Core\DataTypes\TimeDataType;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\BinaryDataType;

/**
 * Generates metamodel from PostgreSQL tables and views
 * 
 * @method PostgreSqlConnector getDataConnection()
 * 
 * @author Andrej Kabachnik
 *
 */
class PostgreSqlModelBuilder extends AbstractSqlModelBuilder
{

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::getAttributeDataFromTableColumns()
     */

    public function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $address) : array
    {
        $parts = explode('.', $address);
        if (count($parts) === 2) {
            $filter = "table_schema = '{$parts[0]}' and table_name = '{$parts[1]}'";
        } else {
            $filter = "table_name = '{$address}'";
        }
        
        $columns_sql = "
            SELECT column_name, data_type, is_nullable, column_default, character_maximum_length, numeric_scale
            FROM information_schema.columns
            WHERE $filter
        ";

        $columns_array = $meta_object->getDataConnection()->runSql($columns_sql)->getResultArray();
        $rows = array();
        foreach ($columns_array as $col) {
            $dataType = $this->guessDataType($meta_object, $col['data_type']);
            $row = [
                'NAME' => $this->generateLabel($col['column_name'], ''),
                'ALIAS' => $col['column_name'],
                'DATATYPE' => $this->getDataTypeId($dataType),
                'DATA_ADDRESS' => $col['column_name'],
                'OBJECT' => $meta_object->getId(),
                'REQUIREDFLAG' => ($col['is_nullable'] === 'NO' && strpos($col['column_default'], 'nextval') === false ? 1 : 0),
                'SHORT_DESCRIPTION' => '',
                'UIDFLAG' => strpos($col['column_default'], 'nextval') !== false ? 1 : 0
            ];

            if (($def = $col['column_default']) !== null) {
                if ($row['REQUIREDFLAG'] === 1) {
                    $row['REQUIREDFLAG'] = 0;
                }

                switch (true) {
                    case strpos($def, 'now()') !== false:
                        $row['DEFAULT_VALUE'] = '=Now()';
                        break;
                    case $def !== '':
                        $row['DEFAULT_VALUE'] = is_numeric($def) ? $def : "'$def'";
                        break;
                }
            }

            $addrProps = new UxonObject();
            if (stripos($col['data_type'], 'bytea') !== false) {
                $addrProps->setProperty('SQL_DATA_TYPE', 'binary');
            }
            if ($addrProps->isEmpty() === false) {
                $row['DATA_ADDRESS_PROPS'] = $addrProps->toJson();
            }

            $dataTypeProps = $this->getDataTypeConfig($dataType, $col['data_type']);
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
     * @see \exface\Core\ModelBuilders\AbstractSqlModelBuilder::findObjectTables()
     */
    protected function findObjectTables(string $mask = null) : array
    {
        $filter = "";
        if ($mask) {
            $mask = mb_strtolower($mask);
            $parts = explode('.', $mask);
            if (count($parts) === 2) {
                $filter = "tablename ILIKE '" . $parts[0] . "'";
                if ($parts[1] !== null && $parts[1] !== '' && $parts[1] !== '%') {
                    $filter .= " AND tablename ILIKE '" . $parts[1] . "'";
                }
            } else {
                $filter = "tablename ILIKE '{$mask}'";
            }
        }
        if ($filter) {
            $filter = ' WHERE ' . $filter;
        }

        $sql = "SELECT tablename as \"ALIAS\", tablename as \"NAME\", CONCAT(schemaname, '.', tablename) as \"DATA_ADDRESS\", '' as \"SHORT_DESCRIPTION\" FROM pg_catalog.pg_tables {$filter}";
        $rows = $this->getDataConnection()->runSql($sql)->getResultArray();
        foreach ($rows as $nr => $row) {
            if (substr($row['ALIAS'], 0, 1) === '_') {
                $rows[$nr]['ALIAS'] = ltrim($row['ALIAS'], '_');
            }

            $rows[$nr]['NAME'] = $this->generateLabel($row['NAME'], $row['SHORT_DESCRIPTION']);
        }

        return $rows;
    }

    protected function guessDataType(MetaObjectInterface $object, string $sql_data_type, $length = null, $scale = null) : DataTypeInterface
    {
        $workbench = $object->getWorkbench();

        /*
        $type = trim(StringDataType::substringBefore($sql_data_type, '(', $sql_data_type));
        if ($type !== $sql_data_type) {
            $details = explode(',', substr($sql_data_type, (strlen($type)+1), -1));
            $length = $length ?? trim($details[0]);
            if ($scale === null && null !== ($scale = $details[1] ?? null)) {
                if (is_string($scale)) {
                    $scale = trim($scale);
                }
            }
        }*/
        
        $sqlType = mb_strtoupper($sql_data_type);
        switch (true) {
            case $sqlType === 'BIT' && $length === 1:
            case $sqlType === 'BOOLEAN':
                $data_type = DataTypeFactory::createFromString($workbench, BooleanDataType::class);
                break;
            case $sqlType === 'INT':
            case $sqlType === 'INT2':
            case $sqlType === 'INT4':
            case $sqlType === 'INTEGER':
            case $sqlType === 'SMALLINT':
            case $sqlType === 'BIGINT':
            case $sqlType === 'SERIAL':
            case $sqlType === 'SMALLSERIAL':
            case $sqlType === 'BIGSERIAL':
                if ($length === 1) {
                    $data_type = DataTypeFactory::createFromString($workbench, BooleanDataType::class);
                } else {
                    $data_type = DataTypeFactory::createFromString($workbench, IntegerDataType::class);
                }
                break;
            case $sqlType === 'NUMERIC':
            case $sqlType === 'DECIMAL':
            case $sqlType === 'REAL':
            case stripos($sqlType, 'DOUBLE') !== false:
            case stripos($sqlType, 'FLOAT') !== false:
                if (is_numeric($scale) === true && $scale == 0) {
                    $data_type = DataTypeFactory::createFromString($workbench, IntegerDataType::class);
                } else {
                    $data_type = DataTypeFactory::createFromString($workbench, NumberDataType::class);
                    if ($scale !== null) {
                        $data_type->setPrecisionMax($scale);
                    }
                }
                break;
            case $sqlType === 'TIME':
            case $sqlType === 'TIMEZ':
                $data_type = DataTypeFactory::createFromString($workbench, TimeDataType::class);
                break;
            case $sqlType === 'TIMESTAMP':
            case $sqlType === 'TIMESTAMPZ':
                $data_type = DataTypeFactory::createFromString($workbench, DateTimeDataType::class);
                break;
            case $sqlType === 'DATE':
                $data_type = DataTypeFactory::createFromString($workbench, DateDataType::class);
                break;
            case stripos($sqlType, 'TEXT') !== false:
                $data_type = DataTypeFactory::createFromString($workbench, TextDataType::class);
                if ($length !== null && $length > 0) {
                    $data_type->setLengthMax($length);
                }
                break;
            case $sqlType === 'UUID':
                $data_type = DataTypeFactory::createFromString($workbench, HexadecimalNumberDataType::class);
                break;
            case $sqlType === 'BYTEA':
                $data_type = DataTypeFactory::createFromString($workbench, BinaryDataType::class);
                $data_type->setEncoding(BinaryDataType::ENCODING_HEX);
                break;
            case stripos($sqlType, 'CHAR') !== false:
                $data_type = DataTypeFactory::createFromString($workbench, StringDataType::class);
                if ($length !== null && $length > 0) {
                    $data_type->setLengthMax($length);
                }
                break;
            default:
                $data_type = parent::guessDataType($object, $sql_data_type, $length, $scale);
        }
        return $data_type;
    }
}