<?php

namespace exface\Core\Interfaces\Model;

use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\AliasInterface;
use exface\Core\Interfaces\iCanBeCopied;

interface BehaviorInterface extends ExfaceClassInterface, iCanBeConvertedToUxon, AliasInterface, iCanBeCopied
{

    /**
     *
     * @return Object
     */
    public function getObject();

    /**
     *
     * @param Object $value            
     * @return BehaviorInterface
     */
    public function setObject(Object $value);

    /**
     *
     * @return BehaviorInterface
     */
    public function register();

    /**
     *
     * @return boolean
     */
    public function isDisabled();

    /**
     *
     * @return BehaviorInterface
     */
    public function disable();

    /**
     *
     * @return BehaviorInterface
     */
    public function enable();

    /**
     *
     * @return boolean
     */
    public function isRegistered();
}
?>