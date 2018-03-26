<?php
namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeCopied;
use exface\Core\Interfaces\Selectors\BehaviorSelectorInterface;

interface BehaviorInterface extends ExfaceClassInterface, iCanBeConvertedToUxon, AliasInterface, iCanBeCopied
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