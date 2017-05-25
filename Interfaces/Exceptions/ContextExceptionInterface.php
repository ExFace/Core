<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Contexts\ContextInterface;

Interface ContextExceptionInterface
{

    /**
     *
     * @param ContextInterface $context            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(ContextInterface $context, $message, $code = null, $previous = null);

    /**
     *
     * @return ContextInterface
     */
    public function getContext();

    /**
     *
     * @param ContextInterface $sheet            
     * @return ContextExceptionInterface
     */
    public function setContext(ContextInterface $context);
}
?>