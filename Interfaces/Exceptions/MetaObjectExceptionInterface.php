<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Model\MetaObjectInterface;

Interface MetaObjectExceptionInterface extends ExceptionInterface
{

    /**
     *
     * @param MetaObjectInterface $uxon            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(MetaObjectInterface $object, $message, $code = null, $previous = null);

    /**
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject();

    /**
     *
     * @param MetaObjectInterface $object            
     * @return MetaObjectExceptionInterface
     */
    public function setMetaObject(MetaObjectInterface $object);
}
?>