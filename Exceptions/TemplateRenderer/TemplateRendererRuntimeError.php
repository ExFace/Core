<?php
namespace exface\Core\Exceptions\TemplateRenderer;

use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Debug\LogBookInterface;
use exface\Core\Interfaces\Exceptions\TemplateRendererExceptionInterface;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;
use exface\Core\Widgets\DebugMessage;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\UxonObject;

class TemplateRendererRuntimeError extends RuntimeException implements TemplateRendererExceptionInterface
{
    private TemplateRendererInterface $renderer;
    private ?string $template = null;
    private ?LogBookInterface $logbook = null;
    
    /**
     * 
     * @param TemplateRendererInterface $renderer
     * @param string $message
     * @param string $alias
     * @param \Throwable $previous
     * @param string|null $renderedTemplate
     * @param LogBookInterface|null $logbook
     */
    public function __construct(TemplateRendererInterface $renderer, $message, $alias = null, $previous = null, ?string $renderedTemplate = null, ?LogBookInterface $logbook = null)
    {
        parent::__construct($message, null, $previous);
        $this->renderer = $renderer;
        $this->template = $renderedTemplate;
        $this->logbook = $logbook;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Exceptions\TemplateRendererExceptionInterface::getResolver()
     */
    public function getResolver(): TemplateRendererInterface
    {
        return $this->renderer;
    }

    /**
     * @return string|null
     */
    public function getRenderedTemplate() : ?string
    {
        return $this->template;
    }

    /**
     * @return LogBookInterface|null
     */
    public function getLogbook(): ?LogBookInterface
    {
        return $this->logbook;
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
        $rendererClass = get_class($this->getResolver());
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
        if ($this->logbook !== null) {
            $debug_widget = $this->logbook->createDebugWidget($debug_widget);
        }
        return $debug_widget;
    }
}