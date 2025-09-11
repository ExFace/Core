<?php

namespace exface\Core\Exceptions\DataTypes;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\Exceptions\UnexpectedValueException;

/**
 * A basic error, thrown during UXON validation, that contains a path pointing to the
 * affected property.
 */
class UxonValidationError extends UnexpectedValueException
{
    private array $path;
    private ?UxonObject $uxon;

    public function __construct(
        array $path,
        string $message, 
        string $alias = '', 
        \Throwable $previous = null,
        UxonObject $uxon = null
    )
    {
        parent::__construct($message, $alias, $previous);
        $this->path = $path;
        $this->uxon = $uxon;
    }

    public function getPath() : array
    {
        return $this->path;
    }
    
    public function getUxon() : ?UxonObject
    {
        return $this->uxon;
    }
    
    public function getAffectedProperty() : ?string
    {
        $length = count($this->path);
        return $length > 0 ? $this->path[$length - 1] : null;
    }
}