<?php
namespace exface\Core\Events;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\Widgets\WidgetHasNoMetaObjectError;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;

/**
 * Widget event names consist of the qualified alias of the app followed by "Widget" and the respective event type:
 * e.g.
 * ..., etc.
 *
 * @author Andrej Kabachnik
 */
class WidgetEvent extends ExfaceEvent
{

    private $widget = null;

    public function getWidget()
    {
        return $this->widget;
    }

    public function setWidget(WidgetInterface $widget)
    {
        $this->widget = $widget;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Events\ExfaceEvent::getNamespace()
     */
    public function getNamespace()
    {
        try {
            $object_alias = $this->getWidget()->getMetaObject()->getAliasWithNamespace();
        } catch (WidgetHasNoMetaObjectError $e) {
            $this->getWidget()->setMetaObject($this->getWorkbench()->model()->getObject('exface.Core.BASE_OBJECT'));
            $object_alias = $this->getWidget()->getMetaObject()->getAliasWithNamespace();
        }
        return $object_alias . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER . 'Widget';
    }
}