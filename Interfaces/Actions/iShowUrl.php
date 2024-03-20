<?php
namespace exface\Core\Interfaces\Actions;

interface iShowUrl extends iNavigate
{
    /**
     * 
     * @return string
     */
    public function getUrl() : string;
    
    /**
     * 
     * @return bool
     */
    public function getOpenInNewWindow() : bool;
    
    /**
     * 
     * @return string|NULL
     */
    public function getOpenInBrowserWidget() : ?string;
}
	