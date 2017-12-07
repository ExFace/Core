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
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\TextDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Factories\DataTypeFactory;

abstract class AbstractSqlModelBuilder extends AbstractModelBuilder implements ModelBuilderInterface
{

    private $data_connector = null;

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
            if (count($meta_object->findAttributesByDataAddress($row['DATA_ADDRESS'])) === 0) {
                $result_data_sheet->addRow($row);
            }
        }
        $result_data_sheet->setCounterRowsAll(count($imported_rows));
        
        if (! $result_data_sheet->isEmpty()) {
            $result_data_sheet->dataCreate();
        }
        
        return $result_data_sheet;
    }
    
    protected abstract function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, $table_name);
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder::guessDataType()
     */
    protected function guessDataType(Workbench $workbench, $sql_data_type, $length = null, $scale = null)
    {
        switch (strtoupper($sql_data_type)) {
            case 'NUMBER':
            case 'BIGINT':
            case 'INT':
            case 'INTEGER':
            case 'DECIMAL':
            case 'FLOAT':
                $data_type = DataTypeFactory::createFromAlias($workbench, 'exface.Core.Number');
                break;
            case 'TIMESTAMP':
            case 'DATETIME':
                $data_type = DataTypeFactory::createFromAlias($workbench, 'exface.Core.Timestamp');
                break;
            case 'DATE':
                $data_type = DataTypeFactory::createFromAlias($workbench, 'exface.Core.Date');
                break;
            case 'TEXT':
            case 'LONGTEXT':
                $data_type = DataTypeFactory::createFromAlias($workbench, 'exface.Core.Text');
                break;
            default:
                $data_type = DataTypeFactory::createFromAlias($workbench, 'exface.Core.String');
        }
        return $data_type;
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