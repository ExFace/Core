<?php
namespace exface\Core\Exceptions\Widgets;

use exface\Core\Interfaces\WidgetInterface;

/**
 * Exception thrown if a no meta object can be determined for a widget.
 *
 * This happens mostly due to missing meta_widget properties in UXON-descriptions.
 * See error code 6T9137Y for details.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetHasNoMetaObjectError extends WidgetConfigurationError
{
    /**
     * Widgets without a meta object are automatically assigned the
     * exface.Core.BASE_OBJECT once they are passed to the WidgetHasNoMetaObjectError
     * exception.
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\WidgetExceptionInterface::setWidget()
     */
    public function setWidget(WidgetInterface $widget)
    {
        $widget->setMetaObject($widget->getWorkbench()->model()->getObject('exface.Core.BASE_OBJECT'));
        return parent::setWidget($widget);
    }
    
    public function getDefaultAlias()
    {
        return '6T9137Y';
    }
}