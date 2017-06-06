<?php


namespace exface\Core\CommonLogic\Log\Processors;


abstract class AbstractColumnPositionProcessor
{
    private $workbench;

    function __construct($workbench)
    {
        $this->workbench = $workbench;
    }

    protected function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $contentArray = $this->getContentArray();

        $index = $this->getIndex($record, $this->getIndexColumns());
        if ($index !== false) {
            return array_slice($record, 0, $index, true) +
                   $contentArray +
                   array_slice($record, $index, count($record) - 1, true);
        } else {
            // otherwise insert requestId as first element
            return $contentArray + $record;
        }
    }

    function getIndex($record, $columns)
    {
        if (!$columns) {
            return false;
        }

        foreach ($columns as $column) {
            $index = array_search($column, array_keys($record));
            if ($index !== false) {
                return $index + 1;
            }
        }

        return false;
    }

    protected function getContentArray()
    {
        return array($this->getContentId() => $this->getContent());
    }

    protected abstract function getContentId();

    protected abstract function getContent();

    protected abstract function getIndexColumns();
}
