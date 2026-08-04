<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Common interface for UI "screens": pages, dialogs, popups, etc.
 * 
 * Having such a common interface is important to identify widget, that user would say "can be opened". Users tend
 * to reference these widgets when speaking about the apps: e.g. when you are on screen "xxx" - that could be a page or
 * a dialog and the user does not really bother what it is exactly.
 * 
 * UI screens are important references for permalinks, widget setups and possibly other component. For example, if
 * a user defines setups for a certain table, it seems obvious for the user "where" this table is located - e.g. in
 * the "order editor" (action) or in the "my orders table" (page). In particular, these "locations" are do not depend
 * on how the user got there - in contrast to our typical page+widget_id approach. They also do not depend on prefill
 * data. Instead, they incorporate the visual appearance or, better to say, the "meaning" of a screen for the user.
 * 
 * Screens are part of the model and do not depend on the facade rendering them. A dialog is always a screens regardless
 * of whether it was loaded via AJAX or not and independently of whether it is an MVC view in JS or just an HTML
 * element.
 * 
 * @author Andrej Kabachnik
 *
 */
interface UiScreenInterface extends WorkbenchDependantInterface
{
    /**
     * Examples:
     * 
     * - Page: page alias - e.g. `exface.core.administraiont`
     * - Object action: action alias - `my.App.MyObjectCancelDialog`
     * - Default editor: `exface.Core.PAGE.ShowObjectEditDialog`
     * - ShowDialog action with explicitly defined widget: `<slug_of_parent_screen>.exface.Core.ShowDialog.tbf`
     * 
     * @return string
     */
    public function getSlug() : string;
}