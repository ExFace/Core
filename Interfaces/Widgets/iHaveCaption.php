<?php
namespace exface\Core\Interfaces\Widgets;

/**
 * 
 * @author Andrej Kabachnik
 *
 */
interface iHaveCaption
{
    /**
     * Sets the widget caption/title
     *
     * @param string $caption
     * @return iHaveCaption
     */
    public function setCaption($caption); 
    
    /**
     *
     * @return string
     */
    public function getCaption();
    
    /**
     * Returns TRUE if the caption is supposed to be hidden
     *
     * @return boolean
     */
    public function getHideCaption();
    
    /**
     *
     * @param bool $value
     * @return iHaveCaption
     */
    public function setHideCaption($value);
}