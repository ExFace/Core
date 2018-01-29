<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Interfaces;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface JsValueDecoratingInterface 
{
    /**
     * @return string
     */
    public function buildJsValueDecorator($value_js);
    
    /**
     * Returns TRUE if this element requires a value decorator and/or formatter and FALSE otherwise.
     * 
     * @return boolean
     */
    public function hasDecorator();   
    
}