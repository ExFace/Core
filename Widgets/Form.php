<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\CommonLogic\UxonObject;

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
class Form extends Panel implements iHaveButtons
{

    private $buttons = array();

    private $button_widget_type = 'Button';

    // Which type of Buttons should be used. Can be overridden by inheriting widgets
    
    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     * @return Button[]
     */
    public function getButtons()
    {
        return $this->buttons;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $buttons_array)
    {
        if (! is_array($buttons_array))
            return false;
        foreach ($buttons_array as $b) {
            // If the widget type of the Button is explicitly defined, use it, otherwise fall back to the button widget type of
            // this widget: i.e. Button for simple Forms, DialogButton for Dialogs, etc.
            $button_widget_type = property_exists($b, 'widget_type') ? $b->widget_type : $this->getButtonWidgetType();
            $button = $this->getPage()->createWidget($button_widget_type, $this, UxonObject::fromAnything($b));
            // Add the button to the form
            $this->addButton($button);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget)
    {
        $button_widget->setParent($this);
        $button_widget->setMetaObjectId($this->getMetaObject()->getId());
        
        // If the button has an action, that is supposed to modify data, we need to make sure, that the panel
        // contains alls system attributes of the base object, because they may be needed by the business logic
        if ($button_widget->getAction() && $button_widget->getAction()->getMetaObject()->is($this->getMetaObject()) && $button_widget->getAction()->implementsInterface('iModifyData')) {
            /* @var $attr \exface\Core\CommonLogic\Model\Attribute */
            foreach ($this->getMetaObject()->getAttributes()->getSystem() as $attr) {
                if (count($this->findChildrenByAttribute($attr)) <= 0) {
                    $widget = $this->getPage()->createWidget('InputHidden', $this);
                    $widget->setAttributeAlias($attr->getAlias());
                    if ($attr->isUidForObject()) {
                        $widget->setAggregateFunction(EXF_AGGREGATOR_LIST);
                    } else {
                        $widget->setAggregateFunction($attr->getDefaultAggregateFunction());
                    }
                    $this->addWidget($widget);
                }
            }
        }
        
        $this->buttons[] = $button_widget;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        if (($key = array_search($button_widget, $this->buttons)) !== false) {
            unset($this->buttons[$key]);
        }
        return $this;
    }

    /**
     * Returns the class of the used buttons.
     * Regular panels and forms use ordinarz buttons, but
     * Dialogs use special DialogButtons capable of closing the Dialog, etc. This special getter
     * function allows all the logic to be inherited from the panel while just replacing the
     * button class.
     *
     * @return string
     */
    public function getButtonWidgetType()
    {
        return $this->button_widget_type;
    }

    /**
     *
     * @param string $string            
     * @return \exface\Core\Widgets\Panel
     */
    public function setButtonWidgetType($string)
    {
        $this->button_widget_type = $string;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        if (count($this->buttons))
            return true;
        else
            return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::getChildren()
     */
    public function getChildren()
    {
        return array_merge(parent::getChildren(), $this->getButtons());
    }
}
?>