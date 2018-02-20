<?php
namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Factories\TemplateFactory;

class UiManager implements UiManagerInterface
{

    private $widget_id_forbidden_chars_regex = '[^A-Za-z0-9_\.]';

    private $loaded_templates = array();

    private $exface = null;

    private $base_template = null;

    private $page_current = null;

    function __construct(\exface\Core\CommonLogic\Workbench $exface)
    {
        $this->exface = $exface;
    }

    /**
     * Returns a template instance for a given template alias.
     * If no alias given, returns the current template.
     * 
     * @param string $template
     * @return AbstractTemplate
     */
    function getTemplate($template = null)
    {
        if (! $template)
            return $this->getTemplateFromRequest();
        
        if (! $instance = $this->loaded_templates[$template]) {
            $instance = TemplateFactory::createFromString($template, $this->exface);
            $this->loaded_templates[$template] = $instance;
        }
        
        return $instance;
    }

    /**
     * Output the final UI code for a given widget
     * IDEA Remove this method from the UI in favor of template::buildWidget() after template handling has been moved to the actions
     * 
     * @param AbstractWidget $widget
     * @param TemplateInterface $template ui_template to use when drawing
     * @return string
     */
    function buildWidget(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->buildWidget($widget);
    }

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
    function buildIncludes(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->buildIncludes($widget);
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the UI page with the given $page_alias.
     * If the $page_alias is ommitted or ='', the default (initially empty) page is returned.
     * 
     * @param string $page_alias
     * @return UiPageInterface
     */
    public function getPage($page_alias = null)
    {
        return UiPageFactory::createFromCmsPage($this, $page_alias);
    }

    /**
     * 
     * @return UiPageInterface
     */
    public function getPageCurrent()
    {
        if (is_null($this->page_current)) {
            $this->page_current = UiPageFactory::createFromCmsPageCurrent($this);
        }
        return $this->page_current;
    }

    /**
     * 
     * @param UiPageInterface $pageCurrent
     * @return UiManager
     */
    public function setPageCurrent(UiPageInterface $pageCurrent)
    {
        $this->page_current = $pageCurrent;
        return $this;
    }

    public function getTemplateFromRequest()
    {
        if (is_null($this->base_template)) {
            // $this->base_template = $this->getTemplate($this->getWorkbench()->getConfig()->getOption('TEMPLATES.DEFAULT_UI_TEMPLATE'));
            $this->base_template = $this->getWorkbench()->getConfig()->getOption('TEMPLATES.DEFAULT_UI_TEMPLATE');
        }
        return $this->getTemplate($this->base_template);
    }

    public function setBaseTemplateAlias($qualified_alias)
    {
        $this->base_template = $qualified_alias;
        return $this;
    }
}

?>