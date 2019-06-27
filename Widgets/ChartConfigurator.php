<?php
namespace exface\Core\Widgets;

/**
 * A special configurator for charts
 * 
 * @author Andrej Kabachnik
 * 
 * @method Chart getWidgetConfigured()
 *
 */
class ChartConfigurator extends DataConfigurator
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\DataConfigurator::getDataWidget()
     */
    public function getDataWidget() : Data
    {
        return $this->getWidgetConfigured()->getData();
    }
}