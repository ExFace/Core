<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iHaveButtons;
use exface\Core\Widgets\Traits\iHaveButtonsAndToolbarsTrait;
use exface\Core\Interfaces\Widgets\iHaveToolbars;

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
}
?>