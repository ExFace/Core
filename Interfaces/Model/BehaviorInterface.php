<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;
use exface\Core\Interfaces\AppInterface;

interface BehaviorInterface extends WorkbenchDependantInterface, iCanBeConvertedToUxon, AliasInterface, iCanBeCopied
{

    /**
     *
     * @return MetaObjectInterface
     */
    public function getObject() : MetaObjectInterface;

    /**
     *
     * @param MetaObjectInterface $value            
     * @return BehaviorInterface
     */
    public function setObject(MetaObjectInterface $value) : BehaviorInterface;
    
    /**
     * Returns the app, that the behavior belongs to.
     * 
     * @return AppInterface
     */
    public function getApp() : AppInterface;

    /**
     *
     * @return BehaviorInterface
     */
    public function register() : BehaviorInterface;

    /**
     *
     * @return boolean
     */
    public function isDisabled() : bool;

    /**
     *
     * @return BehaviorInterface
     */
    public function disable() : BehaviorInterface;

    /**
     *
     * @return BehaviorInterface
     */
    public function enable() : BehaviorInterface;

    /**
     *
     * @return boolean
     */
    public function isRegistered() : bool;
    
    /**
     * 
     * @return BehaviorSelectorInterface
     */
    public function getSelector() : BehaviorSelectorInterface;
}
?>