<?php

namespace exface\Core\CommonLogic\Log\Processors;


class UserNameProcessor
{
    private $userName;

    function __construct($userName)
    {
        $this->userName = $userName ? $userName : "";
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        $requestIdArray = array('userName' => $this->userName);

        $index = $this->getIndex($record);
        if ($index !== false) {
            return array_slice($record, 0, $index, true) +
                   $requestIdArray +
                   array_slice($record, $index, count($record) - 1, true);
        } else {
            // otherwise insert requestId as first element
            return $requestIdArray + $record;
        }
    }

    protected function getIndex($record)
    {
        // insert userName after requestId if present or after id if present
        $index = array_search('requestId', array_keys($record));
        if ($index !== false)
            return $index + 1;

        $index = array_search('id', array_keys($record));
        if ($index !== false)
            return $index + 1;

        return false;
    }
}
