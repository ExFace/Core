<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\iRunDataSourceQuery;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ResultFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Interfaces\DataSources\DataSourceInterface;
use exface\Core\Factories\DataSourceFactory;
use exface\Core\Interfaces\DataSources\TextualQueryConnectorInterface;
use exface\Core\Exceptions\Actions\ActionConfigurationError;

/**
 * Runs explicitly specified data source queries with placeholders filled from input data.
 * 
 * Allows to run custom queries on supporting data sourcers like SQL, OLAP, etc. The data
 * connection MUST implement the interface `TextualQueryConnectorInterface` for this to work!
 * 
 * ## Example
 * 
 * ### Run static SQL on the current connection of a data source
 * 
 * ```
 * {
 *  "alias": "exface.Core.CustomDataSourceQuery",
 *  "data_source": "my_sql_source_alias",
 *  "queries": [
 *      "UPDATE table1 WHERE ...",
 *      "UPDATE table2 WHERE ..."
 *  ]
 * }
 * 
 * ```
 * 
 * ### Run SQL with placeholders filled from input data
 * 
 * In this example the placeholder [#ID#] will be replaced by the value in column "ID" of the input data. 
 * Since the queries depend on certain columns in the input data, it is a good idea to restrict the
 * input to a single `input_object_alias`. Also concider adding `input_rows_min` and `input_rows_max`.
 * 
 * NOTE: Multiple rows will trigger each query multiple times!
 * 
 * ```
 * {
 *   "alias": "exface.Core.CustomDataSourceQuery",
 *   "data_source": "",
 *   "input_object_alias": "",
 *   "queries": [
 *     "UPDATE table1 WHERE id = [#ID#]",
 *     "UPDATE table2 WHERE foreign_key = [#ID#]"
 *   ]
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class CustomDataSourceQuery extends AbstractAction implements iRunDataSourceQuery
{
    private $queries = [];

    private $data_connection = null;
    
    private $dataSource = null;

    private $aplicable_to_object_alias = null;

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::init()
     */
    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::COGS);
    }

    /**
     *
     * @return string[]
     */
    public function getQueries() : array
    {
        return $this->queries;
    }

    /**
     * Queries to run in data source language (e.g. SQL)
     * 
     * @uxon-property queries
     * @uxon-type array
     * @uxon-template [""]
     * 
     * @param UxonObject $query_strings
     * @return \exface\Core\Actions\CustomDataSourceQuery
     */
    public function setQueries(UxonObject $query_strings)
    {
        $this->queries = $query_strings->toArray();
        return $this;
    }

    /**
     * 
     * @param string $string
     * @return \exface\Core\Actions\CustomDataSourceQuery
     */
    public function addQuery(string $string) : CustomDataSourceQuery
    {
        $this->queries[] = $string;
        return $this;
    }
    
    /**
     *
     * @return DataSourceInterface
     */
    protected function getDataSource() : DataSourceInterface
    {
        return $this->dataSource ?? $this->getMetaObject()->getDataSource();
    }
    
    /**
     * The data source to perform the queries on (if not set, the source of the input object will be used).
     * 
     * The queries will be performed on the current connection of this data source.
     * If you need to use a specific connection regardless of the data source
     * configuration, use `data_connection` instead.
     * 
     * @uxon-property data_source
     * @uxon-type metamodel:data_source
     * 
     * @param string|DataSourceInterface $dataSourceOrAlias
     * @return CustomDataSourceQuery
     */
    public function setDataSource($dataSourceOrAlias) : CustomDataSourceQuery
    {
        if ($dataSourceOrAlias instanceof DataSourceInterface) {
            $this->dataSource = $dataSourceOrAlias;
        } else {
            $this->dataSource = DataSourceFactory::createFromModel($this->getWorkbench(), $dataSourceOrAlias);
        }
        return $this;
    }
    
    /**
     * 
     * @throws ActionConfigurationError
     * @return TextualQueryConnectorInterface
     */
    protected function getDataConnection() : TextualQueryConnectorInterface
    {
        $conn = $this->data_connection ?? $this->getDataSource()->getConnection();
        if ($conn === null) {
            throw new ActionConfigurationError($this, 'No data source or connection specified for action "' . $this->getAliasWithNamespace() . '"!');
        }
        if (! $conn instanceof TextualQueryConnectorInterface) {
            throw new ActionConfigurationError($this, 'Cannot use connection "' . $conn->getName() . '" (' . $conn->getAlias() . ') in action CustomDataSourceQuery - only connectors implementing the TextualQueryConnectorInterface supported!');
        }
        return $conn;
    }

    /**
     * A specific data connection to run the queries on (if not set, the data_source will be used).
     * 
     * Use only if you really need to always run the queries on this connection. Normally
     * it is better to set a `data_source` instead, so the current connection of that
     * data source is used.
     * 
     * @uxon-property data_connection
     * @uxon-type metamodel:connection
     * 
     * @param string|DataConnectionInterface $connection_or_alias
     * @return \exface\Core\Actions\CustomDataSourceQuery
     */
    public function setDataConnection($connection_or_alias)
    {
        if ($connection_or_alias instanceof DataConnectionInterface) {
            $this->data_connection = $connection_or_alias;
        } else {
            $this->data_connection = DataConnectionFactory::createFromModel($this->getWorkbench(), $connection_or_alias);
        }
        return $this;
    }

    /**
     * @deprecated use setInputObjectAlias() instead.
     * 
     * @param string $value
     * @return \exface\Core\Interfaces\Actions\ActionInterface
     */
    public function setApplicableToObjectAlias($value)
    {
        return $this->setInputObjectAlias($value);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractAction::perform()
     */
    protected function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
    {
        $counter = 0;
        $data_sheet = $this->getInputDataSheet($task);
        
        foreach ($this->getQueries() as $query) {
            $queryRuns = [];
            
            // See if the query has any placeholders
            foreach (StringDataType::findPlaceholders($query) as $ph) {
                /* @var $col \exface\Core\CommonLogic\DataSheets\DataColumn */
                if (! $col = $data_sheet->getColumns()->get(DataColumn::sanitizeColumnName($ph))) {
                    throw new ActionInputMissingError($this, 'Cannot perform custom query in "' . $this->getAliasWithNamespace() . '": placeholder "' . $ph . '" not found in inupt data!', '6T5DNWE');
                }
                // Replace the placeholder for each row and save each resulting query into
                // an array.
                foreach ($col->getValues(false) as $rowNr => $val) {
                    $queryRuns[$rowNr] = StringDataType::replacePlaceholders($queryRuns[$rowNr] ?? $query, [$ph => $val], false);
                }
            }
            
            // If $queryRuns is empty (= no placeholders found), add the entire query
            if (empty($queryRuns) === true) {
                $queryRuns[] = $query;
            }
            
            // Perform the queries and save the total affected rows of the last query in $counter
            $counter = 0;
            foreach ($queryRuns as $queryRun){
                $counter += $this->getDataConnection()->runCustomQuery($queryRun)->countAffectedRows();
            }
        }
        
        // Refresh the data sheet. Make sure to get only those rows present in the original sheet if there are no filters set.
        // This will mainly happen if the sheet was autogenerated from a users selection. If the sheet was meant to contain all
        // elements of the selected source, it will not be extended by any elements added by the performed query however.
        if ($data_sheet->countRows() && $data_sheet->getUidColumn() && $data_sheet->getFilters()->isEmpty()) {
            $data_sheet->getFilters()->addConditionFromColumnValues($data_sheet->getUidColumn());
        }
        $data_sheet->dataRead();
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        $result->setMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CUSTOMDATAQUERY.RESULT', array(
            '%number%' => $counter
        ), $counter));
        
        return $result;
    }
}