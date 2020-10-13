<?php
namespace exface\Core\Widgets;

use exface\Core\Widgets\Traits\SingleValueInputTrait;

/**
 * This widget represents a two-state-switch mostly displayed as a checkbox.
 *
 * @author Andrej Kabachnik
 *        
 */
class InputCheckBox extends Input
{
    use SingleValueInputTrait;

    /**
     * 
     * @return InputSelect
     */
    public function transformIntoSelect() : InputSelect
    {
        $parent = $this->getParent();
        $select = $this->getPage()->createWidget('InputSelect', $parent);
        $this->getPage()->removeWidget($this);
        $select->setId($this->getId());
        $select->setAttributeAlias($this->getAttributeAlias());
        $select->setValue($this->getValueExpression());
        $select->setSelectableOptions(array(
            '',
            1,
            0
        ), array(
            $this->translate('WIDGET.SELECT_ALL'),
            $this->translate('WIDGET.SELECT_YES'),
            $this->translate('WIDGET.SELECT_NO')
        ));
        $select->setDisabled($this->isDisabled());
        $select->setVisibility($this->getVisibility());
        $select->setCaption($this->getCaption());
        return $select;
    }
}
?>