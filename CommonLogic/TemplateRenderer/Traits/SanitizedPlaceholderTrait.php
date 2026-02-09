<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

use exface\Core\DataTypes\JsonDataType;
use exface\Core\Interfaces\TemplateRenderers\PlaceholderResolverInterface;

/**
 * Trait to simplify sanitizing placeholder values
 * 
 * @author andrej.kabachnik
 *
 */
trait SanitizedPlaceholderTrait
{
    private $sanitizer = null;
    
    private $sanitizeAsUxon = false;
    
    /**
     *
     * @param mixed $val
     * @return mixed
     */
    protected function sanitizeValue($val) {
        if ($this->sanitizeAsUxon === true) {
            $val = JsonDataType::escapeJsonValue($val, false);
        }
        if ($this->sanitizer !== null) {
            $sanitizer = $this->getSanitizer();
            $val = $sanitizer($val);
        }
        return $val;
    }

    /**
     * @return callable
     */
    protected function getSanitizer() : callable
    {
        return $this->sanitizer;
    }
    
    /**
     *
     * @param callable $function
     * @return PlaceholderResolverInterface
     */
    public function setSanitizer(callable $function) : PlaceholderResolverInterface
    {
        $this->sanitizer = $function;
        return $this;
    }
    
    /**
     *
     * @param bool $value
     * @return PlaceholderResolverInterface
     */
    public function setSanitizeAsUxon(bool $value) : PlaceholderResolverInterface
    {
        $this->sanitizeAsUxon = $value;
        return $this;
    }
}