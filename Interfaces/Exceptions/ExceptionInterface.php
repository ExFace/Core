<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * This interface describes workbench exceptions. 
 * 
 * They are compatible to the SPL-exceptions in PHP, but offer more options to get descriptive
 * information: error code, user-oriented message, debug-widget, etc. 
 * 
 * All exceptions thrown from the workbench or it's dependants should implement this interface!
 * 
 * The main properties of a workbench exception are
 * 
 * - Message text - technical description of the specific error
 * - Alias - message code describing the error type. This is also the link to the messages stored
 * in the metamodel. Aliases should be used whenever the user is to be given additional information
 * like an explanation of the error or a hint on how to repair/avoid it. Users can serach for these
 * aliases in the documentation. Developers can use the alias to search the code for exceptions
 * of a certain type.
 * - Message model - optional type, title, hint and description defined as a message object in the
 * metamodel and linked to the exception via it's alias. The message properties are more meant to
 * be more user-friendly (translatable, etc.) than the original exception message.
 * 
 * When an exception is to be shown to a user, it's message model is used. The technical exception
 * message is concidered to be an "additional detail" and can even be conceiled from less experienced
 * users.
 * 
 * NOTE: should the exception message be acutally meant to be shown to the user as error text,
 * call `setUseExceptionMessageAsTitle(true)` on an exception instance.
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
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageTitle(WorkbenchInterface $workbench) : ?string;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageHint(WorkbenchInterface $workbench) : ?string;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageDescription(WorkbenchInterface $workbench) : ?string;
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @return string|NULL
     */
    public function getMessageType(WorkbenchInterface $workbench) : ?string;
    
    /**
     * Makes the errors displayed use the exception message as title instead of attempting to
     * get the title from the message metamodel via error code (alias).
     *
     * @param bool $value
     * @return ExceptionInterface
     */
    public function setUseExceptionMessageAsTitle(bool $value);
}
