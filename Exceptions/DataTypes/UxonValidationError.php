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

    /**
     * Returns a property path that points to the error location in the
     * affected UXON.
     * 
     * @return array
     */
    public function getPath() : array
    {
        return $this->path;
    }

    /**
     * @return UxonObject|null
     */
    public function getUxon() : ?UxonObject
    {
        return $this->uxon;
    }

    /**
     * @return string|null
     */
    public function getAffectedProperty() : ?string
    {
        $length = count($this->path);
        return $length > 0 ? $this->path[$length - 1] : null;
    }
}