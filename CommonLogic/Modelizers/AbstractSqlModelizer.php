<?php
namespace exface\Core\CommonLogic\Modelizers;

use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\DataSources\ModelizerInterface;
use exface\Core\Factories\DataSheetFactory;

abstract class AbstractSqlModelizer implements ModelizerInterface
{

    private $data_connector = null;

    private $data_types = NULL;

    public function __construct(SqlDataConnectorInterface $data_connector)
    {
        $this->data_connector = $data_connector;
    }

    abstract public function getAttributePropertiesFromTable(Object $meta_object, $table_name);

    /**
     *
     * @param string $sql_data_type            
     * @param integer $length
     *            total number of digits/characters
     * @param integer $number_scale
     *            number of digits to the right of the decimal point
     */
    public function getDataType($sql_data_type, $length = null, $number_scale = null)
    {
        $data_type_alias = '';
        switch (strtoupper($sql_data_type)) {
            case 'NUMBER':
            case 'BIGINT':
            case 'INT':
            case 'INTEGER':
            case 'DECIMAL':
            case 'FLOAT':
                $data_type_alias = 'Number';
                break;
            case 'TIMESTAMP':
            case 'DATETIME':
                $data_type_alias = 'Timestamp';
                break;
            case 'DATE':
                $data_type_alias = 'Date';
                break;
            case 'TEXT':
            case 'LONGTEXT':
                $data_type_alias = 'Text';
                break;
            default:
                $data_type_alias = 'String';
        }
        return $data_type_alias;
    }

    /**
     * Prettifies the column name to be used as the attribute's label
     *
     * @param string $column_name            
     * @return string
     */
    public function generateLabel($column_name)
    {
        $column_name = trim($column_name);
        $column_name = str_replace('_', ' ', $column_name);
        $column_name = strtolower($column_name);
        $column_name = ucfirst($column_name);
        return $column_name;
    }

    public function getDataTypeId($data_type_alias)
    {
        if (! $this->data_types) {
            $this->data_types = DataSheetFactory::createFromObject($this->getDataConnection()->getWorkbench()->model()->getObject('exface.Core.DATATYPE'));
            $this->data_types->getColumns()->addMultiple(array(
                $this->data_types->getMetaObject()->getUidAlias(),
                $this->data_types->getMetaObject()->getLabelAlias()
            ));
            $this->data_types->dataRead(0, 0);
        }
        
        return $this->data_types->getUidColumn()->getCellValue($this->data_types->getColumns()->get('LABEL')->findRowByValue($data_type_alias));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\ModelizerInterface::getDataConnection()
     */
    public function getDataConnection()
    {
        return $this->data_connector;
    }

    /**
     * Extracts the DB schema from a qualified table alias (e.g.
     * SCHEMA.TABLE_NAME). Returns an empty string if no schema is found.
     *
     * @param string $table_alias            
     * @return string
     */
    protected static function getSchemaFromAlias($table_alias)
    {
        $parts = explode('.', $table_alias);
        if (count($parts) > 1) {
            return $parts[0];
        } else {
            return '';
        }
    }

    /**
     * Extracts the table name from a qualified table alias (e.g.
     * SCHEMA.TABLE_NAME). Returns the unchanged alias if no schema is found.
     *
     * @param string $table_alias            
     * @return string
     */
    protected static function getTableNameFromAlias($table_alias)
    {
        $parts = explode('.', $table_alias);
        if (count($parts) > 1) {
            return $parts[1];
        } else {
            return $parts[0];
        }
    }
}
?>