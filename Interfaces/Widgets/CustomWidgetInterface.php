<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * A custom widget is a widget, that takes care of rendering itself.
 * 
 * Custom widgets include a rendering method, that facades can call to get the
 * widget rendered. Thus, the abstract and concrete models of a custom widget
 * can be comined in one file.  
 * 
 * ## Why?
 * 
 * App developers can provide their own widget types in their apps by implementing the
 * WidgetInterface. These widgets can be referenced in UXON by prefixing the widget
 * type (= class name) with a namespace (= app alias). In this case, the WidgetFactory
 * will asc the app to instantiate the widget. The UXON configuration will then be
 * applied to this custom widget.
 * 
 * However, the creator of such a custom widget cannot possibly provide rendering
 * implementations for all existing facades. Moreover, In most cases, the developer
 * is only interested in one specific facade. Of course, the implementation of this
 * facade can be forked and modified/extended to support the new widget type, but
 * this is a lot of work. It will be even more work to keep the forked version in
 * sync with the original.
 * 
 * ## How?
 * 
 * If a widget implements this interface, facades will first try to deal with the widget
 * using their regular logic (mostly creating a facade element for the widget type, that
 * the custom widget inherits from) and than pass the result to the `createTemplateElmeent()`
 * method. The template will then use the return value to render the widget.
 * 
 * @author Andrej Kabachnik
 *
 */
interface CustomWidgetInterface extends WidgetInterface
{
    /**
     * Returns a facade element for this widget for the given facade and optionally
     * a base elemnt (i.e. the element, that the facade would produce if this method
     * was not there).
     * 
     * @param FacadeInterface $facade
     * @param mixed|NULL $baseElement
     * 
     * @return mixed
     */
    public function createFacadeElement(FacadeInterface $facade, $baseElement = null);
}