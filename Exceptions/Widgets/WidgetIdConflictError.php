<?php

namespace exface\Core\Exceptions\Widgets;

/**
 * Exception thrown if duplicate widget id's are detected in a UI page.
 *
 * This mostly occurs if the user explicitly sets the same id for more than one widget on the page. The uniqueness
 * of ids is especially hard to track if reusing widgets via "extend_widget" UXON-property.
 *
 * @author Andrej Kabachnik
 *        
 */
class WidgetIdConflictError extends WidgetPropertyInvalidValueError
{

    public static function getDefaultAlias()
    {
        return '6T6I51G';
    }
}