<?php
namespace exface\Core\Interfaces;

interface UiManagerInterface extends ExfaceClassInterface
{

    /**
     * Output the final UI code for a given widget
     * IDEA Remove this method from the UI in favor of template::buildWidget() after template handling has been moved to the actions
     * 
     * @param WidgetInterface $widget            
     * @param TemplateInterface $template ui_template to use when drawing
     * @return string
     */
    function buildWidget(WidgetInterface $widget, TemplateInterface $template = null);

    /**
     * Output document headers, needed for the widget.
     * This could be JS-Includes, stylesheets - anything, that needs to be placed in the
     * resulting document separately from the renderen widget itself.
     * IDEA Remove this method from the UI in favor of template::buildIncludes() after template handling has been moved to the actions
     * 
     * @param WidgetInterface $widget            
     * @param TemplateInterface $template ui_template to use when drawing
     * @return string
     */
    function buildIncludes(WidgetInterface $widget, TemplateInterface $template = null);

    /**
     * 
     * @return TemplateInterface
     */
    public function getTemplateFromRequest();
}

?>