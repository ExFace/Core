<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\CommonLogic\UxonObject;

Interface UxonExceptionInterface
{

    /**
     *
     * @param UxonInterface $uxon            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(UxonObject $uxon, $message, $code = null, $previous = null);

    /**
     *
     * @return UxonObject
     */
    public function getUxon();

    /**
     *
     * @param UxonInterface $uxon            
     * @return UxonExceptionInterface
     */
    public function setUxon(UxonObject $uxon);
}
?>