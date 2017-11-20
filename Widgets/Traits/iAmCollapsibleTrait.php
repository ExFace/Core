<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\DataTypes\BooleanDataType;

trait iAmCollapsibleTrait {
    
    private $collapsible = false;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::isCollapsible()
     */
    public function isCollapsible()
    {
        return $this->collapsible;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iAmCollapsible::setCollapsible()
     */
    public function setCollapsible($value)
    {
        $this->collapsible = BooleanDataType::cast($value);
    }
    
}