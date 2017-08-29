<?php
namespace exface\Core\QueryBuilders;

use exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder;
use exface\Core\CommonLogic\AbstractDataConnector;
use exface\Core\CommonLogic\DataQueries\FileContentsDataQuery;
use exface\Core\Exceptions\QueryBuilderException;

/**
 * A query builder to the raw contents of a file.
 * This is the base for many specific query builders like the CsvBuilder, etc.
 *
 *
 * @author Andrej Kabachnik
 *        
 */
class FileContentsBuilder extends AbstractQueryBuilder
{

    private $result_rows = array();

    private $result_totals = array();

    private $result_total_rows = 0;

    /**
     *
     * @return FileContentsDataQuery
     */
    protected function buildQuery()
    {
        $query = new FileContentsDataQuery();
        $query->setPathRelative($this->replacePlaceholdersInPath($this->getMainObject()->getDataAddress()));
        return $query;
    }

    public function getResultRows()
    {
        return $this->result_rows;
    }

    public function getResultTotals()
    {
        return $this->result_totals;
    }

    public function getResultTotalRows()
    {
        return $this->result_total_rows;
    }

    public function setResultRows(array $array)
    {
        $this->result_rows = $array;
        return $this;
    }

    public function setResultTotals(array $array)
    {
        $this->result_totals = $array;
        return $this;
    }

    public function setResultTotalRows($value)
    {
        $this->result_total_rows = $value;
        return $this;
    }

    protected function getFileProperty(FileContentsDataQuery $query, $data_address)
    {
        switch (mb_strtoupper($data_address)) {
            case '_FILEPATH':
                return $query->getPathAbsolute();
            case '_FILEPATH_RELATIVE':
                return $query->getPathRelative();
            case '_CONTENTS':
                return file_get_contents($query->getPathAbsolute());
            default:
                return false;
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\QueryBuilder\AbstractQueryBuilder::read()
     */
    public function read(AbstractDataConnector $data_connection = null)
    {
        $result_rows = array();
        $query = $this->buildQuery();
        if (is_null($data_connection)) {
            $data_connection = $this->getMainObject()->getDataConnection();
        }
        
        $data_connection->query($query);
        
        foreach ($this->getAttributes() as $qpart) {
            if ($this->getFileProperty($query, $qpart->getDataAddress())) {
                $result_rows[$qpart->getAlias()] = $this->getFileProperty($query, $qpart->getDataAddress());
            }
        }
        
        $this->setResultTotalRows(count($result_rows));
        
        $this->applyFilters($result_rows);
        $this->applySorting($result_rows);
        $this->applyPagination($result_rows);
        
        $this->setResultRows($result_rows);
        return $this->getResultTotalRows();
    }

    /**
     * Looks for placeholders in the give path and replaces them with values from the corresponding filters.
     * Returns the given string with all placeholders replaced or FALSE if some placeholders could not be replaced.
     *
     * @param string $path            
     * @return string|boolean
     */
    protected function replacePlaceholdersInPath($path)
    {
        foreach ($this->getWorkbench()->utils()->findPlaceholdersInString($path) as $ph) {
            if ($ph_filter = $this->getFilter($ph)) {
                if (! is_null($ph_filter->getCompareValue())) {
                    $path = str_replace('[#' . $ph . '#]', $ph_filter->getCompareValue(), $path);
                } else {
                    throw new QueryBuilderException('Filter "' . $ph_filter->getAlias() . '" required for "' . $path . '" does not have a value!');
                }
            } else {
                // If at least one placeholder does not have a corresponding filter, return false
                throw new QueryBuilderException('No filter found in query for placeholder "' . $ph . '" required for "' . $path . '"!');
            }
        }
        return $path;
    }
}
?>