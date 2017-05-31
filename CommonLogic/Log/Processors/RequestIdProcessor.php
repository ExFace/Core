<?php

namespace exface\Core\CommonLogic\Log\Processors;


class RequestIdProcessor
{
    private $workbench;

    function __construct($workbench)
    {
        $this->workbench = $workbench;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $requestIdArray = array('requestid' => $this->workbench->context()->getScopeRequest()->getScopeId());

        $index = $this->getIndex($record);
        if ($index !== false) {
            $idIndex = array_search('id', array_keys($record)) + 1;

            return array_slice($record, 0, $idIndex, true) +
                   $requestIdArray +
                   array_slice($record, $idIndex, count($record) - 1, true);
        } else {
            // otherwise insert requestId as first element
            return $requestIdArray + $record;
        }
    }

    protected function getIndex($record)
    {
        // insert userName after id if present
        $index = array_search('id', array_keys($record));
        if ($index !== false)
            return $index + 1;

        return false;
    }
}
