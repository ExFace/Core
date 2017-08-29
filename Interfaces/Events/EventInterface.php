<?php
namespace exface\Core\Interfaces\Events;

use exface\Core\Interfaces\ExfaceClassInterface;

interface EventInterface extends ExfaceClassInterface
{

    /**
     * Returns the events name (like BeforeQuery)
     *
     * @return string
     */
    public function getName();

    /**
     * Sets the events name
     *
     * @param string $value            
     */
    public function setName($value);

    /**
     * Returns the events fully qualified name (like exface.UrlDataConnector.DataConnection.BeforeQuery)
     *
     * @return string
     */
    public function getNameWithNamespace();

    /**
     * Returns the events namespace (typicall constistant of the app namespace and some kind of event specific suffix)
     *
     * @return string
     */
    public function getNamespace();

    /**
     * Prevents propagation of this event to further listeners
     *
     * @return void
     */
    public function stopPropagation();

    /**
     * Returns TRUE if no further listeners will be triggerd by this event or FALSE otherwise
     *
     * @return boolean
     */
    public function isPropagationStopped();
}
?>