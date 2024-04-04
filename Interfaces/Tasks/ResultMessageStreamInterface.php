<?php
namespace exface\Core\Interfaces\Tasks;

/**
 * Interface for asynchronous multi-message generators like scrolling CLI results
 * 
 * This type of task result can yield multiple text messages via generator: for example,
 * the output of a complex installer, that will print a message whenever a sub-routine 
 * finishes.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ResultMessageStreamInterface extends ResultInterface
{
    /**
     * Returns the generator, that will yield one message at a time when iterated over.
     * 
     * @return \Traversable
     */
    public function getMessageStreamGenerator() : \Traversable;
    
    /**
     * Sets the generator to stream for and the arguments to pass to it if required.
     * 
     * The arguments are meant to be used as `call_user_func_array($generator, $arguments)`.
     * 
     * @param callable $generator
     * @param array $arguments
     * 
     * @return ResultMessageStreamInterface
     */
    public function setMessageStreamGenerator(callable $generator, array $arguments = []) : ResultMessageStreamInterface;
}