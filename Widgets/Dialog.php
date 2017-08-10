<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\CommonLogic\Model\Attribute;
use exface\Core\Interfaces\Widgets\iHaveContextualHelp;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\Constants\Icons;

class Dialog extends Form implements iAmClosable, iHaveContextualHelp
{

    private $hide_close_button = false;

    private $close_button = null;

    private $maximizable = true;

    private $maximized = false;

    private $help_button = null;

    private $hide_help_button = false;

    protected function init()
    {
        parent::init();
        $this->setLazyLoading(true);
        $this->getToolbarMain()->addButton($this->getCloseButton());
    }

    /**
     * Adds a widget to the dialog.
     * Widgets, that fill a container completely, will be added as the only child of the
     * dialog, while any already present children will be moved to the filling widget automatically. Thus, if a panel
     * or the tabs widget are added, they will be the only child of the dialog and will wrap all other widgets within
     * it - even if those other children were added earlier!
     *
     * @see Panel::addWidget()
     */
    public function addWidget(AbstractWidget $widget, $position = NULL)
    {
        if ($widget instanceof iFillEntireContainer) {
            if ($container = $widget->getAlternativeContainerForOrphanedSiblings()) {
                foreach ($this->getWidgets() as $w) {
                    $container->addWidget($w);
                }
                parent::removeWidgets();
            }
        }
        return parent::addWidget($widget, $position);
    }

    /**
     * If TRUE, the automatically generated close button for the dialog is not shown
     *
     * @return boolean
     */
    public function getHideCloseButton()
    {
        return $this->hide_close_button;
    }

    /**
     * If set to TRUE, the automatically generated close button will not be shown in this dialog
     *
     * @param boolean $value            
     */
    public function setHideCloseButton($value)
    {
        $this->hide_close_button = $value;
    }

    /**
     * Returns a special dialog button, that just closes the dialog without doing any other action
     *
     * @return \exface\Core\Widgets\DialogButton
     */
    public function getCloseButton()
    {
        if (! ($this->close_button instanceof DialogButton)) {
            /* @var $btn DialogButton */
            $btn = $this->createButton();
            $btn->setCloseDialogAfterActionSucceeds(true);
            $btn->setRefreshInput(false);
            $btn->setIconName(Icons::TIMES);
            $btn->setCaption($this->translate('WIDGET.DIALOG.CLOSE_BUTTON_CAPTION'));
            $btn->setAlign(EXF_ALIGN_OPPOSITE);
            if ($this->getHideCloseButton())
                $btn->setHidden(true);
            $this->close_button = $btn;
        }
        return $this->close_button;
    }
    
    public function getToolbarWidgetType()
    {
        return 'DialogToolbar';
    }

    /**
     * Returns a Container widget with all widgets the dialog contains.
     * This is usefull for lazy loading dialog contents,
     * where only the widgets need to be rendered, not the dialog itself. The container is not a regular part of the dialog.
     * It only gets created if this method is called. It is not added to the dialog, so it will not get listed by get_children(),
     * etc.
     *
     * When lazy loading the contents of the dialog, it is important to let the template draw() all contained widgets
     * at once (i.e. draw this container). If we draw each widget individually, the respective template elements will get
     * instantiated one after another, so those instatiated first, can't access the ones instantiated later on. Putting
     * everything in a container makes the template instatiate all elements before actually drawing them!
     *
     * @return Container
     */
    public function getContentsContainer()
    {
        $container = $this->getPage()->createWidget('Container', $this);
        foreach ($this->getWidgets() as $w) {
            $container->addWidget($w);
        }
        return $container;
    }

    public function isMaximizable()
    {
        return $this->maximizable;
    }

    public function setMaximizable($value)
    {
        $this->maximizable = BooleanDataType::parse($value);
        return $this;
    }

    public function isMaximized()
    {
        return $this->maximized;
    }

    public function setMaximized($value)
    {
        $this->maximized = BooleanDataType::parse($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::findChildrenByAttribute()
     */
    public function findChildrenByAttribute(Attribute $attribute)
    {
        // If the container has a single filling child, which is a container itself, search that child
        if ($this->countWidgets() == 1) {
            $widgets = $this->getWidgets();
            $first_widget = reset($widgets);
            if ($first_widget instanceof iFillEntireContainer && $first_widget instanceof iContainOtherWidgets) {
                return $first_widget->findChildrenByAttribute($attribute);
            }
        }
        return parent::findChildrenByAttribute($attribute);
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = parent::exportUxonObject();
        // TODO add properties specific to this widget here
        return $uxon;
    }

    public function getHelpButton()
    {
        if (is_null($this->help_button)) {
            $this->help_button = WidgetFactory::create($this->getPage(), $this->getButtonWidgetType(), $this);
            $this->help_button->setActionAlias('exface.Core.ShowHelpDialog');
            $this->help_button->setCloseDialogAfterActionSucceeds(false);
        }
        return $this->help_button;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpWidget()
     */
    public function getHelpWidget(iContainOtherWidgets $help_container)
    {
        /**
         *
         * @var DataTable $table
         */
        $table = WidgetFactory::create($help_container->getPage(), 'DataTable', $help_container);
        $object = $this->getWorkbench()->model()->getObject('exface.Core.USER_HELP_ELEMENT');
        $table->setMetaObject($object);
        $table->setCaption($this->getWidgetType() . ($this->getCaption() ? '"' . $this->getCaption() . '"' : ''));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('TITLE')));
        $table->addColumn($table->createColumnFromAttribute($object->getAttribute('DESCRIPTION')));
        $table->setLazyLoading(false);
        $table->setPaginate(false);
        $table->setNowrap(false);
        // $table->setGroupRows(UxonObject::fromArray(array('group_by_column_id' => 'GROUP')));
        
        // IMPORTANT: make sure the help table does not have a help button itself, because that would result in having
        // infinite children!
        $table->setHideHelpButton(true);
        
        $data_sheet = DataSheetFactory::createFromObject($object);
        
        foreach ($this->getInputWidgets() as $widget) {
            if ($widget->isHidden())
                continue;
            $row = array(
                'TITLE' => $widget->getCaption()
            );
            if ($widget instanceof iShowSingleAttribute && $attr = $widget->getAttribute()) {
                $row = array_merge($row, $this->getHelpRowFromAttribute($attr));
            }
            $data_sheet->addRow($row);
        }
        
        $table->prefill($data_sheet);
        
        $help_container->addWidget($table);
        return $help_container;
    }

    /**
     * Returns a row (assotiative array) for a data sheet with exface.Core.USER_HELP_ELEMENT filled with information about
     * the given attribute.
     * The inforation is derived from the attributes meta model.
     *
     * @param Attribute $attr            
     * @return string[]
     */
    protected function getHelpRowFromAttribute(Attribute $attr)
    {
        $row = array();
        $row['DESCRIPTION'] = $attr->getShortDescription() ? rtrim(trim($attr->getShortDescription()), ".") . '.' : '';
        
        if (! $attr->getRelationPath()->isEmpty()) {
            $row['DESCRIPTION'] .= $attr->getObject()->getShortDescription() ? ' ' . rtrim($attr->getObject()->getShortDescription(), ".") . '.' : '';
        }
        return $row;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHideHelpButton()
     */
    public function getHideHelpButton()
    {
        if (! $this->hide_help_button && count($this->getInputWidgets()) == 0) {
            $this->hide_help_button = true;
        }
        return $this->hide_help_button;
    }

    /**
     * Set to TRUE to remove the contextual help button.
     * Default: FALSE.
     *
     * @uxon-property hide_help_button
     * @uxon-type boolean
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::setHideHelpButton()
     */
    public function setHideHelpButton($value)
    {
        $this->hide_help_button = BooleanDataType::parse($value);
        return $this;
    }

    public function getChildren()
    {
        $children = parent::getChildren();
        
        // Add the help button, so pages will be able to find it when dealing with the ShowHelpDialog action.
        // IMPORTANT: Add the help button to the children only if it is not hidden. This is needed to hide the button in
        // help widgets themselves, because otherwise they would produce their own help widgets, with - in turn - even
        // more help widgets, resulting in an infinite loop.
        if (! $this->getHideHelpButton()) {
            $children[] = $this->getHelpButton();
        }
        return $children;
    }
}
?>