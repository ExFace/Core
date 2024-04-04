<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;

/**
 * 
 * @author andrej.kabachnik
 *
 */
interface MessageInterface extends WorkbenchDependantInterface
{    
    public function getCode() : string;
    
    public function getType() : string;
    
    public function setType(string $value) : MessageInterface;
    
    public function getTitle() : string;
    
    public function setTitle(string $string) : MessageInterface;
    
    public function getHint() : ?string;
    
    public function setHint(string $value) : MessageInterface;
    
    public function getDescription() : ?string;
    
    public function setDescription(string $markdown) : MessageInterface;
    
    public function getAppSelector() : ?AppSelectorInterface;
    
    public function setAppSelector($stringOrSelector) : MessageInterface;
    
    public function getApp() : ?AppInterface;
}