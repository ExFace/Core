<?php

namespace exface\Core\Interfaces\Exceptions;

use exface\Core\CommonLogic\Model\Object;

Interface MetaObjectExceptionInterface extends ExceptionInterface
{

    /**
     *
     * @param Object $uxon            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(Object $object, $message, $code = null, $previous = null);

    /**
     *
     * @return Object
     */
    public function getMetaObject();

    /**
     *
     * @param Object $object            
     * @return MetaObjectExceptionInterface
     */
    public function setMetaObject(Object $object);
}
?>