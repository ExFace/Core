<?php
namespace exface\Core\Interfaces\Security;

/**
 * 
 * 
 * @author Andrej Kabachnik
 *
 */
interface PermissionInterface
{    
    public function isPermitted() : bool;
    
    public function isDenied() : bool;
    
    public function isIndeterminate() : bool;
    
    public function isNotApplicable() : bool;
}