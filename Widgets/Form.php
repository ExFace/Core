<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;
use exface\Core\DataTypes\AggregatorFunctionsDataType;

/**
 * A Form is a Panel with buttons.
 * Forms and their derivatives provide input data for actions.
 *
 * While having similar purpose as HTML forms, ExFace forms are not the same! They can be nested, they may include tabs,
 * optional panels with lazy loading, etc. Thus, in most HTML-templates the form widget will not be mapped to an HTML
 * form, but rather to some container element (e.g. <div>), while fetching data from the form will need to be custom
 * implemented (i.e. with JavaScript).
 *
 * @author Andrej Kabachnik
 *        
 */
class Form extends Panel implements iHaveButtons, iHaveToolbars
{
    use iHaveButtonsAndToolbarsTrait;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren()
    {
        return array_merge(parent::getChildren(), $this->getToolbars());
    }
    
    public function getToolbarWidgetType(){
        return 'FormToolbar';
    }
    
    /**
     * Adds hidden inputs for system attributes etc. required for the action of the given button widget.
     * 
     * @param Button $button
     * @return Form
     */
    public function addRequiredWidgets(Button $button)
    {
        // If the button has an action, that is supposed to modify data, we need to make sure, that the panel
        // contains alls system attributes of the base object, because they may be needed by the business logic
        if ($action = $button->getAction()) {
            if ($action->getMetaObject()->is($this->getMetaObject()) && ($action->implementsInterface('iModifyData') || $action->implementsInterface('iModifyContext'))) {
                /* @var $attr \exface\Core\Interfaces\Model\MetaAttributeInterface */
                foreach ($this->getMetaObject()->getAttributes()->getSystem() as $attr) {
                    if (count($this->findChildrenByAttribute($attr)) === 0) {
                        $widget = $this->getPage()->createWidget('InputHidden', $this);
                        $widget->setAttributeAlias($attr->getAlias());
                        if ($attr->isUidForObject()) {
                            $widget->setAggregator(AggregatorFunctionsDataType::LIST_ALL($this->getWorkbench()));
                        } else {
                            $widget->setAggregator($attr->getDefaultAggregateFunction());
                        }
                        $this->addWidget($widget);
                    }
                }
            }
        }
        return $this;
    }
}
?>