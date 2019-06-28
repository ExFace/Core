<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;

/**
 * This trait contains an implementation of the methods defined in the interface iHaveConfigurator.
 * 
 * @author Andrej Kabachnik
 *
 */
trait iHaveConfiguratorTrait 
{
    /**
     *
     * @var iConfigureWidgets
     */
    private $configurator = null;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidget()
     */
    public function getConfiguratorWidget() : iConfigureWidgets
    {
        if ($this->configurator === null) {
            $this->configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
        }
        return $this->configurator;
    }
    
    public function setConfigurator(UxonObject $uxon) : iHaveConfigurator
    {
        if ($this->configurator === null) {
            $this->configurator = WidgetFactory::createFromUxon($this->getPage(), $uxon, $this, $this->getConfiguratorWidgetType());
        } else {
            $this->configurator->importUxonObject($uxon);
        }
        
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::setConfiguratorWidget()
     */
    public function setConfiguratorWidget(iConfigureWidgets $widget) : iHaveConfigurator
    {
        $expectedClass = WidgetFactory::getWidgetClassFromType($this->getConfiguratorWidgetType());
        if (! $widget instanceof $expectedClass) {
            throw new InvalidArgumentException('Cannot use widget ' . $widget->getWidgetType() . ' as configurator for ' . $this->getWidgetType() . ': expecting a '. $this->getConfiguratorWidgetType() . '!');
        }
        $this->configurator = $widget;
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    abstract public function getConfiguratorWidgetType() : string;
}