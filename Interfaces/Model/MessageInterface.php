<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
interface MessageInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon
{
    public function getCode() : string;
    
    public function setCode(string $code) : MessageInterface;
    
    public function getType(string $default = null) : string;

    public function setType(string $value) : MessageInterface;
    
    public function getTitle() : string;

    public function setTitle(string $value) : MessageInterface;
    
    public function getHint() : ?string;

    public function setHint(string $value) : MessageInterface;
    
    public function getDescription() : ?string;
    
    public function setDescription(string $markdown) : MessageInterface;
    
    public function getAppSelector() : ?AppSelectorInterface;

    public function setAppSelector($stringOrSelector) : MessageInterface;
    
    public function getApp() : ?AppInterface;
}