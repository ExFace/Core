<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Widgets\Parts\ConditionalProperty;
use exface\Core\CommonLogic\UxonObject;

/**
 * A Tab is a special panel to be used within tab-containers like Tabs and WidgetCarousel.
 * 
 * @method Tabs getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class Tab extends Panel
{
    private $badge_attribute_alias;

    private $badge_value;
    
    private $activeIf = null;
    
    public function getBadgeAttributeAlias() : ?string
    {
        return $this->badge_attribute_alias;
    }

    /**
     * Adds a badge to the tab caption showing the value of this attribute (typically a counter or so).
     * 
     * Alternatively to binding the badge to an attribute, you can explicitly set it's value via
     * `badge_value`.
     * 
     * @uxon-property badget_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Tab
     */
    public function setBadgeAttributeAlias(string $value) : Tab
    {
        $this->badge_attribute_alias = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        return $this->prepareDataSheetToX(parent::prepareDataSheetToRead($data_sheet));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null) : DataSheetInterface
    {
        return $this->prepareDataSheetToX(parent::prepareDataSheetToPrefill($data_sheet));
    }
    
    /**
     * 
     * @param DataSheetInterface $data_sheet
     * @return DataSheetInterface
     */
    protected function prepareDataSheetToX(DataSheetInterface $data_sheet) : DataSheetInterface
    {
        if ($this->getBadgeAttributeAlias()) {
            $data_sheet->getColumns()->addFromExpression($this->getBadgeAttributeAlias());
        }
        return $data_sheet;
    }

    /**
     * 
     * @return string|NULL
     */
    public function getBadgeValue() : ?string
    {
        return $this->badge_value;
    }

    /**
     * Adds a badge to the tab's caption showing a fixed value.
     * 
     * Alternatively to setting a fixed badge value, you can also specify a `badge_attribute_alias`
     * to bind the badget value to the model - e.g. some counter, sum, etc.
     * 
     * @uxon-property badge_value
     * @uxon-type string
     * 
     * @param string $value
     * @return \exface\Core\Widgets\Tab
     */
    public function setBadgeValue(string $value) : Tab
    {
        $this->badge_value = $value;
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Container::doPrefill()
     */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        if ($this->getBadgeAttributeAlias()) {
            if ($this->getMetaObject()->isExactly($data_sheet->getMetaObject())) {
                if ($col = $data_sheet->getColumns()->getByExpression($this->getBadgeAttributeAlias())) {
                    $pointer = DataPointerFactory::createFromColumn($col, 0);
                    $this->setBadgeValue($pointer->getValue());
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'badge_value', $pointer));
                }
            } else {
                throw new NotImplementedError('Prefilling Tab badges with data sheets from related objects not implemented!');
            }
        }
        return $this;
    }
    
    /**
     * Captions for tabs can be hidden for a particular tab or for all tabs via Tabs::setHideTabCaptions
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::getHideCaption()
     */
    public function getHideCaption() : ?bool
    {
        if (parent::getHideCaption() === null && $this->getParent()->getHideNavCaptions() === true){
            return true;
        }
        return parent::getHideCaption();
    }

    /**
     * Returns the Tabs container where this tab belongs to.
     * 
     * @return Tabs
     */
    public function getTabs() : Tabs
    {
        return $this->getParent();
    }
    
    /**
     * 
     * @return int
     */
    public function getTabIndex() : int
    {
        return $this->getTabs()->getWidgetIndex($this);
    }
    
    /**
     * Set to TRUE to show this tab initially instead of the first tab (index 0)
     * 
     * This is an alternative to `active_tab` of the Tabs widget. If multiple tabs
     * are marked as `active`, the last one will be actually active.
     * 
     * You can also use a condition to determine the active tab based on values
     * of other widgets via `active_if`.
     * 
     * @uxon-property active
     * @uxon-type boolean
     * 
     * @param bool $trueOrFalse
     * @return Tab
     */
    public function setActive(bool $trueOrFalse) : Tab
    {
        $this->getTabs()->setActiveTab($this->getTabIndex());
        return $this;
    }
    
    /**
     * Sets a condition to activate the tab automatically.
     *
     * ## Examples
     * 
     * Activate a different tab when opening an existing object than when creating a new one
     *
     * ```json
     *  {
     *      "widget_type": "Tab"
     *      "active_if": {
     *          "value_left": "=id_of_uid_field",
     *          "comparator": "!==",
     *          "value_right": ""
     *      }
     *  }
     *
     * ```
     *
     * @uxon-property active_if
     * @uxon-type \exface\Core\Widgets\Parts\ConditionalProperty
     * @uxon-template {"operator": "AND", "conditions": [{"value_left": "", "comparator": "", "value_right": ""}]}
     *
     * @param UxonObject $value
     * @return \exface\Core\Widgets\AbstractWidget
     */
    public function setActiveIf(UxonObject $uxon) : Tab
    {
        $this->activeIf = $uxon;
        return $this;
    }
    
    /**
     *
     * @return ConditionalProperty|NULL
     */
    public function getActiveIf() : ?ConditionalProperty
    {
        if ($this->activeIf === null) {
            return null;
        }
        
        if (! ($this->activeIf instanceof ConditionalProperty)) {
            $this->activeIf = new ConditionalProperty($this, 'active_if', $this->activeIf);
        }
        
        return $this->activeIf;
    }
}