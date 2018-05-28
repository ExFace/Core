<?php
namespace exface\Core\Widgets;

/**
 * A responsive table collapses it's columns into vertical lists on small screens.
 * 
 * Actually, this special widget is not required for the described behavior, as
 * you can add responsive columns to a regular DataTable too, but it's a big
 * help when switching the table behavior: all columns of a DataTableResponsive
 * widget are automatically made responsive if they do not hav an explicit widget
 * type set.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataTableResponsive extends DataTable
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getColumnDefaultWidgetType()
     */   
    public function getColumnDefaultWidgetType() : string
    {
        return 'DataColumnResponsive';
    }
}
?>