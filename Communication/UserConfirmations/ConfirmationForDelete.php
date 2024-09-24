<?php

namespace exface\Core\Communication\UserConfirmations;

/**
 * @inheritDoc
 */
class ConfirmationForDelete extends ConfirmationForAction
{
    /**
     * @inheritDoc
     */
    public static function getDefaultTranslationTokens(): array
    {
        return [
            self::TRANSLATE_TITLE => "MESSAGE.CONFIRM.DELETE.TITLE",
            self::TRANSLATE_CONTENT => "MESSAGE.CONFIRM.DELETE.TEXT",
            self::TRANSLATE_CONFIRM => "MESSAGE.CONFIRM.DELETE.CONFIRM",
            self::TRANSLATE_CANCEL => "MESSAGE.CANCEL"
        ];
    }
}