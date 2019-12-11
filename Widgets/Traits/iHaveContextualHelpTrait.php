<?php
namespace exface\Core\Widgets\Traits;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\DataTable;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\Widgets\iTakeInput;
use exface\Core\Interfaces\WidgetInterface;

/**
 * This trait contains common methods to implement the iHaveContextualHelp interface.
 * 
 * @author Andrej Kabachnik
 */
trait iHaveContextualHelpTrait {
    
    private $help_button = null;
    
    private $help_button_uxon = null;
    
    private $hide_help_button = null;
        
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpButton()
     */
    public function getHelpButton() : iTriggerAction
    {
        if ($this->help_button === null) {
            if (method_exists($this, 'getButtonWidgetType')) {
                $btnType = $this->getButtonWidgetType();
            } else {
                $btnType = 'Button';
            }
            $this->help_button = WidgetFactory::createFromUxonInParent($this, $this->getHelpButtonUxon(), $btnType);
        }
        return $this->help_button;
    }
    
    /**
     * 
     * @return UxonObject
     */
    private function getHelpButtonUxon() : UxonObject
    {
        return $this->help_button_uxon ?? new UxonObject([
            'hidden' => true,
            'refresh_input' => false,
            'action' => [
                'alias' => 'exface.Core.ShowHelpDialog'
            ]
        ]);
    }
    
    
    /**
     * Custom configuration for the contextual help button.
     * 
     * For example, a help-dialog showing some external HTML help page:
     * 
     * ```
     * {
     *  "widget_type": "Form",
     *  "help_button": {
     *      "action": {
     *          "alias": "exface.Core.ShowHelpDialog",
     *          "widget": {
     *              "widget_type": "Dialog",
     *              "widgets": [
     *                  {
     *                      "widget_type": "Browser",
     *                      "url": "http://yourdomain.com/custom/help/url"
     *                  }
     *              ]
     *          }
     *      }
     *  }
     * }
     * 
     * ```
     * 
     * @uxon-property help_button
     * @uxon-type \exface\Core\Widgets\Button
     * @uxon-template {"action": {"alias": "exface.Core.ShowHelpDialog", "widget": {"":""}}}
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHelpButton()
     */
    public function setHelpButton(UxonObject $uxon) : iHaveContextualHelp
    {
        $this->help_button_uxon = $uxon;
        $this->setHideHelpButton(false);
        return $this;
    }
    
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton($default = false) : ?bool
    {
        return $this->hide_help_button ?? $default;
    }
    
    /**
     * Set to TRUE to remove the contextual help button.
     *
     * @uxon-property hide_help_button
     * @uxon-type boolean
     * @uxon-default false
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHideHelpButton()
     */
    public function setHideHelpButton(bool $value) : iHaveContextualHelp
    {
        $this->hide_help_button = $value;
        return $this;
    }
    
    /**
     * Creates a DataTable for the exface.Core.USER_HELP_ELEMENT object with row grouping over GROUP attribute.
     * 
     * @param iContainOtherWidgets $help_container
     * @return DataTable
     */
    protected function getHelpTable(iContainOtherWidgets $help_container) : DataTable
    {
        /**
         *
         * @var DataTable $table
         */
        $table = WidgetFactory::create($help_container->getPage(), 'DataTableResponsive', $help_container);
        $object = $this->getWorkbench()->model()->getObject('exface.Core.USER_HELP_ELEMENT');
        $table->setMetaObject($object);
        $table->setCaption($this->getWidgetType() . ($this->getCaption() ? ' "' . $this->getCaption() . '"' : ''));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('TITLE')));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('DESCRIPTION')));
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setNowrap(false);
        $table->setRowGrouper(UxonObject::fromArray(array(
            'group_by_attribute_alias' => 'GROUP',
            'hide_caption' => true
        )));
        
        // IMPORTANT: make sure the help table does not have a help button itself, because that would result in having
        // infinite children!
        $table->setHideHelpButton(true);
        return $table;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container) : iContainOtherWidgets
    {        
        return $help_container;
    }
    
    /**
     * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
     * the given attribute.
     * The inforation is derived from the attributes meta model.
     *
     * @param MetaAttributeInterface $attr
     * @return string[]
     */
    protected function getHelpDataRowFromAttribute(MetaAttributeInterface $attr, WidgetInterface $widget = null) : array
    {
        $row = [];
        $descr = $attr->getShortDescription() ? rtrim(trim($attr->getShortDescription()), ".") . '.' : '';
        
        
        if (! $attr->getRelationPath()->isEmpty()) {
            $descr .= $attr->getObject()->getShortDescription() ? ' ' . rtrim($attr->getObject()->getShortDescription(), ".") . '.' : '';
        }
        
        if ($widget !== null && ($widget instanceof iTakeInput) && $widget->isDisabled() === false) {
            if ($dataTypeHint = $attr->getDataType()->getInputFormatHint()) {
                $descr .= ($descr ? "\n\n" : '') . $this->translate('LOCALIZATION.DATATYPE.FORMAT_HINT') . $dataTypeHint;
            }
            
            if ($widget->isRequired() === true) {
                $descr .= ($descr ? "\n\n" : '') . $this->translate('WIDGET.INPUT.REQUIRED_HINT');
            }
        }
        
        $row['DESCRIPTION'] = $descr;
        return $row;
    }
}