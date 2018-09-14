<?php
namespace exface\Core\Actions;

use exface\Core\CommonLogic\AbstractAction;
use exface\Core\Interfaces\Actions\iRunDataSourceQuery;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\CommonLogic\DataSheets\DataColumn;
use exface\Core\Exceptions\Actions\ActionInputMissingError;
use exface\Core\Exceptions\Actions\ActionInputInvalidObjectError;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Factories\ResultFactory;

class CustomDataSourceQuery extends AbstractAction implements iRunDataSourceQuery
{

    private $queries = array();

    private $data_connection = null;

    private $aplicable_to_object_alias = null;

    protected function init()
    {
        parent::init();
        $this->setIcon(Icons::COGS);
    }

    /**
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    public function setQueries(UxonObject $query_strings)
    {
        $this->queries = $query_strings->toArray();
        return $this;
    }

    public function addQuery($string)
    {
        $this->queries[] = $string;
        return $this;
    }

    public function getDataConnection()
    {
        if (is_null($this->data_connection)) {
            $this->setDataConnection($this->getWidgetDefinedIn()->getMetaObject()->getDataConnection());
        }
        return $this->data_connection;
    }

    public function setDataConnection($connection_or_alias)
    {
        if ($connection_or_alias instanceof DataConnectionInterface) {
            $this->data_connection = $connection_or_alias;
        } else {
            // TODO
        }
        return $this;
    }

    public function getAplicableToObjectAlias()
    {
        return $this->aplicable_to_object_alias;
    }

    /**
     *
     * @return MetaObjectInterface
     */
    public function getAplicableToObject()
    {
        return $this->getWorkbench()->model()->getObject($this->getAplicableToObjectAlias());
    }

    public function setAplicableToObjectAlias($value)
    {
        $this->aplicable_to_object_alias = $value;
        return $this;
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
        // Check if the action is aplicable to the input object
        if ($this->getAplicableToObjectAlias()) {
            if (! $data_sheet->getMetaObject()->is($this->getAplicableToObjectAlias())) {
                throw new ActionInputInvalidObjectError($this, 'Cannot perform action "' . $this->getAliasWithNamespace() . '" on object "' . $data_sheet->getMetaObject()->getAliasWithNamespace() . '": action only aplicable to "' . $this->getAplicableToObjectAlias() . '"!', '6T5DMU');
            }
        }
        
        // Start transaction
        $transaction->addDataConnection($this->getDataConnection());
        
        // Build and perform all queries. Rollback if anything fails
        try {
            foreach ($this->getQueries() as $query) {
                // See if the query has any placeholders
                $placeholders = array();
                foreach (StringDataType::findPlaceholders($query) as $ph) {
                    /* @var $col exface\Core\CommonLogic\DataSheets\DataColumn */
                    if (! $col = $data_sheet->getColumns()->get(DataColumn::sanitizeColumnName($ph))) {
                        throw new ActionInputMissingError($this, 'Cannot perform custom query in "' . $this->getAliasWithNamespace() . '": placeholder "' . $ph . '" not found in inupt data!', '6T5DNWE');
                    }
                    $placeholders['[#' . $ph . '#]'] = implode(',', $col->getValues(false));
                }
                $query = str_replace(array_keys($placeholders), array_values($placeholders), $query);
                
                // Perform the query
                $counter = $this->getDataConnection()->query($query);
            }
            $transaction->commit();
        } catch (\Exception $e) {
            var_dump($query);
            $transaction->rollback();
            $e->rethrow();
        }
        
        // Refresh the data sheet. Make sure to get only those rows present in the original sheet if there are no filters set.
        // This will mainly happen if the sheet was autogenerated from a users selection. If the sheet was meant to contain all
        // elements of the selected source, it will not be extended by any elements added by the performed query however.
        if ($data_sheet->countRows() && $data_sheet->getUidColumn() && $data_sheet->getFilters()->isEmpty()) {
            $data_sheet->addFilterFromColumnValues($data_sheet->getUidColumn());
        }
        $data_sheet->dataRead();
        
        $result = ResultFactory::createDataResult($task, $data_sheet);
        $result->setMessage($this->getWorkbench()->getCoreApp()->getTranslator()->translate('ACTION.CUSTOMDATAQUERY.RESULT', array(
            '%number%' => $counter
        ), $counter));
        
        return $result;
    }
}
?>