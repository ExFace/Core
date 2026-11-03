<?php
namespace exface\Core\Widgets;

use exface\Core\Actions\ShowWidget;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\Factories\ActionFactory;
use exface\Core\Interfaces\Actions\iShowWidget;
use exface\Core\Interfaces\Model\UiScreenInterface;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\Interfaces\Widgets\iTriggerAction;
use exface\Core\Widgets\Traits\PopupTrait;
use exface\Core\Interfaces\Widgets\iAmClosable;
use exface\Core\Interfaces\Widgets\iHaveFooter;
use exface\Core\DataTypes\BooleanDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;

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
class Popup extends Form implements UiScreenInterface, iAmClosable, iHaveFooter
{
    public const SHOW_ON_CLICK = 'click';
    public const SHOW_ON_HOVER = 'hover';

    use PopupTrait {
        createCloseButton as createCloseButtonViaTrait;
    }

    /**
     * @var bool|null
     */
    private $hide_footer = false;

    private string $showOn = self::SHOW_ON_CLICK;

    /**
     * Returns the interaction that opens the popup, defaulting to click.
     *
     * @return string
     */
    public function getShowOn() : string
    {
        return $this->showOn;
    }

    /**
     * Choose whether to show the popup on click or hover.
     * With `click`, click the triggering element to open the popup and click outside to close it.
     * With `hover`, move the pointer over the triggering element to open the popup. It remains
     * usable while the pointer is over that element or the popup and closes after leaving both.
     * 
     * INFO: 'click' is the default interaction. The 'hover' however is currently not supported everywhere.
     * The triggering widget and facade must support the selected interaction.
     * 
     * @uxon-property show_on
     * @uxon-type [click,hover]
     * @uxon-default click
     *
     * @param string $value
     * @return Popup
     */
    public function setShowOn(string $value) : Popup
    {
        $value = mb_strtolower($value);
        switch ($value) {
            case self::SHOW_ON_CLICK:
            case self::SHOW_ON_HOVER:
                $this->showOn = $value;
                return $this;
        }

        throw new WidgetConfigurationError($this, 'Invalid popup show_on value "' . $value . '": please use click or hover!');
    }

    /**
     * Returns whether the entire popup footer is hidden.
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFooter::getHideFooter()
     */
    public function getHideFooter() : ?bool
    {
        return $this->hide_footer;
    }

    /**
     * Set to TRUE to hide the entire footer, including the close button and all other footer actions.
     * The popup can still be closed by clicking outside it. Buttons inside the popup content are not affected.
     * @uxon-property hide_footer
     * @uxon-type boolean
     * @uxon-default false
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveFooter::setHideFooter()
     * @param bool $value
     * @return iHaveFooter
     */
    public function setHideFooter($value) : iHaveFooter
    {
        $this->hide_footer = BooleanDataType::cast($value);
        return $this;
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
        yield from parent::getChildren();
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
    
    /**
     * {@inheritDoc}
     * @see UiScreenInterface::getUrlSlug()
     */
    public function getUrlSlug() : string
    {
        $parent = $this->getParent();
        if (($parent instanceof iTriggerAction) && $parent->getAction() instanceof iShowWidget) {
            return $parent->getAction()->getAliasWithNamespace();
        } else {
            return $this->getPage()->getAliasWithNamespace();
        }
    }
    
    /**
     * {@inheritDoc}
     * @see UiScreenInterface::getName()
     */
    public function getName(): string
    {
        return $this->getCaption();
    }

    /**
     * {@inheritDoc}
     * @see UiScreenInterface::getDescription()
     */
    public function getDescription(): ?string
    {
        $btn = $this->getParent();
        if ($btn instanceof iTriggerAction) {
            if (null !== $action = $btn->getAction()) {
                return $action->getHint();
            }
        }
        return $this->getHint(false);
    }
}