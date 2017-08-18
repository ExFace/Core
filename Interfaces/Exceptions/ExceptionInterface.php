<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

/**
 * This interface describes ExFace exceptions. They are compatible to the
 * SPL-exceptions in PHP, but include advanced features like the ability to
 * generate debug widgets.
 * 
 * @author Andrej Kabachnik
 *
 */
interface ExceptionInterface extends iCanBeConvertedToUxon, iCanGenerateDebugWidgets
{

    /**
     * Returns TRUE if this exception is a warning and FALSE otherwise
     *
     * @return boolean
     */
    public function isWarning();

    /**
     * Returns TRUE if this exception is an error and FALSE otherwise
     *
     * @return boolean
     */
    public function isError();

    /**
     * Creates a blawidget with detailed information about this exception.
     *
     * @param UiPageInterface $page            
     * @return ErrorMessage
     */
    public function createWidget(UiPageInterface $page);

    /**
     * Returns the default error code for this type of exception.
     * If no error code is given in the constructor, the default
     * will be used to generate a link to the help, etc.
     *
     * @return string
     */
    public function getDefaultAlias();

    /**
     * Returns the HTTP status code appropriate for this exception
     *
     * @return integer
     */
    public function getStatusCode();

    /**
     *
     * @return string
     */
    public function getAlias();

    /**
     *
     * @param string $string            
     * @return ExceptionInterface
     */
    public function setAlias($string);

    /**
     * Returns the unique identifier of this exception (exceptions thrown at the same line at different times will have differnt ids!)
     *
     * @return string
     */
    public function getId();
    
    /**
     * Returns the log level for this exception according to the PSR-3 standard.
     * 
     * If no log level was specified, the value of getDefaultLogLevel() will
     * be returned. This way each exception class can have it's own default
     * log level.
     * 
     * Chained exceptions will have the log level of the first exception.
     * 
     * @return string
     */
    public function getLogLevel();
    
    /**
     * Sets the log level for this exceptions according to the PSR-3 standard.
     * 
     * @param string $logLevel
     * @return ExceptionInterface
     */
    public function setLogLevel($logLevel);  
    
    /**
     * Returns the default log level for this exception according to PSR-3
     * 
     * @return string
     */
    public function getDefaultLogLevel();
}
