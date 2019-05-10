<?php
namespace exface\Core\Widgets;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\LogicException;

/**
 * 
 *
 * @author Andrej Kabachnik
 *        
 */
class InputDataGrid extends Data
{
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::isPaged()
     */
    public function isPaged() : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::getPaginator()
     */
    public function getPaginator() : DataPaginator
    {
        throw new LogicException('The widget "' . $this->getWidgetType() . '" does not support pagination - therefore it has no paginator!');
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Data::setPaginator()
     */
    public function setPaginator(UxonObject $uxon) : Data
    {
        return $this;
    }
    
    /**
     * InputDataGrid does not support pagination!
     * 
     * @see \exface\Core\Widgets\Data::setPaginate()
     */
    public function setPaginate($value)
    {
        return $this;
    }
}