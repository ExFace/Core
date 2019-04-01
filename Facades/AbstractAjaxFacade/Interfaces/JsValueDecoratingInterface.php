<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Interfaces;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface JsValueDecoratingInterface 
{
    
    /**
     * Returns inline javascript code decorating the value with extra styles, wrapping HTML elements, etc.
     *
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