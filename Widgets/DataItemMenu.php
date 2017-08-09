<?php
namespace exface\Core\Widgets;

/**
 * A special menu to be used in data widgets to create context-menus.
 * 
 * In addition to a regular menu, it contains options to automatically import
 * buttons from the data widget and global actions for the underlying meta
 * object.
 *
 * @author Andrej Kabachnik
 *        
 */
class DataItemMenu extends Menu
{
    /**
     * 
     * @return Data
     */
    public function getDataWidget()
    {
        return $this->getInputWidget();
    } 
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Toolbar::getButtons()
     */
    public function getButtons(callable $filter_callback = null)
    {
        if (!parent::hasButtons()){
            foreach ($this->getDataWidget()->getToolbars() as $toolbar){
                foreach ($toolbar->getButtonGroups() as $group){
                    $this->addButtonGroup($group->copy());
                }
            }
        }
        return parent::getButtons($filter_callback);
    }
}
?>