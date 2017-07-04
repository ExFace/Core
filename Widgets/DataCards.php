<?php
namespace exface\Core\Widgets;

/**
 * Similar to a DataList, but displaying each element as a tile or card instead of a list item.
 * 
 * While a list displays elements one above the other, each having full with,
 * cards have a width of their own and will be displayed next to each other if
 * there is enough horizontal space.
 *
 * The contents of the widget is still defined similarly to a DataTable via 
 * columns, filters, buttons, etc. It's just the visual appearance, that is 
 * different: each card represents a data row, where columns are rendered as
 * fields within the card. The order of the columns and their align-property
 * allows some customization of the appearance of the card.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataCards extends DataList
{
    
}
?>