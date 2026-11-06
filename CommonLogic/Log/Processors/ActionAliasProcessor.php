<?php

namespace exface\Core\CommonLogic\Log\Processors;


use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Adds the action alias to the extra field of the log record.
 * 
 * @author Georg Bieger
 */
class ActionAliasProcessor extends AbstractContextProcessor
{
    const KEY_ACTION_ALIAS = CsvLogConfig::KEY_ACTION_ALIAS;
    
    private WorkbenchInterface $workbench;

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    /**
     * Never throws: this runs inside logging, where an exception would break or loop the logger.
     */
    protected function getCurrentAction() : ?ActionInterface
    {
        if (! $this->workbench->isStarted()) {
            return null;
        }
        
        try {
            return $this->workbench
                ->getContext()
                ->getScopeWindow()
                ->getActionContext()
                ->getCurrentAction();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    protected function invokeInternal(array &$context, array &$extra): void
    {
        $this->setValue(
            $extra,
            self::KEY_ACTION_ALIAS,
            $this->getCurrentAction()?->getAliasWithNamespace() ?? ''
        );
    }
}