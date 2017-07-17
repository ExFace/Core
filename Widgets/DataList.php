<?php
namespace exface\Core\Widgets;

/**
 * Produces a menu-like list from a table definition, where every row is transformed to a list item.
 *
 * The DataList is a good choice for high narrow widgets, that should display
 * large tabular data sets. It is particularly handy for horizontal master-detail
 * displays (as master).
 * 
 * The contents is still defined via columns, filters, buttons, etc. It's just 
 * the visual appearance, that is different. Instead of rendering a table row
 * the DataList will create a list item, where all values from the row are listed
 * one-after-another. Think of it as a 1-column table with multiline rows.
 * 
 * Using the aling-property of DataColumns you can place upto three values in
 * one line within the list item: left, center and right.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataList extends DataTable
{
    
}
?>