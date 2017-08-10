<?php
namespace exface\Core\Exceptions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * Exception thrown if the entity (widget, action, etc.) represented by a UXON description cannot be instantiated due to invalid or missing properties.
 *
 * If the entity exists alread, it's class-specific exceptions (e.g. widget or action exceptions) should be preferred
 * to this general exception.
 *
 * @author Andrej Kabachnik
 *        
 */
class UxonParserError extends RuntimeException implements UxonExceptionInterface
{
    
    use ExceptionTrait {
		createDebugWidget as parentCreateDebugWidget;
	}

    private $uxon = null;

    /**
     *
     * @param UxonObject $uxon            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(UxonObject $uxon, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->setUxon($uxon);
    }

    public function getUxon()
    {
        return $this->uxon;
    }

    public function setUxon(UxonObject $uxon)
    {
        $this->uxon = $uxon;
        return $this;
    }

    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        if ($debug_widget->findChildById('uxon_tab') === false) {
            $page = $debug_widget->getPage();
            $uxon_tab = $debug_widget->createTab();
            $uxon_tab->setId('UXON');
            $uxon_tab->setCaption('UXON');
            $uxon_tab->setNumberOfColumns(1);
            $request_widget = WidgetFactory::create($page, 'Html');
            $uxon_tab->addWidget($request_widget);
            $request_widget->setValue('<pre>' . $this->getUxon()->toJson(true) . '</pre>');
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }
}
?>