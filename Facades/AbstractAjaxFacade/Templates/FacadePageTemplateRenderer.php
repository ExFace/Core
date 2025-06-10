<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Templates;

use exface\Core\Exceptions\TemplateRenderer\TemplateRendererRuntimeError;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Templates\BracketHashFileTemplateRenderer;
use exface\Core\Templates\Placeholders\WidgetRenderPlaceholders;
use exface\Core\Templates\Placeholders\UrlPlaceholders;
use exface\Core\Templates\Placeholders\UiPagePlaceholders;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\TranslationPlaceholders;
use exface\Core\Templates\Placeholders\SessionPlaceholders;
use exface\Core\Templates\Placeholders\FacadePlaceholders;

/**
 * A generic HTML template renderer working with [#placeholders#].
 * 
 * ## Placeholders supported by default
 * 
 * - `[#~head#]` - replaced by the output of `Facade::buildHtmlHead($widget, true)`
 * - `[#~body#]` - replaced by the output of `Facade::buildHtmlBody($widget)`
 * - `[#~widget:<widget_type>#] - renders a widget, e.g. `[#~widget:NavCrumbs#]`
 * - `[#~url:<page_selector>#]` - replaced by the URL to the page identified by the 
 * `<page_selector>` (i.e. UID or alias with namespace) or to the server adress
 * - `[#~page:<attribute_alias|url>#]` - replaced by the value of a current page's attribute or URL
 * - `[#~config:<app_alias>:<config_key>#]` - replaced by the value of the configuration option
 * - `[#~translate:<app_alias>:<message>#]` - replaced by the message's translation to current locale
 * - `[#~session:<option>#]` - replaced by session option values
 * - `[#~facade:<property>]` - replaced by the value of a current facade's attribute
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadePageTemplateRenderer extends BracketHashFileTemplateRenderer
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $widget = null;
    
    private $facade = null;
    
    /**
     * 
     * @param AbstractAjaxFacade $facade
     * @param string $templateFilePath
     * @param WidgetInterface $widget
     */
    public function __construct(AbstractAjaxFacade $facade, WidgetInterface $widget = null)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
        $this->widget = $widget;
        $this->initPlaceholders();
    }

    /**
     * {@inheritDoc}
     * @see BracketHashFileTemplateRenderer::render()
     */
    public function render($tplPath = null, ?LogBookInterface $logbook = null)
    {
        // Do not throw detailed errors containing the tempaltes. They produce template tabs in a lot of errors, that
        // have nothing to do with template renderers.
        try {
            return parent::render($tplPath, $logbook);
        } catch (TemplateRendererRuntimeError $e) {
            if (null !== $prev = $e->getPrevious()) {
                throw $prev;
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }
    
    /**
     * 
     * @return UiPageInterface
     */
    protected function getPage() : UiPageInterface
    {
        return $this->getWidget()->getPage();
    }
    
    /**
     * 
     * @return WidgetInterface
     */
    protected function getWidget() : WidgetInterface
    {
        return $this->widget;
    }
    
    /**
     * @return void
     */
    protected function initPlaceholders()
    {
        $this->addPlaceholder(new HtmlFacadePlaceholders($this->getFacade(), $this->getWidget()));
        $this->addPlaceholder(new WidgetRenderPlaceholders($this->getFacade(), $this->getPage(), '~widget:'));
        $this->addPlaceholder(new UrlPlaceholders($this->getFacade(), '~url'));
        $this->addPlaceholder(new UiPagePlaceholders($this->getPage(), $this->getFacade(), '~page:'));
        $this->addPlaceholder(new ConfigPlaceholders($this->getWorkbench(), '~config:'));
        $this->addPlaceholder(new TranslationPlaceholders($this->getWorkbench(), '~translate:'));
        $this->addPlaceholder(new SessionPlaceholders($this->getWorkbench(), '~session:'));
        $this->addPlaceholder(new FacadePlaceholders($this->getFacade(), '~facade:'));
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Templates\BracketHashFileTemplateRenderer::getPlaceholderValues()
     */
    protected function getPlaceholderValues(array $placeholders, ?LogBookInterface $logbook = null) : array
    {
        $phVals = parent::getPlaceholderValues($placeholders, $logbook);
        
        $nullVals = array_filter($phVals, function($val) {
            return $val === null;
        });
        if (! empty($nullVals)) {
            throw new FacadeOutputError('No value found for placeholder(s) "[#' . implode('#]", "[#', array_keys($nullVals)) . '#]" in template "' . $this->getTemplateFilePath() . '"!');
        }
        
        return $phVals;
    }
    
    /**
     * 
     * @return AbstractAjaxFacade
     */
    protected function getFacade() : AbstractAjaxFacade
    {
        return $this->facade;
    }
}