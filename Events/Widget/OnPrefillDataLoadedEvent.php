<?php
namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Interfaces\Debug\LogBookInterface;

/**
 * Event fired after prefill data was computed for a widget
 * 
 * Listeners to this event can modify the prefill data via `$event->getDataSheet()`. The event can also
 * produce a debug tab explaining the prefill logic used.
 * 
 * @event exface.Core.Widget.OnPrefillDataLoaded
 *
 * @author Andrej Kabachnik
 *        
 */
class OnPrefillDataLoadedEvent extends OnBeforePrefillEvent implements DataSheetEventInterface, iCanGenerateDebugWidgets
{
    private $action = null;
    
    private $logBook = null;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param DataSheetInterface $prefillSheet
     * @param iPrefillWidget $action
     * @param array $potentialPrefillSheets
     * @param string $explanation
     */
    public function __construct(WidgetInterface $widget, DataSheetInterface $prefillSheet, iPrefillWidget $action = null, LogBookInterface $logBook = null)
    {
        parent::__construct($widget, $prefillSheet);
        $this->action = $action;
        $this->logBook = $logBook;
    }
    
    /**
     * {@inheritdoc}
     * @see \exface\Core\Events\AbstractEvent::getEventName()
     */
    public static function getEventName() : string
    {
        return 'exface.Core.Widget.OnPrefillDataLoaded';
    }
    
    /**
     * 
     * @return ActionInterface|NULL
     */
    public function getAction() : ?ActionInterface
    {
        return $this->action;
    }
    
    /**
     * 
     * @return LogBookInterface|NULL
     */
    public function getLogBook() : ?LogBookInterface
    {
        return $this->logBook;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        if (null !== $logBook = $this->getLogBook()) {
            return $logBook->createDebugWidget($debugWidget);
        }
        return $debugWidget;
    }
}