<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\CommonLogic\UxonObject;

Interface UxonExceptionInterface
{
    /**
     *
     * @param UxonObject  $uxon
     * @param string      $message
     * @param null        $code
     * @param null        $previous
     * @param string|null $affectedProperty
     */
    public function __construct(UxonObject $uxon, string $message, $code = null, $previous = null, string $affectedProperty = null);

    /**
     *
     * @return UxonObject
     */
    public function getUxon();

    /**
     * @return array
     */
    public function getPath() : array;
}