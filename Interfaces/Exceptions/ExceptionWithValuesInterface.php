<?php

namespace exface\Core\Interfaces\Exceptions;

/**
 * This exception can render an array of values with configurable tokens.
 */
interface ExceptionWithValuesInterface
{
    /**
     * The value will be rendered at the `start` of the message.
     */
    const MODE_PREPEND = 'prepend';
    
    /**
     * The value will be rendered at the `end` of the message.
     */
    const MODE_APPEND = 'append';

    /**
     * The value will be `inserted` into the message, by replacing any detected instance
     * of `{#}` or `ExceptionWithValuesInterface::INSERT`. 
     */
    const MODE_INSERT = 'insert';

    /**
     * If the rendering mode is set to insert, all instances of this string in the message will
     * be replaced with the value snippet.
     */
    const INSERT = '{#}';

    /**
     * Set a new value for the labels of the value snippet.
     * 
     * The value snippet is constructed like `<label> [(]<value>[)]` and 
     * differentiates between singular and plural values.
     * 
     * @param string $singular
     * @param string $plural
     * @return void
     */
    function setValueLabels(
        string $singular = '', 
        string $plural = ''
    ) : void;

    /**
     * Configure where in the message the value should be rendered.
     * 
     * Use the built-in `MODE_` constants:
     * - `MODE_PREPEND`: The values token will be rendered at the `start` of the message. Like "Rows (3,4,6): Message.".
     * - `MODE_APPEND`: The values token will be rendered at the `end` of the message. Like "Message. Rows (3,4,6).".
     * - `MODE_INSERT`: Replaces all instances of the string `{#}` (`ExceptionWithValuesInterface::INSERT`) with the values token.
     * For example "Errors have been detected in {#}. Please contact support." could be rendered as "Errors have been detected in Rows (3,4,6). Please contact support."
     * 
     * @param string $mode
     * @return bool
     */
    function setRenderingMode(string $mode) : bool;

    /**
     * @return string
     */
    function getRenderingMode() : string;

    /**
     * Set the values that will be rendered by this message, for example row numbers.
     * 
     * @param array $values
     * @return void
     */
    function setValues(array $values) : void;

    /**
     * @return array
     */
    function getValues() : array;

    /**
     * @return string
     */
    function getValuesToken() : string;

    /**
     * Returns the message of this instance, rendered without values.
     * 
     * @return string
     */
    function getMessageWithoutValues() : string;

    /**
     * Returns the base string passed to this instance during `__construct()`, without rendering it.
     * 
     * @return string
     */
    function getMessageRaw() : string;

    /**
     * Re-renders the message of this instance.
     *
     * @return void
     */
    function updateMessage() : void;
}