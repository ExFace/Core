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
     * Sets the widget caption/title.
     * 
     * By default, setting a caption explicitly will force the widget to show it
     * (same as setHideCaption(false)). This is important for captions specified
     * in UXON - indeed, if the user sets a caption he or she will expect it to show.
     * 
     * When setting the caption programmatically, this behavior can be explicitly
     * controlled using the optional $forceCaptionVisible parameter.
     *
     * @param string|ExpressionInterface $caption
     * @param bool $forceCaptionVisible
     * 
     * @return iHaveCaption
     */
    public function setCaption($caption, bool $forceCaptionVisible = true) : iHaveCaption; 
    
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