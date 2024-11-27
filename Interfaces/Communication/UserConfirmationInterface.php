<?php

namespace exface\Core\Interfaces\Communication;

use exface\Core\Interfaces\WidgetInterface;

/**
 * A user confirmation requires the user to respond to a dialog or message pop-up before
 * they can proceed with whatever action they were taking.
 *
 * As such this class provides useful data for generating said confirmation message, as well as
 * some basic logic to determine whether the system should ask for confirmation in the first place.
 */
interface UserConfirmationInterface
{
    const TRANSLATE_TITLE = 'title';
    const TRANSLATE_CONTENT = 'content';
    const TRANSLATE_CONFIRM = 'confirm';
    const TRANSLATE_CANCEL = 'cancel';

    /**
     * @param WidgetInterface $widget
     * @return static
     */
    function setWidget(WidgetInterface $widget) : static;

    /**
     * Get the widget for this confirmation.
     *
     * @return WidgetInterface|null
     */
    function getWidget() : ?WidgetInterface;

    /**
     * Check whether the system should ask for this confirmation, even if it has no
     * action reference.
     *
     * This feature exists primarily to ensure backwards compatibility.
     *
     * @return bool
     */
    static function isRequiredWithoutActionReference() : bool;

    /**
     * Checks whether this confirmation is disabled.
     *
     * @return bool
     */
    function disabled() : bool;

    /**
     * Returns the default translation tokens for this type of confirmation.
     * Use the `TRANSLATE_` constants as keys.
     *
     *  ```
     * return [
     *      const::TRANSLATE_TITLE => Message title,
     *      const::TRANSLATE_CONTENT => Message content,
     *      const::TRANSLATE_CONFIRM => Label confirmation button,
     *      const::TRANSLATE_CANCEL => Label cancel button,
     * ];
     *
     *  ```
     *
     * @return array
     */
    static function getDefaultTranslationTokens() : array;

    /**
     * Returns the translation tokens for this instance.
     * Use the `TRANSLATE_` constants as keys.
     *
     *  ```
     * return [
     *      const::TRANSLATE_TITLE => Message title,
     *      const::TRANSLATE_CONTENT => Message content,
     *      const::TRANSLATE_CONFIRM => Label confirmation button,
     *      const::TRANSLATE_CANCEL => Label cancel button,
     * ];
     *
     *  ```
     *
     * @return array
     */
    function getTranslationTokens() : array;
}