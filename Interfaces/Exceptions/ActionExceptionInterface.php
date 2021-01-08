<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\Actions\ActionInterface;

Interface ActionExceptionInterface
{

    /**
     *
     * @param ActionInterface $action            
     * @param string $message            
     * @param string $code            
     * @param \Throwable $previous            
     */
    public function __construct(ActionInterface $action, $message, $code = null, $previous = null);

    /**
     *
     * @return ActionInterface
     */
    public function getAction();
}