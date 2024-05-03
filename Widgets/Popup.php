<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Widgets\Traits\PopupTrait;
use exface\Core\Interfaces\Widgets\iAmClosable;

/**
 * Dialogs are pop-up forms (i.e. windows), that can be moved and/or maximized.
 * 
 * A dialog MUST be opened via action (in most cases, pressing a button with `exface.Core.ShowDialog` 
 * or a derivative). 
 * 
 * A dialog will mostly also contain buttons itself. These special `DialogButton`s can close their
 * parent dialog automatically or leave it open (`close_dialog`). This way, stacks of open dialogs
 * can be created.
 * 
 * ## Lazy loading
 * 
 * Most facades will send a server request when the open-button is pressed to lazy load the dialog, however 
 * this behavior can be explicitly toggled by setting `lazy_loading:false` for a specific dialog. These
 * dialogs will still need a button to open, but they will be definitely rendered together with the
 * button itself - regardless of the default rendering approach of the facade.
 * 
 * @author Andrej Kabachnik
 */
class Popup extends Form implements iAmClosable
{
    use PopupTrait {
        createCloseButton as createCloseButtonViaTrait;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\AbstractWidget::init()
     */
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
     * Returns a special dialog button, that just closes the dialog without doing any other action
     *
     * @return \exface\Core\Widgets\DialogButton
     */
    public function createCloseButton()
    {
        $btn = $this->createCloseButtonViaTrait();
        $btn->setCaption($this->translate('WIDGET.POPUP.CLOSE_BUTTON_CAPTION'));
        $btn->setShowIcon(false);
        return $btn;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Form::getChildren()
     */
    public function getChildren() : \Iterator
    {        
        yield $this->getCloseButton();
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
}