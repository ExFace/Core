<?php
namespace exface\Core\Interfaces;

interface ValueObjectInterface
{  
    /**
     *
     * @return mixed
     */
    public function getValue();
    
    /**
     *
     * @return bool
     */
    public function hasValue() : bool;
    
    /**
     *
     * @param mixed $value
     * @return ValueObjectInterface
     */
    public function withValue($value) : ValueObjectInterface;
    
    /**
     *
     * @param ValueObjectInterface $valueObject
     * @return bool
     */
    public function equals(ValueObjectInterface $valueObject) : bool;
}