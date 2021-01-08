<?php
namespace exface\Core\Exceptions\Queues;

/**
 * Exception thrown if a message is placed in a queue with unique ids multiple times.
 * 
 * @author Andrej Kabachnik
 *
 */
class QueueMessageDuplicateError extends QueueRuntimeError
{}