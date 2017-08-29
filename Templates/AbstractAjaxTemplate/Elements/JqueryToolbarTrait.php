<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Widgets\Toolbar;

/**
 * This is the reference implementation of the Toolbar widget for jQuery templates.
 * 
 * In a nutshell, it is a <div> with all the button groups one-after-another.
 * 
 * Optional button groups are moved to automatically created menu buttons (i.e.
 * more-actions-menus) at the end of the preceding visible button group. This,
 * of course, will only work, if the JqueryButtonGroupTrait is used as well.
 * 
 * @method Toolbar getWidget()
 * 
 * @author Andrej Kabachnik
 *
 */
trait JqueryToolbarTrait 
{
    protected function init()
    {
        $toolbar = $this->getWidget();
        // See if the toolbar has only optional button groups, which would
        // only produce a menu button. If so, we could potentially have multiple
        // menu buttons next to each other without any difference. To avoid this,
        // we will append optional button groups to the preceding visible button
        // group with the same alignment. Thus, if you have multiple visible
        // groups with optional ones in between, the optional buttons will allways
        // be appended to their preceding visible neighbour.
        foreach ($this->getWidget()->getButtonGroups() as $grp){
            if ($grp->getVisibility() === EXF_WIDGET_VISIBILITY_OPTIONAL){
                $grp_idx = $toolbar->getButtonGroupIndex($grp);
                // Find the first preceding button group: look back through the
                // toolbar to find a group that is neither hidden nor optional
                // and has the same alignment as our group (unless ours does not
                // have an alignment at all.
                do {
                    $grp_idx--;
                    try {
                        $prev_grp = $toolbar->getButtonGroup($grp_idx);
                    } catch (\exface\Core\Exceptions\Widgets\WidgetChildNotFoundError $e){
                        $prev_grp = null;
                    }
                } while (
                    !is_null($prev_grp) 
                    && !is_null($grp->getAlign()) 
                    && $prev_grp->isHidden() 
                    && $prev_grp->getVisibility() === EXF_WIDGET_VISIBILITY_OPTIONAL 
                    && $prev_grp->getAlign() !== $grp->getAlign()
                );
                
                // If a neighbour group was found, include this group in it's menu.
                // If nothing was found, leave this group as it is and it will
                // produce a single menu button.
                if ($prev_grp && $grp !== $prev_grp){
                    $toolbar->removeButtonGroup($grp);
                    $this->getTemplate()->getElement($prev_grp)->getMoreButtonsMenu()->getMenu()->addButtonGroup($grp);
                }
            } else {
                $this->getTemplate()->getElement($grp)->moveButtonsToMoreButtonsMenu(EXF_WIDGET_VISIBILITY_OPTIONAL, EXF_WIDGET_VISIBILITY_OPTIONAL);
            }
        }
        return;
    }
    
    /**
     * 
     * @return string
     */
    protected function buildHtmlButtons(){
        $button_html = '';
        foreach ($this->getWidget()->getButtonGroups() as $grp){
            $button_html .= $this->getTemplate()->getElement($grp)->generateHtml();
        }
        return $button_html;
    }
    
    /**
     * Returns the JS needed for all buttons in this widget
     * @return string
     */
    protected function buildJsButtons(){
        $output = '';
        foreach ($this->getWidget()->getButtons() as $button) {
            $output .= $this->getTemplate()->generateJs($button);
        }
        return $output;
    }
    
    public function generateJs()
    {
        return $this->buildJsButtons();
    }
    
    public function generateHtml()
    {
        return $this->buildHtmlToolbarWrapper($this->buildHtmlButtons());
    }
    
    protected function buildHtmlToolbarWrapper($contents)
    {
        return <<<HTML
        
            <div class="exf-toolbar">
                {$contents}
            </div>
            
HTML;
    }
}
