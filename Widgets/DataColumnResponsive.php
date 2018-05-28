<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\WidgetVisibilityDataType;

/**
 * A responsive table column collapses it's columns into vertical lists on small screens.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataColumnResponsive extends DataColumn
{
    private $visibilityOnSmartphone = null;
    
    private $visibilityOnTablet = null;
    
    private $visibilityOnDesktop = null;
    
    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnSmartphone() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnSmartphone === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnSmartphone = $v;
        }
        return $this->visibilityOnSmartphone;
    }

    /**
     * 
     * @param WidgetVisibilityDataType|string $visibilityOnSmartphone
     * @return DataColumnResponsive
     */
    public function setVisibilityOnSmartphone($visibility) : DataColumnResponsive
    {
        $this->visibilityOnSmartphone = $visibility;
        return $this;
    }

    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnTablet() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnTablet === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnTablet = $v;
        }
        return $this->visibilityOnTablet;
    }

    /**
     * 
     * @param WidgetVisibilityDataType|string $visibilityOnTablet
     * @return DataColumnResponsive
     */
    public function setVisibilityOnTablet($visibility) : DataColumnResponsive
    {
        $this->visibilityOnTablet = $visibility;
        return $this;
    }

    /**
     * 
     * @return WidgetVisibilityDataType
     */
    public function getVisibilityOnDesktop() : WidgetVisibilityDataType
    {
        if ($this->visibilityOnDesktop === null) {
            $v = $this->getVisibility();
            if (! ($v instanceof WidgetVisibilityDataType)) {
                $v = WidgetVisibilityDataType::fromValue($this->getWorkbench(), $v);
            }
            $this->visibilityOnDesktop = $v;
        }
        return $this->visibilityOnDesktop;
    }

    /**
     * visibilityOnDesktop
     * @param WidgetVisibilityDataType|string $visibility
     * @return DataColumnResponsive
     */
    public function setVisibilityOnDesktop($visibility) : DataColumnResponsive
    {
        $this->visibilityOnDesktop = $visibility;
        return $this;
    }

}
?>