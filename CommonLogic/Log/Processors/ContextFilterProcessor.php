<?php
namespace exface\Core\CommonLogic\Log\Processors;

/**
 * Removes the specified context keys from the log record.
 * 
 * @author Georg Bieger
 */
class ContextFilterProcessor extends AbstractContextProcessor
{
    private array $removeContextKeys;
    
    /**
     * @param array $removeContextKeys
     */
    public function __construct(array $removeContextKeys)
    {
        $this->removeContextKeys = $removeContextKeys;    
    }
    
    /**
     * @inheritDoc
     */
    protected function invokeInternal(array &$context, array &$extra): void
    {
        foreach ($this->removeContextKeys as $key) {
            unset($context[$key]);
        }
    }
}