<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\CommonLogic\Constants\Icons;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Widgets\iHaveHeader;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\Traits\iHaveToolbarsTrait;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;

/**
 * Dialogs are pop-up forms (i.e. windows), that can be moved and/or maximized.
 * 
 * @author Andrej Kabachnik
 */
class Dialog extends Form implements iAmClosable, iHaveHeader
{
    private $hide_close_button = false;

    private $close_button = null;

    private $maximizable = true;

    private $maximized = null;
    
    private $header = null;
    
    private $hide_header = null;

    protected function init()
    {
        parent::init();
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
            if ($this->isEmpty() === false && $container = $widget->getAlternativeContainerForOrphanedSiblings()) {
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
     * @uxon-property hide_close_button
     * @uxon-type boolean
     * @uxon-default false
     *
     * @param boolean $value 
     * @return Dialog           
     */
    public function setHideCloseButton($value)
    {
        $this->hide_close_button = $value;
        if ($this->close_button !== null) {
            $this->close_button->setHidden($value);
        }
        return $this;
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
            $btn->setIcon(Icons::TIMES);
            $btn->setCaption($this->translate('WIDGET.DIALOG.CLOSE_BUTTON_CAPTION'));
            $btn->setAlign(EXF_ALIGN_OPPOSITE);
            if ($this->getHideCloseButton()) {
                $btn->setHidden(true);
            }
            $this->close_button = $btn;
        }
        return $this->close_button;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getToolbarWidgetType()
     */
    public function getToolbarWidgetType()
    {
        return 'DialogToolbar';
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return 'DialogButton';
    }

    /**
     * Returns a Container widget with all widgets the dialog contains.
     * This is usefull for lazy loading dialog contents,
     * where only the widgets need to be rendered, not the dialog itself. The container is not a regular part of the dialog.
     * It only gets created if this method is called. It is not added to the dialog, so it will not get listed by get_children(),
     * etc.
     *
     * When lazy loading the contents of the dialog, it is important to let the facade render all contained widgets
     * at once (i.e. draw this container). If we draw each widget individually, the respective facade elements will get
     * instantiated one after another, so those instatiated first, can't access the ones instantiated later on. Putting
     * everything in a container makes the facade instatiate all elements before actually drawing them!
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

    /**
     * Set to FALSE to prevent maximization of the dialog.
     * 
     * @uxon-property maximizable
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param boolean|string $value
     * @return \exface\Core\Widgets\Dialog
     */
    public function setMaximizable($value)
    {
        $this->maximizable = BooleanDataType::cast($value);
        return $this;
    }

    public function isMaximized()
    {
        return $this->maximized;
    }

    /**
     * Makes the dialog open maximized (TRUE) or regular (FALSE).
     * 
     * The default behavior depends on the facade.
     * 
     * @uxon-property maximized
     * @uxon-type boolean
     * 
     * @param boolean|string $value
     * @return \exface\Core\Widgets\Dialog
     */
    public function setMaximized($value)
    {
        $this->maximized = BooleanDataType::cast($value);
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\Container::findChildrenByAttribute()
     */
    public function findChildrenByAttribute(MetaAttributeInterface $attribute)
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
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveContextualHelp::getHelpButton()
     */
    public function getHelpButton() : iTriggerAction
    {
        $button = parent::getHelpButton();
        $button->setCloseDialogAfterActionSucceeds(false);
        return $button;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getChildren()
     */
    public function getChildren() : \Iterator
    {
        foreach(parent::getChildren() as $child) {
            yield $child;
        }
        
        if (! $this->getHideHeader() && $this->hasHeader()) {
            yield $this->getHeader();
        }
        
        yield $this->getCloseButton();
    }
    
    /**
     * Defines the DialogHeader widget.
     * 
     * @uxon-property header
     * @uxon-type \exface\Core\Widgets\DialogHeader
     * @uxon-template {"widgets": [{"": ""}]}
     * 
     * @param UxonObject|DialogHeader $uxon_or_widget
     * @throws WidgetConfigurationError
     * @return \exface\Core\Widgets\Dialog
     */
    public function setHeader($uxon_or_widget)
    {
        if ($uxon_or_widget instanceof UxonObject) {
            $this->header = WidgetFactory::createFromUxon($this->getPage(), $uxon_or_widget, $this, 'DialogHeader');
        } elseif ($uxon_or_widget instanceof DialogHeader) {
            $this->header = $uxon_or_widget;
        } else {
            throw new WidgetConfigurationError($this, 'Invalid definiton of panel header given!');
        }
        return $this;
    }
    
    public function getHeader()
    {
        return $this->header;
    }
    
    public function hasHeader()
    {
        return is_null($this->header) ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::getHideHeader()
     */
    public function getHideHeader() : ?bool
    {
        return $this->hide_header;
    }
    
    /**
     * Forces the dialog header to hide (TRUE) or show (FALSE)
     * 
     * By default, the facade decides, if the header should be shown or not.
     * 
     * @uxon-property hide_header
     * @uxon-type boolean
     * 
     * @see \exface\Core\Interfaces\Widgets\iHaveHeader::setHideHeader()
     */
    public function setHideHeader(bool $boolean) : iHaveHeader
    {
        $this->hide_header = $boolean;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getHideHelpButton()
     */
    public function getHideHelpButton($default = false) : ?bool
    {
        return $this->getHideHelpButtonViaTrait($default);
    }
    
    /**
     * Add buttons to the dialog
     * 
     * @uxon-property buttons
     * @uxon-type \exface\Core\Widgets\DialogButton[]
     * @uxon-template [{"action_alias": ""}]
     * 
     * @see iHaveToolbarsTrait::setButtons()
     */
    public function setButtons($uxonOrArray)
    {
        return parent::setButtons($uxonOrArray);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getHelpData()
     */
    protected function getHelpData(array $widgets, DataSheetInterface $dataSheet, string $groupName = null) : DataSheetInterface
    {
        $dataSheet = parent::getHelpData($widgets, $dataSheet, $groupName);
        if ($this->hasHeader() && $widgets == $this->getWidgets()) {
            $headerHelpData = parent::getHelpData($this->getHeader()->getWidgets(), DataSheetFactory::createFromObject($dataSheet->getMetaObject()), 'Header');
            $bodyRows = $dataSheet->getRows();
            $dataSheet->removeRows()->addRows(array_merge($headerHelpData->getRows(), $bodyRows));
        }
        return $dataSheet;
    }
}
?>