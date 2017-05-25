<?php
namespace exface\Core\Interfaces\Exceptions;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Widgets\ErrorMessage;
use exface\Core\Interfaces\iCanGenerateDebugWidgets;

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
    public static function getDefaultAlias();

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
}
