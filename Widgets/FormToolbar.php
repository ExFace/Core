<?php
namespace exface\Core\Widgets;

/**
 * 
 * @method Form getInputWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
class FormToolbar extends Toolbar
{
    /**
     * 
     * @return \exface\Core\Widgets\Form
     */
    public function getFormWidget()
    {
        return $this->getInputWidget();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        parent::addButton($button_widget, $index);
        $form = $this->getFormWidget();
        // If the button has an action, that is supposed to modify data, we need to make sure, that the panel
        // contains alls system attributes of the base object, because they may be needed by the business logic
        if ($button_widget->getAction() && $button_widget->getAction()->getMetaObject()->is($form->getMetaObject()) && $button_widget->getAction()->implementsInterface('iModifyData')) {
            /* @var $attr \exface\Core\CommonLogic\Model\Attribute */
            foreach ($form->getMetaObject()->getAttributes()->getSystem() as $attr) {
                if (count($form->findChildrenByAttribute($attr)) <= 0) {
                    $widget = $form->getPage()->createWidget('InputHidden', $form);
                    $widget->setAttributeAlias($attr->getAlias());
                    if ($attr->isUidForObject()) {
                        $widget->setAggregateFunction(EXF_AGGREGATOR_LIST);
                    } else {
                        $widget->setAggregateFunction($attr->getDefaultAggregateFunction());
                    }
                    $form->addWidget($widget);
                }
            }
        }
        
        return $this;
    }
}
