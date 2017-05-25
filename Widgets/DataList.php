<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveTopToolbar;
use exface\Core\Interfaces\Widgets\iHaveBottomToolbar;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iSupportMultiSelect;

/**
 * Similar to a DataTable, but displaying each element as a tile or card instead of a table row.
 *
 * The contents is still defined via columns, filters, buttons, etc. It's just the visual appearance, that
 * is different.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataList extends Data implements iHaveTopToolbar, iHaveBottomToolbar, iFillEntireContainer, iSupportMultiSelect
{

    private $hide_toolbar_top = false;

    private $hide_toolbar_bottom = false;

    private $multi_select = false;

    public function getHideToolbarTop()
    {
        return $this->hide_toolbar_top;
    }

    /**
     * Set to TRUE to hide the top toolbar or FALSE to show it.
     *
     * @uxon-property hide_toolbar_top
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbarTop($value)
    {
        $this->hide_toolbar_top = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getHideToolbarBottom()
    {
        return $this->hide_toolbar_bottom;
    }

    /**
     * Set to TRUE to hide the bottom toolbar or FALSE to show it.
     *
     * @uxon-property hide_toolbar_bottom
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbarBottom($value)
    {
        $this->hide_toolbar_bottom = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    public function getHideToolbars()
    {
        return ($this->getHideToolbarTop() && $this->getHideToolbarBottom());
    }

    /**
     * Set to TRUE to hide the all toolbars.
     * Use hide_toolbar_top and hide_toolbar_bottom to control toolbar individually.
     *
     * @uxon-property hide_toolbars
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveTopToolbar::setHideToolbarTop()
     */
    public function setHideToolbars($value)
    {
        $this->setHideToolbarTop($value);
        $this->setHideToolbarBottom($value);
        return $this;
    }

    public function getWidth()
    {
        if (parent::getWidth()->isUndefined()) {
            $this->setWidth('max');
        }
        return parent::getWidth();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings()
    {
        return null;
    }

    public function getMultiSelect()
    {
        return $this->multi_select;
    }

    /**
     * Set to TRUE to allow selecting multiple elements at a time and FALSE to force selection of exactly one element.
     *
     * @uxon-property multi_select
     * @uxon-type boolean
     *
     * @see \exface\Core\Interfaces\Widgets\iSupportMultiSelect::setMultiSelect()
     */
    public function setMultiSelect($value)
    {
        $this->multi_select = \exface\Core\DataTypes\BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::getValues()
     */
    public function getValues()
    {
        // TODO set selected list items programmatically
        /*
         * if ($this->getValue()){
         * return explode(EXF_LIST_SEPARATOR, $this->getValue());
         * }
         */
        return array();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValues()
     */
    public function setValues($expression_or_delimited_list)
    {
        // TODO set selected list items programmatically
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveValues::setValuesFromArray()
     */
    public function setValuesFromArray(array $values)
    {
        $this->setValue(implode(EXF_LIST_SEPARATOR, $values));
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        $uxon->setProperty('multi_select', $this->getMultiSelect());
        $uxon->setProperty('hide_toolbar_top', $this->getHideToolbarTop());
        $uxon->setProperty('hide_toolbar_bottom', $this->getHideToolbarBottom());
        return $uxon;
    }
}
?>