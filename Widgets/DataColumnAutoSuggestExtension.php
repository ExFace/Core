<?php

namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\ISupportAttributeGroupsTrait;

/**
 * This widget is functionally identical to Widgets/DataColumn, but has some additional auto-suggest options.
 * 
 * @see DataColumn
 */
class DataColumnAutoSuggestExtension extends DataColumn
{
    use ISupportAttributeGroupsTrait;
}