<?php
namespace exface\Core\Interfaces\Exceptions;

/**
 * Interface for exceptions that shows that the exception contains a custom stack-
 * trace.
 * 
 * This is needed for the proper display of stacktraces from alexa RMS. It is not
 * possible to insert a custom stacktrace in a php exception or throwable.
 * 
 * @author SFL
 *
 */
interface iContainCustomTrace extends \Throwable {
    
    /**
     * Returns the custom stacktrace in the php stacktrace format.
     * 
     * [
     *   [
     *     'file' => '...',     string, e.g. 'C:\\wamp\\...'
     *     'line' => '...',     int, e.g. 136
     *     'function' => '...', string, e.g. 'processError' 
     *     'class' => '...',    string, e.g. 'exface\\Core\\...'
     *     'type' => '...',     string, e.g. '->'
     *     'args' => [...]      object[], e.g. [null] or [HttpConnector]
     *   ],
     *   [
     *     ...
     *   ]
     * ]
     * 
     * @return array The custom stacktrace.
     */
    public function getStacktrace();
    
}
