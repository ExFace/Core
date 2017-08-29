<?php
namespace exface\Core\CommonLogic;

use exface\Core\Widgets\AbstractWidget;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\UiManagerInterface;
use exface\Core\Factories\TemplateFactory;
use exface\Core\Exceptions\UiPageFoundError;

class UiManager implements UiManagerInterface
{

    private $widget_id_forbidden_chars_regex = '[^A-Za-z0-9_\.]';

    private $loaded_templates = array();

    private $pages = array();

    private $exface = null;

    private $base_template = null;

    private $page_id_current = null;

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
     * IDEA Remove this method from the UI in favor of template::draw() after template handling has been moved to the actions
     *
     * @param AbstractWidget $widget            
     * @param
     *            TemplateInterface ui_template to use when drawing
     * @return string
     */
    function draw(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->draw($widget);
    }

    /**
     * Output document headers, needed for the widget.
     * This could be JS-Includes, stylesheets - anything, that needs to be placed in the
     * resulting document separately from the renderen widget itself.
     * IDEA Remove this method from the UI in favor of template::drawHeaders() after template handling has been moved to the actions
     *
     * @param WidgetInterface $widget            
     * @param
     *            TemplateInterface ui_template to use when drawing
     * @return string
     */
    function drawHeaders(WidgetInterface $widget, TemplateInterface $template = null)
    {
        if (is_null($template))
            $template = $this->getTemplateFromRequest();
        return $template->drawHeaders($widget);
    }

    /**
     * Returns an ExFace widget from a given resource by id
     * Caching is used to store widgets from already loaded pages
     *
     * @param string $widget_id            
     * @param string $page_id            
     * @return WidgetInterface
     */
    function getWidget($widget_id, $page_id)
    {
        $page = $this->getPage($page_id);
        if (! is_null($widget_id)) {
            return $page->getWidget($widget_id);
        } else {
            return $page->getWidgetRoot();
        }
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns the UI page with the given $page_id.
     * If the $page_id is ommitted or =0, the default (initially empty) page is returned.
     *
     * @param string $page_id            
     * @return UiPageInterface
     */
    public function getPage($page_id = null)
    {
        if (! $page_id) {
            $this->pages[$page_id] = UiPageFactory::createEmpty($this);
        } elseif (! $this->pages[$page_id]) {
            $this->pages[$page_id] = UiPageFactory::createFromCmsPage($this, $page_id);
        }
        return $this->pages[$page_id];
    }

    /**
     *
     * @return \exface\Core\Interfaces\Model\UiPageInterface
     */
    public function getPageCurrent()
    {
        return $this->getPage($this->getPageIdCurrent());
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

    public function getPageIdCurrent()
    {
        if (is_null($this->page_id_current)) {
            $this->page_id_current = $this->getWorkbench()->getCMS()->getPageId();
        }
        return $this->page_id_current;
    }

    public function setPageIdCurrent($value)
    {
        $this->page_id_current = $value;
        return $this;
    }
}

?>