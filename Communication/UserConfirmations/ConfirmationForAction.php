<?php

namespace exface\Core\Communication\UserConfirmations;

/**
 * @inheritDoc
 */
class ConfirmationForAction extends AbstractUserConfirmation
{
    /**
     * @inheritDoc
     */
    public static function isRequiredWithoutActionReference(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public static function getDefaultTranslationTokens(): array
    {
        return [
            self::TRANSLATE_TITLE => "MESSAGE.CONFIRM.ACTION.TITLE",
            self::TRANSLATE_CONTENT => "MESSAGE.CONFIRM.ACTION.TEXT",
            self::TRANSLATE_CONFIRM => "MESSAGE.CONFIRM.ACTION.CONFIRM",
            self::TRANSLATE_CANCEL => "MESSAGE.CANCEL"
        ];
    }
}