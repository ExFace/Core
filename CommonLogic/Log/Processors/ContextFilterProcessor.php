<?php
namespace exface\Core\CommonLogic\Log\Processors;

/**
 * Removes provided keys from `$record['context']`
 * 
 * @author andrej.kabachnik
 *
 */
class ContextFilterProcessor 
{
    private $removeContextKeys = [];
    
    /**
     * 
     * @param array $removeContextKeys
     */
    public function __construct(array $removeContextKeys)
    {
        $this->removeContextKeys = $removeContextKeys;    
    }
    
    /**
     * 
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if (is_array($record['context'])) {
            foreach ($this->removeContextKeys as $key) {
                unset($record['context'][$key]);
            }
        }
        return $record;
    }
}