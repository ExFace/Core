<?php
namespace exface\Core\Exceptions;

use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

/**
 * Exception thrown if a JSON string cannot be parsed into a UXON object.
 *
 * @author Andrej Kabachnik
 *        
 */
class UxonSyntaxError extends InvalidArgumentException
{
    private $brokenUxon = null;
    
    public function __construct($message, $alias = null, $previous = null, $brokenUxon = null)
    {
        parent::__construct($message, $alias, $previous);
        $this->brokenUxon = $brokenUxon;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debugWidget)
    {
        $debugWidget = parent::createDebugWidget($debugWidget);
        if ($this->brokenUxon !== null) {
            $tab = $debugWidget->createTab();
            $tab->setCaption('Broken UXON');
            $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
                'widget_type' => 'InputText',
                'disabled' => true,
                'width' => '100%',
                'height' => '100%',
                'value' => $this->brokenUxon
            ])));
            $debugWidget->addTab($tab);
        }
            
        return $debugWidget;
    }
}