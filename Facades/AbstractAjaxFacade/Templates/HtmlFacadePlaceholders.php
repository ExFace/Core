<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Templates;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/**
 * Replaces placeholders with output of AJAX facades.
 * 
 * ## Supported Placeholders
 * 
 * - `[#~head#]` - replaced by the output of `Facade::buildHtmlHead($widget, true)`
 * - `[#~body#]` - replaced by the output of `Facade::buildHtmlBody($widget)`
 * 
 * @author Andrej Kabachnik
 *
 */
class HtmlFacadePlaceholders implements PlaceholderResolverInterface
{
    private $facade = null;
    
    private $widget = null;
    
    /**
     *
     * @param FacadeInterface $facade
     * @param string $prefix
     */
    public function __construct(AbstractAjaxFacade $facade, WidgetInterface $widget)
    {
        $this->facade = $facade;
        $this->widget = $widget;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface::resolve()
     */
    public function resolve(array $placeholders) : array
    {
        $vals = [];
        foreach ($placeholders as $placeholder) {
            switch (true) {
                case $placeholder === '~head':
                    $val = $this->facade->buildHtmlHead($this->widget, true);
                    break;
                case $placeholder === '~body':
                    $val = $this->facade->buildHtmlBody($this->widget);
                    break;
            }
            $vals[$placeholder] = $val;
        }
        return $vals;
    }
}