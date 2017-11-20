<?php
namespace exface\Core\Widgets\Traits;

trait iHaveIconTrait {
    
    private $icon = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::getIcon()
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveIcon::setIcon()
     */
    public function setIcon($value)
    {
        $this->icon = $value;
    }    
}