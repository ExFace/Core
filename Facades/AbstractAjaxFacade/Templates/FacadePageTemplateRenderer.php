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
    public function render() : string
    {
        $tpl = $this->getTemplate();
        
        $phs = StringDataType::findPlaceholders($tpl);
        $phVals = [];
        foreach ($phs as $ph) {
            $phVals[$ph] = $this->renderPlaceholderValue($ph);
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
            
                break;
            case StringDataType::startsWith($placeholder, '~page:');
                $property = StringDataType::substringAfter($placeholder, '~page:');
                $val = $this->renderPlaceholderPageProperty($property, $this->getPage());
                break;
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
            default:
                $method = 'get' . StringDataType::convertCaseUnderscoreToPascal($property);
                $val = call_user_func([$page, $method]);
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