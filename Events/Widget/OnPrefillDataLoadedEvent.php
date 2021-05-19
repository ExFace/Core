<?php
namespace exface\Core\Events\Widget;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\Events\DataSheetEventInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Actions\iPrefillWidget;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

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
    
    private $sourceSheets = [];
    
    private $explanation = null;
    
    /**
     * 
     * @param WidgetInterface $widget
     * @param DataSheetInterface $prefillSheet
     * @param iPrefillWidget $action
     * @param array $potentialPrefillSheets
     * @param string $explanation
     */
    public function __construct(WidgetInterface $widget, DataSheetInterface $prefillSheet, iPrefillWidget $action = null, array $potentialPrefillSheets = [], string $explanation = '')
    {
        parent::__construct($widget, $prefillSheet);
        $this->action = $action;
        $this->sourceSheets = $potentialPrefillSheets;
        $this->explanation = $explanation;
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
     * @return DataSheetInterface[]
     */
    protected function getPotentialSourceSheets() : array
    {
        return $this->sourceSheets;
    }
    
    /**
     * 
     * @return string
     */
    protected function getExplanation() : string
    {
        return $this->explanation;
    }
    
    public function addExplanation(string $markdown) : OnPrefillDataLoadedEvent
    {
        $this->explanation .= $markdown;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        // Add a tab with the data sheet UXON
        $prefillTab = $debugWidget->createTab();
        $prefillTab->setCaption('Prefill');
        $debugWidget->addTab($prefillTab);
        
        $innerTabs = WidgetFactory::createFromUxonInParent($prefillTab, new UxonObject([
            'widget_type' => 'DebugMessage'
        ]));
        $prefillTab->addWidget($innerTabs);
        
        $firstTab = $innerTabs->createTab();
        $firstTab->setCaption('Explanation');
        $firstTab->setColumnsInGrid(1);
        $firstTab->addWidget(WidgetFactory::createFromUxonInParent($firstTab, new UxonObject([
            'widget_type' => 'Markdown',
            'value' => '## Prefill for action ' . ($this->getAction() ? $this->getAction()->getAliasWithNamespace() : 'unknown') . PHP_EOL . PHP_EOL . $this->getExplanation(),
            'width' => 'max'
        ])));
        $innerTabs->addTab($firstTab);
        
        $this->getDataSheet()->createDebugWidget($innerTabs);
        $innerTabs->getWidget(($innerTabs->countWidgets()-1))->setCaption('Final prefill data');
        
        foreach ($this->getPotentialSourceSheets() as $name => $sheet) {
            $sheet->createDebugWidget($innerTabs);
            $innerTabs->getWidget(($innerTabs->countWidgets()-1))->setCaption($name);
        }
        
        return $debugWidget;
    }
}