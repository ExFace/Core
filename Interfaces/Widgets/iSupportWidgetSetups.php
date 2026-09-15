<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Tab;

/**
 * Configurator widgets implementing this interface provide persisted widget setups.
 *
 * @author Andrej Kabachnik
 */
interface iSupportWidgetSetups extends iConfigureWidgets
{
    /**
     * Returns TRUE if setup management is enabled for the configured widget.
     *
     * @return bool
     */
    public function hasSetups() : bool;

    /**
     * Returns the tab containing the setup-management controls.
     *
     * @return Tab|null
     */
    public function getSetupsTab() : ?Tab;

    /**
     * Returns the ID of the setup list widget, or NULL if setups are disabled.
     *
     * @return string|null
     */
    public function getSetupsTableId() : ?string;
}
