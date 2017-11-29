<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder;

abstract class AbstractSqlModelBuilder extends AbstractModelBuilder implements ModelBuilderInterface
{

    private $data_connector = null;

    private $data_types = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder::generateAttributesForObject()
     */
    public function generateAttributesForObject(MetaObjectInterface $meta_object)
    {
        $result_data_sheet = DataSheetFactory::createFromObjectIdOrAlias($meta_object->getWorkbench(), 'exface.Core.ATTRIBUTE');
        
        $imported_rows = $this->getAttributeDataFromTableColumns($meta_object, $meta_object->getDataAddress());
        foreach ($imported_rows as $row) {
            if ($meta_object->findAttributesByDataAddress($row['DATA_ADDRESS'])) {
                continue;
            }
            $result_data_sheet->addRow($row);
        }
        $result_data_sheet->setCounterRowsAll(count($imported_rows));
        
        if (! $result_data_sheet->isEmpty()) {
            $result_data_sheet->dataCreate();
        }
        
        return $result_data_sheet;
    }
    
    abstract protected function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, $table_name);
    
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
    public function generateLabel($column_name, $description = null)
    {
        if (! is_null($description) && strlen($description) < 50) {
            $column_name = $description;
        } else {
            $column_name = trim($column_name);
            $column_name = str_replace('_', ' ', $column_name);
            $column_name = strtolower($column_name);
            $column_name = ucfirst($column_name);
        }
        return $column_name;
    }

    public function getDataTypeId($data_type_alias)
    {
        if (is_null($this->data_types)) {
            $this->data_types = DataSheetFactory::createFromObject($this->getDataConnection()->getWorkbench()->model()->getObject('exface.Core.DATATYPE'));
            $this->data_types->getColumns()->addMultiple(array(
                $this->data_types->getMetaObject()->getUidAttributeAlias(),
                'ALIAS'
            ));
            $this->data_types->dataRead(0, 0);
        }
        
        return $this->data_types->getUidColumn()->getCellValue($this->data_types->getColumns()->get('ALIAS')->findRowByValue($data_type_alias));
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