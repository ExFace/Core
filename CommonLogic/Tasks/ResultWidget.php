<?php
namespace exface\Core\CommonLogic\Tasks;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Tasks\ResultWidgetInterface;

/**
 * Generic task result implementation.
 * 
 * @author Andrej Kabachnik
 *
 */
class ResultWidget extends ResultMessage implements ResultWidgetInterface
{
    private $widget = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultWidgetInterface::getWidget()
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultWidgetInterface::setWidget()
     */
    public function setWidget(WidgetInterface $widget): ResultWidgetInterface
    {
        $this->widget = $widget;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Tasks\ResultWidgetInterface::hasWidget()
     */
    public function hasWidget(): bool
    {
        return is_null($this->widget) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Tasks\ResultMessage::isEmpty()
     */
    public function isEmpty() : bool
    {
        return parent::isEmpty() && ! $this->hasWidget();
    }
}