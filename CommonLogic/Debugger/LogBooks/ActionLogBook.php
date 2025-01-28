<?php
namespace exface\Core\CommonLogic\Debugger\LogBooks;

use exface\Core\Events\Action\OnActionFailedEvent;
use exface\Core\Events\Action\OnActionPerformedEvent;
use exface\Core\Events\Action\OnBeforeActionPerformedEvent;
use exface\Core\Events\Behavior\OnBeforeBehaviorAppliedEvent;
use exface\Core\Events\Behavior\OnBehaviorAppliedEvent;
use exface\Core\Events\DataSheet\OnBeforeCreateDataEvent;
use exface\Core\Events\DataSheet\OnBeforeDeleteDataEvent;
use exface\Core\Events\DataSheet\OnBeforeUpdateDataEvent;
use exface\Core\Events\DataSheet\OnCreateDataEvent;
use exface\Core\Events\DataSheet\OnDeleteDataEvent;
use exface\Core\Events\DataSheet\OnUpdateDataEvent;
use exface\Core\Interfaces\Events\ActionEventInterface;
use exface\Core\Interfaces\Events\EventInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Debug\DataLogBookInterface;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\PhpClassDataType;

class ActionLogBook implements DataLogBookInterface
{
    private $task = null;
    
    private $action = null;
    
    private $logBook = null;
    
    private $autoSectionsAdded = false;
    
    private $flowDiagram = null;

    private $eventStack = [];

    private $eventStackIndent = 0;

    private $eventStackProcessed = false;

    /**
     * 
     * @param string $title
     * @param ActionInterface $action
     * @param TaskInterface $task
     * @param string $defaultSection
     */
    public function __construct(string $title, ActionInterface $action, TaskInterface $task)
    {
        $this->task = $task;
        $this->action = $action;
        $this->logBook = new DataLogBook($title);
        $this->logBook->addSection('Action ' . $action->getAliasWithNamespace() . ' "' . $action->getName() . '"');
        $this->logBook->addIndent(1);
        $this->logBook->addLine('Prototype class: ' . get_class($action));
        try {
            $this->logBook->addLine('Action object: ' . $action->getMetaObject()->__toString());
        } catch (\Throwable $e) {
            $this->logBook->addLine('Action object not found');
        }
        if ($task->isTriggeredByWidget()) {
            try {
                $trigger = $task->getWidgetTriggeredBy();
                $this->logBook->addLine('Trigger widget: ' . $trigger->getWidgetType() . ' "**' . $trigger->getCaption() . '**"');
            } catch (\Throwable $e) {
                $this->logBook->addLine('Trigger widget not accessible: ' . $e->getMessage());
            }
        } else {
            $this->logBook->addLine('Trigger widget not known');
        }
        $this->logBook->addIndent(-1);
    }

    public function startLogginEvents() : void
    {
        $eventMgr = $this->action->getWorkbench()->eventManager();
        $eventMgr->addListener(OnBeforeActionPerformedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnActionPerformedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnActionFailedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnBeforeBehaviorAppliedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnBehaviorAppliedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnCreateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnUpdateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->addListener(OnDeleteDataEvent::getEventName(), [$this, 'onEvent']);
    }

    public function stopLoggingEvents() : void
    {
        $eventMgr = $this->action->getWorkbench()->eventManager();
        $eventMgr->removeListener(OnBeforeActionPerformedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnActionPerformedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnActionFailedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnBeforeBehaviorAppliedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnBehaviorAppliedEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnBeforeCreateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnCreateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnBeforeUpdateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnUpdateDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnBeforeDeleteDataEvent::getEventName(), [$this, 'onEvent']);
        $eventMgr->removeListener(OnDeleteDataEvent::getEventName(), [$this, 'onEvent']);
    }

    /**
     * 
     * @param \exface\Core\Interfaces\Events\EventInterface $event
     * @return void
     */
    public function onEvent(EventInterface $event)
    {
        switch (true) {
            // Do not include events from this action itself in the inner log
            case ($event instanceof ActionEventInterface) && $event->getAction() === $this->action:
                break;
            case $event->isOnBefore():
                $this->eventStackIndent++;
                $this->eventStack[] = [
                    'event' => $event,
                    'indent' => $this->eventStackIndent
                ];
                break;
            case $event->isOnAfter():
                $this->eventStack[] = [
                    'event' => $event,
                    'indent' => $this->eventStackIndent
                ];
                $this->eventStackIndent--;
                break;
        }
    }

    /**
     * 
     * @return void
     */
    protected function generateEventsSection() : void
    {
        $this->logBook->addSection('Inner events');
        foreach ($this->eventStack as $entry) {
            $event = $entry['event'];	
            $idt = $entry['indent'];
            switch (true) {
                // Skip the after-events in this list
                case $event->isOnAfter():
                    break;
                case $event instanceof OnBeforeBehaviorAppliedEvent:
                    $behavior = $event->getBehavior();
                    $this->addLine("Behavior `{$behavior->getName()}` (instance " . spl_object_id($behavior) . ") for object {$behavior->getObject()->__toString()}", $idt);
                    break;
                case $event instanceof OnBeforeActionPerformedEvent:
                    $action = $event->getAction();
                    $this->addLine("Action `{$action->getAliasWithNamespace()}` (instance " . spl_object_id($action) . ") on object {$action->getMetaObject()->__toString()}", $idt);
                    break;
                case $event instanceof OnBeforeCreateDataEvent:
                    $this->addLine('Create data `' . DataLogBook::buildTitleForData($event->getDataSheet()) . '`', $idt);
                    break;
                case $event instanceof OnBeforeUpdateDataEvent:
                    $this->addLine('Update data `' . DataLogBook::buildTitleForData($event->getDataSheet()) . '`', $idt);
                    break;
                case $event instanceof OnBeforeDeleteDataEvent:
                    $this->addLine('Delete data `' . DataLogBook::buildTitleForData($event->getDataSheet()) . '`', $idt);
                    break;
            }
        }
        return;
    }
    
    /**
     * 
     * @return TaskInterface
     */
    public function getTask() : TaskInterface
    {
        return $this->task;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Stringable::__toString()
     */
    public function __toString()
    {
        return $this->toMarkdown();
    }
        
    /**
     * 
     * @return string
     */
    protected function toMarkdown() : string
    {
        return $this->logBook->__toString();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLine()
     */
    public function addLine(string $text, int $indent = null, $section = null): LogBookInterface
    {
        $this->logBook->addLine($text, $indent, $section);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        if ($this->eventStackProcessed === false) {
            $this->eventStackProcessed = true;
            $this->generateEventsSection();
        }

        $debug_widget = $this->logBook->createDebugWidget($debug_widget);
        $actionTabs = $debug_widget->getWidgetFirst()->getWidgetFirst();
        if ($actionTabs instanceof DebugMessage) {
            $tab = $actionTabs->createTab();
            $tab->setCaption('Action config');
            $actionTabs->addTab($tab);
            $tab->addWidget(WidgetFactory::createFromUxonInParent($actionTabs, new UxonObject([
                'widget_type' => 'InputUxon',
                'width' => 'max',
                'height' => '100%',
                'caption' => PhpClassDataType::findClassNameWithoutNamespace(get_class($this->action)),
                'hide_caption' => true,
                'value' => $this->action->exportUxonObject()->toJson(true),
                'root_prototype' => '\\' . get_class($this->action)
            ])));
            /* TODO add behavior and data sheet tabs? On first try this also added their
            log output to this log as further sections and cause other side-effects
            foreach ($this->innerStack as $events) {
                foreach ($events as $event) {
                    switch (true) {
                        case $event->isOnBefore():
                            break;
                        case ($event instanceof OnBehaviorAppliedEvent) && null !== $logbook = $event->getLogbook():
                            $logbook->createDebugWidget($actionTabs);
                            break;
                    }
                }
            }*/
        }
        return $debug_widget;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::addDataSheet()
     */
    public function addDataSheet(string $title, DataSheetInterface $dataSheet): LogBookInterface
    {
        $this->logBook->addDataSheet($title, $dataSheet);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addSection()
     */
    public function addSection(string $title): LogBookInterface
    {
        $this->logBook->addSection($title);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setSectionActive()
     */
    public function setSectionActive($section) : LogBookInterface
    {
        $this->logBook->setSectionActive($section);
        return $this;
    }
    
    public function getSectionActive() : ?string
    {
        return $this->logBook->getSectionActive();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeSection()
     */
    public function removeSection(string $title): LogBookInterface
    {
        $this->logBook->removeSection($title);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addLineSpacing()
     */
    public function addLineSpacing($section = null): LogBookInterface
    {
        $this->logBook->addLineSpacing($section);
        return $this;
    }
    
    /**
     * 
     * @param string $code
     * @param string $type
     * @return MarkdownLogBook
     */
    public function addCodeBlock(string $code, string $type = '', $section = null) : LogBookInterface
    {
        $this->logBook->addCodeBlock($code, $type, $section);
        return $this;
    }
    
    /**
     * 
     * @param string $mermaid
     * @param string $placeInSection
     * @return ActionLogBook
     */
    public function setFlowDiagram(string $mermaid) : ActionLogBook
    {
        $this->flowDiagram = $mermaid;
        $this->logBook->addCodeBlock($mermaid, 'mermaid', 1);
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getFlowDiagram() : ?string
    {
        return $this->flowDiagram;
    }
    
    /**
     * 
     * @return string
     */
    public function getFlowDiagramStyleError() : string
    {
        return "fill:#FF6347,stroke:#FF0000";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getId()
     */
    public function getId(): string
    {
        return $this->logBook->getId();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\DataLogBookInterface::getDataSheets()
     */
    public function getDataSheets(): array
    {
        return $this->logBook->getDataSheets();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::setIndentActive()
     */
    public function setIndentActive(int $zeroOrMore) : LogBookInterface
    {
        $this->logBook->setIndentActive($zeroOrMore);
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addIndent()
     */
    public function addIndent(int $positiveOrNegative) : LogBookInterface
    {
        $this->logBook->addIndent($positiveOrNegative);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addException()
     */
    public function addException(\Throwable $e, int $indent = null) : LogBookInterface
    {
        $this->logBook->addException($e, $indent);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::addPlaceholderValue()
     */
    public function addPlaceholderValue(string $placeholder, string $value): LogBookInterface
    {
        $this->logBook->addPlaceholderValue($placeholder, $value);
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getPlaceholderValue()
     */
    public function getPlaceholderValue(string $placeholder) : ?string
    {
        return $this->logBook->getPlaceholderValue($placeholder);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::removeLine()
     */
    public function removeLine($section, int $lineNo): LogBookInterface
    {
        $this->logBook->removeLine($section, $lineNo);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getCodeBlocksInSection()
     */
    public function getCodeBlocksInSection($section = null): array
    {
        return $this->logBook->getCodeBlocksInSection($section);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Debug\LogBookInterface::getLinesInSection()
     */
    public function getLinesInSection($section = null): array
    {
        return $this->logBook->getLinesInSection($section);
    }
}