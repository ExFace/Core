<?php
namespace exface\Core\Templates\Placeholders;

use exface\Core\CommonLogic\TemplateRenderer\AbstractPlaceholderResolver;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\WidgetFactory;
use exface\Core\Interfaces\Model\UiPageInterface;

/**
 * Replaces placeholders with rendered widgets: `~widget:widget_type` or `~widget:widget_type:uxon`.
 *
 * @author Andrej Kabachnik
 */
class WidgetRenderPlaceholders extends AbstractPlaceholderResolver
{
    private $facade = null;
    
    private $page = null;
    
    /**
     * 
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(FacadeInterface $facade, UiPageInterface $page, string $prefix = '~widget:')
    {
        $this->setPrefix($prefix);
        $this->facade = $facade;
        $this->page = $page;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {     
        $vals = [];
        foreach ($this->filterPlaceholders($placeholders) as $placeholder) {
            $widgetType = $this->stripPrefix($placeholder);
            $json = StringDataType::substringAfter($widgetType, ':', null);
            if ($json !== null) {
                $widgetType = StringDataType::substringBefore($widgetType, ':', $widgetType);
                $uxon = UxonObject::fromJson($json);
            } elseif (StringDataType::startsWith($widgetType, 'Nav') === true) {
                $uxon = new UxonObject([
                    'object_alias' => 'exface.Core.PAGE'
                ]);
            } else {
                $uxon = null;
            }
            
            $phWidget = WidgetFactory::createFromUxon($this->page, $uxon, null, $widgetType);
            $vals[$placeholder] = $this->facade->buildHtml($phWidget);
        }
        return $vals;
    }
}