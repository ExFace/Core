<?php
namespace exface\Core\Exceptions\TemplateRenderer;

use exface\Core\Exceptions\RangeException;
use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

class PlaceholderNotFoundError extends RangeException
{
    private $placeholder = null;

    private $template = null;
    
    /**
     * 
     * @param string $placeholder
     * @param mixed $message
     * @param mixed $alias
     * @param mixed $previous
     * @param string|NULL $template
     */
    public function __construct(string $placeholder, $message, $alias = null, $previous = null, string $template = null)
    {
        parent::__construct($message, null, $previous);
        $this->placeholder = $placeholder;
        $this->template = $template;
    }
    
    /**
     * 
     * @return string
     */
    public function getPlaceholder() : string
    {
        return $this->placeholder;
    }

    /**
     * 
     * @return string
     */
    public function getTemplate() : ?string
    {
        return $this->template;
    }
}