<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;

/**
 * A Tab is a special panel to be used within the tabs widget
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
    
    protected function prepareDataSheetToX(DataSheetInterface $data_sheet) : DataSheetInterface
    {
        if ($this->getBadgeAttributeAlias()) {
            $data_sheet->getColumns()->addFromExpression($this->getBadgeAttributeAlias());
        }
        return $data_sheet;
    }

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
    public function getHideCaption()
    {
        if ($this->getParent()->getHideTabsCaptions()){
            return true;
        }
        return parent::getHideCaption();
    }

}
?>