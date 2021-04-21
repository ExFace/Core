<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Templates;

use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\CommonLogic\Filemanager;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;

/**
 * A generic HTML template renderer working with [#placeholders#].
 * 
 * ## Supported Placeholders
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
 * - `[#~facade:<attribute_alias>]` - replaced by the value of a current facade's attribute
 * 
 * @author Andrej Kabachnik
 *
 */
class FacadePageTemplateRenderer implements TemplateRendererInterface
{
    use ImportUxonObjectTrait;
    
    private $workbench = null;
    
    private $templateFilePath = null;
    
    private $widget = null;
    
    private $facade = null;
    
    /**
     * 
     * @param AbstractAjaxFacade $facade
     * @param string $templateFilePath
     * @param WidgetInterface $widget
     */
    public function __construct(AbstractAjaxFacade $facade, string $templateFilePath = null, WidgetInterface $widget = null)
    {
        $this->workbench = $facade->getWorkbench();
        $this->facade = $facade;
        if ($templateFilePath !== null) {
            $this->setTemplateFilePath($templateFilePath);
        }
        if ($widget !== null) {
            $this->setWidget($widget);
        }
    }
    
    /**
     *
     * @return string
     */
    protected function getTemplateFilePath() : string
    {
        return $this->templateFilePath;
    }
    
    /**
     * Path to the template file - either absolute or relative to vendor folder.
     * 
     * @uxon-property template_file_path
     * @uxon-type string
     * 
     * @param string $value
     * @return FacadePageTemplateRenderer
     */
    public function setTemplateFilePath(string $value) : FacadePageTemplateRenderer
    {
        if (Filemanager::pathIsAbsolute($value)) {
            $this->templateFilePath = $value;
        } else {
            $this->templateFilePath = $this->getWorkbench()->filemanager()->getPathToVendorFolder() . DIRECTORY_SEPARATOR . $value;
        }
        return $this;
    }
    
    protected function getTemplate() : string
    {
        $absPath = $this->getTemplateFilePath();
        if (file_exists($absPath) === false) {
            throw new RuntimeException('Template file "' . $absPath . '" not found!');
        }
        return file_get_contents($absPath);
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface::render()
     */
    public function render(array $customPlaceholders = []) : string
    {
        $tpl = $this->getTemplate();
        
        $phs = StringDataType::findPlaceholders($tpl);
        $phVals = [];
        foreach ($phs as $ph) {
            if ($customPlaceholders[$ph] !== null) {
                continue;
            }
            $phVals[$ph] = $this->renderPlaceholderValue($ph);
        }
        
        foreach ($customPlaceholders as $ph => $value) {
            $phVals[$ph] = $value;
        }
        
        return StringDataType::replacePlaceholders($tpl, $phVals, false);
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
     * 
     * @param WidgetInterface $widget
     * @return FacadePageTemplateRenderer
     */
    protected function setWidget(WidgetInterface $widget) : FacadePageTemplateRenderer
    {
        $this->widget = $widget;
        return $this;
    }
    
    /**
     * 
     * @param string $placeholder
     * @throws RuntimeException
     * @return string
     */
    protected function renderPlaceholderValue(string $placeholder) : string
    {
        $val = '';
        switch (true) {
            case $placeholder === '~head':
                $val = $this->getFacade()->buildHtmlHead($this->getWidget(), true);
                break;
            case $placeholder === '~body':
                $val = $this->getFacade()->buildHtmlBody($this->getWidget());
                break;
            case StringDataType::startsWith($placeholder, '~widget:') === true;
                $widgetType = StringDataType::substringAfter($placeholder, '~widget:');
                if (StringDataType::startsWith($widgetType, 'Nav') === true) {
                    $uxon = new UxonObject([
                        'object_alias' => 'exface.Core.PAGE'
                    ]);
                }
                $phWidget = WidgetFactory::createFromUxon($this->getPage(), $uxon, null, $widgetType);
                $val = $this->getFacade()->buildHtml($phWidget);
                break;
            case StringDataType::startsWith($placeholder, '~url:') === true;
                $pageSelectorString = StringDataType::substringAfter($placeholder, '~url:');
                if ($pageSelectorString === '') {
                    $val = $this->getFacade()->buildUrlToSiteRoot();
                } else {
                    $val = $this->getFacade()->buildUrlToPage($pageSelectorString);
                }
                break;
            case StringDataType::startsWith($placeholder, '~page:');
                $property = StringDataType::substringAfter($placeholder, '~page:');
                $val = $this->renderPlaceholderPageProperty($property, $this->getPage());
                break;
            case StringDataType::startsWith($placeholder, '~config:');
                $value = StringDataType::substringAfter($placeholder, '~config:');
                list($appAlias, $option) = explode(':', $value);
                $val = $this->getWorkbench()->getApp($appAlias)->getConfig()->getOption(mb_strtoupper($option));
                break;
            case StringDataType::startsWith($placeholder, '~translate:');
                $value = StringDataType::substringAfter($placeholder, '~translate:');
                list($appAlias, $message) = explode(':', $value);
                $val = $this->getWorkbench()->getApp($appAlias)->getTranslator()->translate(mb_strtoupper($message));
                break;
            case StringDataType::startsWith($placeholder, '~session:') === true;
                $option = StringDataType::substringAfter($placeholder, '~session:');
                $val = $this-> renderPlaceholderSessionOption($option);
                break;
            case StringDataType::startsWith($placeholder, '~facade:') === true;
                $option = StringDataType::substringAfter($placeholder, '~facade:');
                $methodName = 'get' . StringDataType::convertCaseUnderscoreToPascal($option);
                if (method_exists($this->getFacade(), $methodName)) {
                    $val = call_user_func([$this->getFacade(), $methodName]);
                    break;
                } 
            default:
                throw new RuntimeException('Unknown placehodler "[#' . $placeholder . '#]" found in template "' . $this->getTemplateFilePath() . '"!');
        }
        return $val;
    }
    
    /**
     * 
     * @param string $property
     * @param UiPageInterface $page
     * @return string
     */
    protected function renderPlaceholderPageProperty(string $property, UiPageInterface $page) : string
    {
        switch ($property) {
            case 'alias':
                $val = $this->getPage()->getAliasWithNamespace();
                break;
            case 'url':
                $val = $this->getFacade()->buildUrlToPage($this->getPage());
                break;
            default:
                $method = 'get' . StringDataType::convertCaseUnderscoreToPascal($property);
                $val = call_user_func([$page, $method]);
        }
        return $val;
    }
    
    protected function renderPlaceholderSessionOption(string $option) : string
    {
        switch ($option) {
            case 'language':
                $locale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
                $val = explode('_', $locale)[0];
                break;
            default:
                $val = '';
        }
        return $val;
        
    }
    
    /**
     * 
     * @return AbstractAjaxFacade
     */
    protected function getFacade() : AbstractAjaxFacade
    {
        return $this->facade;
    }
    
    public function exportUxonObject()
    {
        return new UxonObject([
            'template_file_path' => $this->getTemplateFilePath()
        ]);
    }
}