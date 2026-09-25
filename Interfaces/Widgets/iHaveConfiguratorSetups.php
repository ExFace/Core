<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * Interface for widgets, that have a configurator with setups.
 * 
 * This is just a convenience interface, that allows to detect the use of setups without getting the configurator,
 * checking if that supports setups, etc.
 * 
 * @see \exface\Core\Widgets\WidgetConfigurator for a details explanation.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveConfiguratorSetups extends iHaveConfigurator
{
    /**
     * Returns true if the configurator of this widget has setups enabled and false otherwise.
     * @return bool
     */
    public function getConfiguratorSetupsEnabled() : bool;
}