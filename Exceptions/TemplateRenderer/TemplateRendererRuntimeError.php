<?php
namespace exface\Core\Exceptions\TemplateRenderer;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Exceptions\TemplateRendererExceptionInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

class TemplateRendererRuntimeError extends RuntimeException implements TemplateRendererExceptionInterface
{
    private $renderer = null;
    
    private $template = null;
    
    /**
     * 
     * @param TemplateRendererInterface $renderer
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     */
    public function __construct(TemplateRendererInterface $renderer, $message, $alias = null, $previous = null, string $renderedTemplate = null)
    {
        parent::__construct($message, null, $previous);
        $this->renderer = $renderer;
        $this->template = $renderedTemplate;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\TemplateRendererExceptionInterface::getRenderer()
     */
    public function getRenderer(): TemplateRendererInterface
    {
        return $this->renderer;
    }
    
    public function getRenderedTemplate() : ?string
    {
        return $this->template;
    }
    
    /**
     *
     * @param DebugMessage $debug_widget
     */
    public function createDebugWidget(DebugMessage $debug_widget)
    {
        $debug_widget = parent::createDebugWidget($debug_widget);
        $tab = $debug_widget->createTab();
        $tab->setCaption('Template');
        $rendererClass = get_class($this->getRenderer());
        $tab->addWidget(WidgetFactory::createFromUxonInParent($tab, new UxonObject([
            'widget_type' => 'Markdown',
            'width' => 'max',
            'hide_caption' => true,
            'value' => <<<MD
Renderer prototype class: {$rendererClass}

```
{$this->getRenderedTemplate()}
```
MD
])));
        $debug_widget->addTab($tab);
        return $debug_widget;
    }
}