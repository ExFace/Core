<?php
namespace exface\Core\Events;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Interfaces\WidgetInterface;

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
        return $this->getWidget()->getMetaObject()->getAliasWithNamespace() . NameResolver::NAMESPACE_SEPARATOR . 'Widget';
    }
}