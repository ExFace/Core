<?php

namespace exface\Core\CommonLogic\Log\Processors;


use exface\Core\CommonLogic\Log\CsvLogConfig;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Events\Action\OnActionFailedEvent;
use exface\Core\Interfaces\Events\EventManagerInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Adds the currently executing action alias to the log record using action lifecycle events.
 *
 * Nested actions temporarily replace the parent alias. Completion or failure restores the parent.
 * 
 * @author Georg Bieger
 */
class ActionAliasProcessor extends AbstractContextProcessor
{
    const KEY_ACTION_ALIAS = CsvLogConfig::KEY_ACTION_ALIAS;
    
    /** @var string[] */
    private array $actionAliases = [];

    /**
     * Registers tracking before action execution and cleanup after completion or failure listeners.
     *
     * @param WorkbenchInterface $workbench
     */
    public function __construct(WorkbenchInterface $workbench)
    {
        $events = $workbench->eventManager();
        $events->addListener(
            OnBeforeActionPerformedEvent::getEventName(),
            [$this, 'onBeforeActionPerformed'],
            (int) EventManagerInterface::PRIORITY_MAX
        );
        $events->addListener(
            OnActionPerformedEvent::getEventName(),
            [$this, 'onActionFinished'],
            (int) EventManagerInterface::PRIORITY_MIN
        );
        $events->addListener(
            OnActionFailedEvent::getEventName(),
            [$this, 'onActionFinished'],
            (int) EventManagerInterface::PRIORITY_MIN
        );
    }

    /**
     * Saves the alias before action logic and other before-action listeners execute.
     *
     * @param OnBeforeActionPerformedEvent $event
     * @return void
     */
    public function onBeforeActionPerformed(OnBeforeActionPerformedEvent $event) : void
    {
        $this->actionAliases[] = $event->getAction()->getAliasWithNamespace();
    }

    /**
     * Restores the parent alias after completion or failure listeners execute.
     *
     * @param OnActionPerformedEvent|OnActionFailedEvent $event
     * @return void
     */
    public function onActionFinished(OnActionPerformedEvent|OnActionFailedEvent $event) : void
    {
        array_pop($this->actionAliases);
    }

    /**
     * {@inheritDoc}
     * @see AbstractContextProcessor::invokeInternal()
     */
    protected function invokeInternal(array &$context, array &$extra): void
    {
        $this->setValue(
            $extra,
            self::KEY_ACTION_ALIAS,
            $this->actionAliases === [] ? '' : $this->actionAliases[array_key_last($this->actionAliases)]
        );
    }
}