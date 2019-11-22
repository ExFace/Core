<?php
namespace exface\Core\Interfaces\Widgets;

use exface\Core\Interfaces\Model\ExpressionInterface;

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
     * @param string|ExpressionInterface $caption
     * @return iHaveCaption
     */
    public function setCaption($caption) : iHaveCaption; 
    
    /**
     *
     * @return string|NULL
     */
    public function getCaption() : ?string;
    
    /**
     * Returns TRUE if the caption is supposed to be hidden, FALSE it must be shown and NULL by default.
     *
     * @return bool|NULL
     */
    public function getHideCaption() : ?bool;
    
    /**
     *
     * @param bool $value
     * @return iHaveCaption
     */
    public function setHideCaption(bool $value) : iHaveCaption;
}