<?php
namespace exface\Core\Exceptions\TemplateRenderer;

use exface\Core\Interfaces\TemplateRenderers\TemplateRendererInterface;

class PlaceholderValueInvalidError extends PlaceholderNotFoundError
{
    private $value = null;
    
    /**
     * 
     * @param string $placeholder
     * @param mixed $message
     * @param mixed $alias
     * @param mixed $previous
     * @param string|NULL $template
     * @param mixed $value
     */
    public function __construct(string $placeholder, $message, $alias = null, $previous = null, string $template = null, $value = null)
    {
        parent::__construct($placeholder, $message, null, $previous, $template);
        $this->value = $value;
    }
    
    /**
     * 
     * @return mixed
     */
    public function getPlaceholderValue()
    {
        return $this->value;
    }
}