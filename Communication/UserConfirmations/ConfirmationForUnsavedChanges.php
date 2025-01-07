<?php

namespace exface\Core\Communication\UserConfirmations;

/**
 * @inheritDoc
 */
class ConfirmationForUnsavedChanges extends AbstractUserConfirmation
{
    /**
     * @inheritDoc
     */
    public static function isRequiredWithoutActionReference(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public static function getDefaultTranslationTokens(): array
    {
        return [
            self::TRANSLATE_TITLE => "MESSAGE.CONFIRM.CHANGES.TITLE",
            self::TRANSLATE_CONTENT => "MESSAGE.CONFIRM.CHANGES.TEXT",
            self::TRANSLATE_CONFIRM => "MESSAGE.CONFIRM.CHANGES.CONFIRM",
            self::TRANSLATE_CANCEL => "MESSAGE.CANCEL"
        ];
    }
}