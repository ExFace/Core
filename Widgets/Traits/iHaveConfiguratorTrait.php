<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\Model\Expression;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Interfaces\Widgets\iConfigureWidgets;
use exface\Core\Interfaces\Widgets\iHaveConfigurator;
use exface\Core\Interfaces\Widgets\WidgetLinkInterface;

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
    private string|WidgetLinkInterface|null $configuratorWidgetLink = null;

    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidgetType()
     */
    abstract public function getConfiguratorWidgetType() : string;
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveConfigurator::getConfiguratorWidget()
     */
    public function getConfiguratorWidget() : iConfigureWidgets
    {
        if ($this->configurator === null) {
            if (null !== $link = $this->getconfiguratorWidgetLink()) {
                $linkedWidget = $link->getTargetWidget();
                switch (true) {
                    case $linkedWidget instanceof iConfigureWidgets:
                        $this->configuratorWidget = $linkedWidget;
                        break;
                    case $linkedWidget instanceof iHaveConfigurator:
                        $this->configurator = $linkedWidget->getConfiguratorWidget();
                        break;
                    default:
                        throw new WidgetConfigurationError($this, 'Invalid configurator_widget_link in ' . $this->getWidgetType() . ': it must point to a configurator or the its owner widget');
                }
            } else {
                $this->configurator = WidgetFactory::create($this->getPage(), $this->getConfiguratorWidgetType(), $this);
            }
        }
        return $this->configurator;
    }
    
    /**
     * Detailed configuration for the configurator for this widget - only needed for special cases
     * 
     * @uxon-property configurator
     * @uxon-type \exface\Core\Widgets\WidgetConfigurator
     * @uxon-template {"": ""}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $uxon
     * @return \exface\Core\Interfaces\Widgets\iHaveConfigurator
     */
    protected function setConfigurator(UxonObject $uxon) : iHaveConfigurator
    {
        if ($this->isConfiguratorLinked()) {
            throw new WidgetConfigurationError($this, 'Cannot change configurator settings for widget ' . $this->getWidgetType() . ' when `configurator_widget_link` is used');
        }
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
        if ($this->isConfiguratorLinked()) {
            throw new WidgetConfigurationError($this, 'Cannot change configurator settings for widget ' . $this->getWidgetType() . ' when `configurator_widget_link` is used');
        }
        $expectedClass = WidgetFactory::getWidgetClassFromType($this->getConfiguratorWidgetType());
        if (! $widget instanceof $expectedClass) {
            throw new InvalidArgumentException('Cannot use widget ' . $widget->getWidgetType() . ' as configurator for ' . $this->getWidgetType() . ': expecting a '. $this->getConfiguratorWidgetType() . '!');
        }
        $this->configurator = $widget;
        return $this;
    }

    /**
     * Use a configurator from another widget
     * 
     * You can make multiple widgets share a single configurator. Of course, the linked configurator must be
     * compatible: in must be based on the same object or a derivative and must have the same widget type.
     * 
     * When a linked configurator is used, this widget will be automatically refreshed when the linked widget
     * is refreshed (= when the linked configurator is applied).
     * 
     * @uxon-property configurator_widget_link
     * @uxon-type metamodel:widget_link|uxon:$..id
     * 
     * @param string $widgetId
     * @return iHaveConfigurator
     */
    protected function setConfiguratorWidgetLink(string $widgetIdOrLink) : IhaveConfigurator
    {
        $widgetIdOrLink = trim($widgetIdOrLink);
        if (! Expression::detectReference($widgetIdOrLink)) {
            $widgetIdOrLink = '=' . $widgetIdOrLink;
        }
        $this->configuratorWidgetLink = $widgetIdOrLink;
        $this->setRefreshWithWidget('=' . $widgetIdOrLink);
        return $this;
    }

    /**
     * {@inheritDoc}
     * @see iHaveConfigurator::getConfiguratorWidgetLink()
     */
    public function getConfiguratorWidgetLink() : ?WidgetLinkInterface
    {
        if (is_string($this->configuratorWidgetLink)) {
            $this->configuratorWidgetLink = WidgetLinkFactory::createFromWidget($this, $this->configuratorWidgetLink);
        }
        return $this->configuratorWidgetLink;
    }

    /**
     * {@inheritDoc}
     * @see iHaveConfigurator::isConfiguratorLinked()
     */
    public function isConfiguratorLinked() : bool
    {
        return $this->configuratorWidgetLink !== null;
    }
}