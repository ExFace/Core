<?php
namespace exface\Core\CommonLogic\Log\Processors;

class IdProcessor
{

    /**
     *
     * @param array $record            
     * @return array
     */
    public function __invoke(array $record)
    {
        $idArray = array(
            'id' => $record['context']['id']
        );
        unset($record['context']['id']);
        
        return $idArray + $record;
    }
}
