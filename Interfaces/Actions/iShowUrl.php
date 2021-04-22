<?php
namespace exface\Core\Interfaces\Actions;

interface iShowUrl extends iNavigate
{

    public function setUrl($value);

    public function getUrl();
    
    public function getOpenInNewWindow();
    
    public function setOpenInNewWindow($value);
}
	