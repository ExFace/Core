<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;

/**
 * Common interface for UI "screens": pages, dialogs, popups, etc.
 * 
 * Screens are not model components themselves - they are the major parts of the UI generated from the model. Screens
 * are what users remember as a "place" in the app. Technically, are the result of rendering a page or a dialog, but
 * users do not really care about how this happened - they just see a recognizable screen. 
 * 
 * The concept of screens is important for the identification of widgets. Every widget belongs to a screen (page or 
 * dialog) and its id stays the same within the screen. The global id of a widget does not - it depends on the path
 * through the UI, the user has taken to reach the widget, because it must be unique within the entire entry page. 
 * 
 * This is an important difference, when you try to identify widgets. For example, if you have a ShowDialog action, 
 * that is used in different pages, the ids of widgets inside the dialog will be different on each page as they get
 * generated or at least prefixed automatically. But since the dialogs are separate screens, the widget ids relative to
 * the screen id space would be the same. This way, you can identify the same widget in screens located in different
 * parts of the UI reliably.
 * 
 * Having such a common interface for model components representing UI screens is important for widget to find reliably,
 * what screen they ar on. Each screen also has its own human-readable id called "slug". It differs from the technical
 * widget id and is primarily intended to be used as a universal reference/link to a screen independently of the
 * model component behind it. 
 * 
 * UI screens are important references for permalinks, widget setups and possibly other components. For example, if
 * a user defines setups for a certain table, it seems obvious for the user "where" this table is located - e.g. in
 * the "order editor" (action) or in the "my orders table" (page). In particular, these "locations" are do not depend
 * on how the user got there - in contrast to our typical page+widget_id approach. They also do not depend on prefill
 * data. Instead, they incorporate the visual appearance or, better to say, the "meaning" of a screen for the user.
 * 
 * Screens are part of the UI structure and do not depend on the facade rendering them. A dialog is always a screens 
 * regardless of whether it was loaded via AJAX or not and independently of whether it is an MVC view in JS or just an
 * HTML element.
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
     * - Default editor: `my.App.MYOBJECT.ShowObjectEditDialog`
     * - ShowDialog action with explicitly defined widget: `<slug_of_parent_screen>.exface.Core.ShowDialog.tbf`
     * 
     * @return string
     */
    public function getUrlSlug() : string;

    /**
     * @return string
     */
    public function getIdSpace() : string;

    /**
     * @return string
     */
    public function getName() : string;

    /**
     * @return string|null
     */
    public function getDescription() : ?string;
}