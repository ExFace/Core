<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Widgets\Parts\WidgetPropertyBinding;

/**
 * Implemented by widgets that bind to more than one value simultaneously.
 *
 * A regular Display widget binds exactly one data source (attribute, calculation, etc.).
 * A DisplayTemplate has one or more `[#placeholders#]` that each map to an
 * independent value. Facades and data-transposing code (e.g. DataMatrix) use
 * this interface to distinguish such widgets from single-value widgets and to handle
 * them correctly
 * 
 * @author saskia.hustinx 
 *
 */
interface iHaveMultipleBindings
{
    /**
     * Returns all property bindings of this widget 
     *
     * @return WidgetPropertyBinding[]
     */
    public function getBindings() : array;
}
