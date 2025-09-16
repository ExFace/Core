<?php
namespace exface\Core\Exceptions;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Exceptions\UxonExceptionInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;

/**
 * Exception thrown if the entity (widget, action, etc.) represented by a UXON description cannot be instantiated due to invalid or missing properties.
 *
 * If the entity exists alread, it's class-specific exceptions (e.g. widget or action exceptions) 
 * should be preferred to this general exception.
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
    private string|null $affectedProperty = null;

    /**
     *
     * @param UxonObject  $uxon
     * @param string      $message
     * @param null        $alias
     * @param null        $previous
     * @param string|null $affectedProperty
     */
    public function __construct(
        UxonObject $uxon, 
        string $message, 
        $alias = null, 
        $previous = null, 
        string $affectedProperty = null
    )
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->uxon = $uxon;
        
        $this->affectedProperty = $affectedProperty;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\UxonExceptionInterface::getUxon()
     */
    public function getUxon()
    {
        return $this->uxon;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\iCanGenerateDebugWidgets::createDebugWidget()
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = $this->parentCreateDebugWidget($debug_widget);
        if ($debug_widget->findChildById('uxon_tab') === false) {
            $uxon_tab = $debug_widget->createTab();
            $uxon_tab->setId('uxon_tab');
            $uxon_tab->setCaption('UXON');
            $uxon_tab->addWidget(WidgetFactory::createFromUxonInParent($uxon_tab, new UxonObject([
                'widget_type' => 'InputUxon',
                'disabled' => true,
                'width' => '100%',
                'height' => '100%',
                'value' => $this->getUxon()->toJson()
            ])));
            $debug_widget->addTab($uxon_tab);
        }
        return $debug_widget;
    }

    /**
     * @inerhitDoc 
     * @see UxonExceptionInterface::getPath()
     */
    public function getPath(): array
    {
        $path = $this->uxon?->getPath() ?? [];
        
        if($this->affectedProperty !== null) {
            $path[] = $this->affectedProperty;
        }
        
        return $path;
    }
}