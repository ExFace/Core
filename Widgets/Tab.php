<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Exceptions\NotImplementedError;
use exface\Core\Interfaces\Widgets\iHaveIcon;

/**
 * A Tab is a special panel to be used within the tabs widget
 * 
 * @method Tabs getParent()
 *
 * @author Andrej Kabachnik
 *        
 */
class Tab extends Panel implements iHaveIcon
{

    private $badge_attribute_alias;

    private $badge_value;
    
    private $icon = null;

    public function getBadgeAttributeAlias()
    {
        return $this->badge_attribute_alias;
    }

    public function setBadgeAttributeAlias($value)
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
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        
        $data_sheet->getColumns()->addFromExpression($this->getBadgeAttributeAlias());
        
        return $data_sheet;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::prepareDataSheetToPrefill()
     */
    public function prepareDataSheetToPrefill(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToPrefill($data_sheet);
        
        $data_sheet->getColumns()->addFromExpression($this->getBadgeAttributeAlias());
        
        return $data_sheet;
    }

    public function getBadgeValue()
    {
        return $this->badge_value;
    }

    public function setBadgeValue($value)
    {
        $this->badge_value = $value;
        return $this;
    }

    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        if ($this->getBadgeAttributeAlias()) {
            if ($this->getMetaObjectId() == $data_sheet->getMetaObject()->getId()) {
                $this->setBadgeValue($data_sheet->getCellValue($this->getBadgeAttributeAlias(), 0));
            } else {
                throw new NotImplementedError('Prefilling Tab badges with data sheets from related objects not implemented!');
            }
        }
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Panel::getIconName()
     */
    public function getIconName() {
        return $this->icon;    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Panel::setIconName()
     */
    public function setIconName($value) {
        $this->icon = $value;
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