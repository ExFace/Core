<?php
namespace exface\Core\CommonLogic\TemplateRenderer\Traits;

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
            $sanitizer = $this->getSanitizerForUxon();
            $val = $sanitizer($val);
        }
        if ($this->sanitizer !== null) {
            $sanitizer = $this->sanitizer;
            $val = $sanitizer($val);
        }
        return $val;
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
    
    /**
     * 
     * @return callable
     */
    protected function getSanitizerForUxon() : callable
    {
        $fn = function($val) {
            $enc = json_encode($val);
            if (mb_substr($enc, 0, 1) === '"' && mb_substr($enc, -1) === '"') {
                $enc = substr($enc, 1, -1);
            }
            return $enc;
        };
        return $fn;
    }
}