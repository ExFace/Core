<?php
namespace exface\Core\ModelBuilders;

use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\DataSources\ModelBuilderInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Factories\DataTypeFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\DataConnectors\AbstractSqlConnector;
use exface\Core\DataTypes\IntegerDataType;
use exface\Core\DataTypes\NumberDataType;
use exface\Core\DataTypes\TimestampDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\DataTypes\TextDataType;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\DataTypes\HexadecimalNumberDataType;

/**
 * This is the base for all SQL model builders in the core.
 * 
 * This model builder will generate meta objects (from tables and views), attributes
 * (from columns) and relations (from foreign keys).
 * 
 * In data-source-mode (no existing object specified), a new meta object will be created 
 * for each table or view, that matches the data address mask and is not yet used in a the
 * data address of another object. The data address mask may contain any valid SQL LIKE
 * expression (e.g. `my_prefix%` to match all tables beginning with "my_prefix"). If it
 * is empty, objects will be created for all tables in the database or scheme defined
 * in the data connection.
 * 
 * Similarly, new attributes will be created for every table column, that does not match
 * the data address of an existing attribute of the same object exactly. The column name
 * is then used as data address. This means, a column is never imported a second
 * time automatically. However, if the data address of the attribute is subsequently
 * changed manually (e.g. into an SQL statement instead of a simple column name), the
 * next import will create a new attribute for the table column.
 * 
 * Relations can be automatically created using a regular expression on column names. The
 * regular expression mask must be defined in the custom data connection property 
 * `relation_matcher` and must contain the following named character groups:
 * 
 * - `table` - the name of the target table, the foreign key points to,
 * - `key` - the key column in the target table,
 * - `alias` - the alias to be used for the relation in the metamodel.
 * 
 * A column is concidered a relation (foreign key) if all three values are found. This works 
 * well if foreign keys contain the target table and key in their name, which is quite typical: 
 * e.g. `product_id` for a foreign key, pointing to the `id` column of the `product` table - 
 * the corresponding matcher would be `/(?<alias>(?<table>.*))_(?<key>id)/i`. Concrete
 * SqlModelBuilder implementations for specific databases may include other methods of
 * foreign key detection (e.g. constraints) - please refer to the documentation of the
 * respective model builder.
 * 
 * A relation property `DELETE_WITH_RELATED_OBJECT` is set automatically if the foreign key
 * column is required (not nullable). The property `COPY_WITH_RELATED_OBJECT` is never set.
 * 
 * Comments on tables and columns are automatically imported as short descriptions of the
 * resulting model entities.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractSqlModelBuilder extends AbstractModelBuilder implements ModelBuilderInterface
{

    private $data_connector = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder::generateAttributesForObject()
     */
    public function generateAttributesForObject(MetaObjectInterface $meta_object) : DataSheetInterface
    {
        $this->setModelLanguage($meta_object->getApp()->getLanguageDefault());
        
        $transaction = $meta_object->getWorkbench()->data()->startTransaction();
        
        $newAttributes = $this->generateAttributes($meta_object, $transaction);
        $this->generateRelations($meta_object, $newAttributes, $transaction);
        
        $transaction->commit();
        
        return $newAttributes;
    }
    
    /**
     * Returns an attribute data sheet with attributes created from data addresses currently not in the model.
     * 
     * @param MetaObjectInterface $meta_object
     * @return DataSheetInterface
     */
    protected function generateAttributes(MetaObjectInterface $meta_object, DataTransactionInterface $transaction = null) : DataSheetInterface
    {
        $result_data_sheet = DataSheetFactory::createFromObjectIdOrAlias($meta_object->getWorkbench(), 'exface.Core.ATTRIBUTE');
        
        $imported_rows = $this->getAttributeDataFromTableColumns($meta_object, $meta_object->getDataAddress());
        foreach ($imported_rows as $row) {
            if (empty($meta_object->findAttributesByDataAddress($row['DATA_ADDRESS']))) {
                if ($meta_object->isWritable() === false) {
                    $row['WRITABLEFLAG'] = false;
                    $row['EDITABLEFLAG'] = false;
                }
                $result_data_sheet->addRow($row);
            }
        }
        $result_data_sheet->setCounterForRowsInDataSource(count($imported_rows));
        
        if (! $result_data_sheet->isEmpty()) {
            $result_data_sheet->dataCreate(false, $transaction);
        }
        
        return $result_data_sheet;
    }
    
    /**
     * Returns an array of data rows - each being an array with attribute aliases for keys.
     * 
     * @param MetaObjectInterface $meta_object
     * @param string $table_name
     * 
     * @return array[]
     */
    protected abstract function getAttributeDataFromTableColumns(MetaObjectInterface $meta_object, string $table_name) : array;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder::generateObjectsForDataSource()
     */
    public function generateObjectsForDataSource(AppInterface $app, DataSourceInterface $source, string $data_address_mask = null) : DataSheetInterface
    {
        $this->setModelLanguage($app->getLanguageDefault());
        
        $existing_objects = DataSheetFactory::createFromObjectIdOrAlias($app->getWorkbench(), 'exface.Core.OBJECT');
        $existing_objects->getColumns()->addFromExpression('DATA_ADDRESS');
        $existing_objects->addFilterFromString('APP', $app->getUid(), EXF_COMPARATOR_EQUALS);
        $existing_objects->dataRead();
        
        $newObjectsSheet = DataSheetFactory::createFromObjectIdOrAlias($app->getWorkbench(), 'exface.Core.OBJECT');
        
        $transaction = $app->getWorkbench()->data()->startTransaction();
        
        $imported_rows = $this->getObjectData($app, $source, $data_address_mask)->getRows();
        foreach ($imported_rows as $row) {
            if ($existing_objects->getColumns()->getByExpression('DATA_ADDRESS')->findRowByValue($row['DATA_ADDRESS']) === false) {
                $newObjectsSheet->addRow($row);
            }
        }
        $newObjectsSheet->setCounterForRowsInDataSource(count($imported_rows));
        
        if (! $newObjectsSheet->isEmpty()) {
            $newObjects = [];
            $newObjectsSheet->dataCreate(false, $transaction);
            // Generate attributes for each object
            foreach ($newObjectsSheet->getRows() as $row) {
                $object = $app->getWorkbench()->model()->getObjectByAlias($row['ALIAS'], $app->getAliasWithNamespace());
                $attributes = $this->generateAttributes($object, $transaction);
                $newObjects[] = [$object, $attributes];
            }
            // After all attributes are there, generate relations. It must be done after all new objects have
            // attributes as relations need attribute UIDs on both sides!
            foreach($newObjects as $data) {
                list($object, $attributes) = $data;
                $this->generateRelations($object, $attributes, $transaction);
            }
        }
        
        $transaction->commit();
        
        return $newObjectsSheet;
    }
    
    /**
     * 
     * @param AppInterface $app
     * @param DataSourceInterface $source
     * @param string $data_address_mask
     * @return DataSheetInterface
     */
    protected function getObjectData(AppInterface $app, DataSourceInterface $source, string $data_address_mask = null) : DataSheetInterface
    {
        $sheet = DataSheetFactory::createFromObjectIdOrAlias($app->getWorkbench(), 'exface.Core.OBJECT');
        $ds_uid = $source->getId();
        $app_uid = $app->getUid();
        foreach ($this->findObjectTables($data_address_mask) as $row) {
            $row = array_merge([
                'DATA_SOURCE' => $ds_uid,
                'APP' => $app_uid
            ], $row);
            $sheet->addRow($row);
        }
        return $sheet;
    }
    
    /**
     * 
     * @param SqlDataConnectorInterface $connection
     * @param string $data_address_mask
     * @return array
     */
    abstract protected function findObjectTables(string $data_address_mask = null) : array;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\ModelBuilders\AbstractModelBuilder::guessDataType()
     */
    protected function guessDataType(Workbench $workbench, $sql_data_type, $length = null, $scale = null)
    {
        switch (strtoupper($sql_data_type)) {
            case 'BIGINT':
            case 'INT':
            case 'INTEGER':
                if ($length === 1) {
                    $data_type = DataTypeFactory::createFromSelector($workbench, BooleanDataType::class);
                } else {
                    $data_type = DataTypeFactory::createFromString($workbench, IntegerDataType::class);
                }
                break;
            case 'NUMBER':
            case 'DECIMAL':
            case 'FLOAT':
                if ($scale === 0) {
                    $data_type = DataTypeFactory::createFromString($workbench, IntegerDataType::class);
                } else {
                    $data_type = DataTypeFactory::createFromString($workbench, NumberDataType::class);
                    if ($scale !== null) {
                        $data_type->setPrecision($scale);
                    }
                }
                break;
            case 'TIMESTAMP':
            case 'DATETIME':
                $data_type = DataTypeFactory::createFromString($workbench, TimestampDataType::class);
                break;
            case 'DATE':
                $data_type = DataTypeFactory::createFromString($workbench, DateDataType::class);
                break;
            case 'TEXT':
            case 'LONGTEXT':
                $data_type = DataTypeFactory::createFromString($workbench, TextDataType::class);
                break;
            case 'BINARY':
                $data_type = DataTypeFactory::createFromString($workbench, HexadecimalNumberDataType::class);
                break;
            default:
                $data_type = DataTypeFactory::createFromString($workbench, StringDataType::class);
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
        if ($description !== null && $description !== '' && strlen($description) < 50) {
            $column_name = $description;
        } else {
            $column_name = trim($column_name);
            $column_name = str_replace('_', ' ', $column_name);
            $column_name = mb_strtolower($column_name);
            $column_name = ucfirst($column_name);
            $lang = mb_strtolower($this->getModelLanguage());
            if ($lang === 'de') {
                $column_name = str_replace(['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue'], ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü'], $column_name);
            }
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
    
    /**
     * Erstellt eine Relation, wenn die Datenadresse mit _OID endet und der Wert davor exakt der Adresse
     * eine Metaobjekts aus derselben datenquelle entspricht.
     *
     * @param MetaObjectInterface $object
     * @param array $row
     * @return array
     */
    protected function generateRelations(MetaObjectInterface $object, DataSheetInterface $attributeSheet, DataTransactionInterface $transaction = null) : DataSheetInterface
    {
        if (! $attributeSheet->getMetaObject()->is('exface.Core.ATTRIBUTE')) {
            throw new InvalidArgumentException('Invalid data sheet passed to relation finder: expected "exface.Core.ATTRIBUTE", received "' . $attributeSheet->getMetaObject()->getAliasWithNamespace() . '"!');
        }
        
        $found_relations = false;
        foreach ($attributeSheet->getRows() as $row) {
            $address = $row['DATA_ADDRESS'];
            $relation = $this->findRelation($object->getDataAddress(), $address, $object->getDataConnection());
            if (! empty($relation)) {
                $relatedTable = $relation['table'];
                $ds = DataSheetFactory::createFromObjectIdOrAlias($object->getWorkbench(), 'exface.Core.OBJECT');
                $ds->getColumns()->addFromUidAttribute();
                $ds->getColumns()->addFromLabelAttribute();
                $ds->addFilterFromString('DATA_ADDRESS', $relatedTable, EXF_COMPARATOR_EQUALS);
                $ds->addFilterFromString('DATA_SOURCE', $object->getDataSourceId(), EXF_COMPARATOR_EQUALS);
                $ds->dataRead();
                if ($ds->countRows() === 1) {
                    $row['RELATED_OBJ'] = $ds->getUidColumn()->getValues()[0];
                    $row['ALIAS'] = $relation['alias'];
                    // If the attribute's name was autogenerated from it's data address, replace it with the label of
                    // the related object. This is much better in most cases.
                    if ($row['LABEL'] === $this->generateLabel($address)) {
                        $row['LABEL'] = $ds->getColumns()->getByAttribute($ds->getMetaObject()->getLabelAttribute())->getCellValue(0);
                    }
                    // If the foreign key is a required column, it's row must be deleted together with the
                    // row, the foreign key points to (otherwise we will get an orphan or a constraint violation)
                    if ($row['REQUIREDFLAG']) {
                        $row['DELETE_WITH_RELATED_OBJECT'] = 1;
                    }
                    $attributeSheet->addRow($row, true);
                    $found_relations = true;
                }
            }
        }
        
        if ($found_relations === true) {
            $attributeSheet->dataUpdate(false, $transaction);    
        }
        
        return $attributeSheet;
    }
    
    /**
     * Checks, if the given table column is a foreign key: returns an empty array (if not) or the following structure:
     * 
     * [
     *  'table' => the name of the target table, the foreign key points to,
     *  'key' => the key column in the target table,
     *  'alias' => the alias to be used for the relation in the metamodel,
     * ]
     *  
     * By default this method will simply apply the regular expression from the
     * data connection property `relation_matcher` to the table name. Using named
     * groups in this regex, you can fill the above array. This works well if
     * foreign keys contain the target table and key in their name, which is
     * quite typical: e.g. `product_id` for a foreign key, pointing to the `id`
     * column of the `product` table.
     * 
     * If foreign key constraints are used, this method should be overridden or
     * extended to read the foreign keys metadata from the DB. This is why the
     * connector is a mandatory argument.
     * 
     * @param string $table
     * @param string $column
     * @param SqlDataConnectorInterface $connector
     * @return string[]
     */
    protected function findRelation(string $table, string $column, SqlDataConnectorInterface $connector) : array
    {
        if (! ($connector instanceof AbstractSqlConnector)) {
            return [];
        }
        
        $pattern = $connector->getRelationMatcher();
        
        if (! $pattern) {
            return [];
        }
        
        $matches = [];
        if (preg_match_all($pattern, $column, $matches)) {
            return [
                'table' => $matches['table'][0],
                'key' => $matches['key'][0],
                'alias' => $matches['alias'][0],
            ];
        }
        return [];
    }
}