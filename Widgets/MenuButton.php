<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveMenu;
use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\Widgets\WidgetPropertyInvalidValueError;
use exface\Core\CommonLogic\UxonObject;

/**
 * A button with a menu, containing other buttons.
 *
 * If the MenuButton itself does not have an action, pressing it will merely open the menu. With an action defined,
 * the MenuButton will have actually two functions: performing the action and opening the menu with other buttons.
 * In the latter case, most templates will render a split button with a larger area for the action and a smaller
 * area for the menu - e.g. multitool buttons with little triangles on the right in MS Word, Photoshop, etc.
 *
 * @author Andrej Kabachnik
 *        
 */
class MenuButton extends Button implements iHaveMenu, iHaveButtons
{

    /** @var Menu $menu */
    private $menu = null;

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveMenu::getMenu()
     */
    public function getMenu()
    {
        if (is_null($this->menu)) {
            $page = $this->getPage();
            $this->setMenu(WidgetFactory::create($page, 'Menu', $this));
        }
        return $this->menu;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveMenu::setMenu()
     */
    public function setMenu($menu_widget_or_uxon_or_array)
    {
        if ($menu_widget_or_uxon_or_array instanceof Menu) {
            $this->menu = $menu_widget_or_uxon_or_array;
            $this->menu->setParent($this);
        } elseif (is_array($menu_widget_or_uxon_or_array)) {
            $this->getMenu()->setButtons($menu_widget_or_uxon_or_array);
        } elseif ($menu_widget_or_uxon_or_array instanceof \stdClass) {
            $this->getMenu()->importUxonObject($menu_widget_or_uxon_or_array);
        } else {
            throw new WidgetPropertyInvalidValueError($this, 'Invalid menu configuration for MenuButton "' . $this->getId() . '"!');
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtons()
     */
    public function getButtons(callable $filter_callback = null)
    {
        return $this->getMenu()->getButtons($filter_callback);
    }

    /**
     * Defines the buttons in the menu via array of button definitions.
     *
     * @uxon-property buttons
     * @uxon-type Button[]
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::setButtons()
     */
    public function setButtons(array $buttons_array)
    {
        return $this->getMenu()->setButtons($buttons_array);
    }

    /**
     * Adds a button to the group
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::addButton()
     */
    public function addButton(Button $button_widget, $index = null)
    {
        $this->getMenu()->addButton($button_widget, $index);
        return $this;
    }

    /**
     * Removes a button from the group
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::removeButton()
     */
    public function removeButton(Button $button_widget)
    {
        $this->getMenu()->removeButton($button_widget);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @see \exface\Core\Widgets\AbstractWidget::getChildren()
     */
    public function getChildren()
    {
        return array_merge(parent::getChildren(), array(
            $this->getMenu()
        ));
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::hasButtons()
     */
    public function hasButtons()
    {
        if ($this->getMenu()->hasButtons()) {
            return true;
        }
        return false;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonWidgetType()
     */
    public function getButtonWidgetType()
    {
        return $this->getMenu()->getButtonWidgetType();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::countButtons()
     */
    public function countButtons(callable $filter_callback = null)
    {
        return $this->getMenu()->countButtons($filter_callback);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButtonIndex()
     */
    public function getButtonIndex(Button $widget)
    {
        return $this->getMenu()->getButtonIndex($widget);    
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::getButton()
     */
    public function getButton($index)
    {
        return $this->getMenu()->getButton($index);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iHaveButtons::createButton()
     */
    public function createButton(UxonObject $uxon = null)
    {
        return $this->getMenu()->createButton($uxon);
    }
}
?>