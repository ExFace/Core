<?php

namespace exface\Core\Communication\UserConfirmations;

use exface\Core\Interfaces\Communication\UserConfirmationInterface;
use exface\Core\Interfaces\WidgetInterface;

/**
 * @inheritDoc
 */
abstract class AbstractUserConfirmation implements UserConfirmationInterface
{
    protected ?WidgetInterface $widget = null;

    public function __construct(?WidgetInterface $widget)
    {
        $this->widget = $widget;
    }

    /**
     * @inheritDoc
     */
    public function setWidget(WidgetInterface $widget) : static
    {
        $this->widget = $widget;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getWidget(): WidgetInterface
    {
        return $this->widget;
    }

    /**
     * @inheritDoc
     */
    public function disabled(): bool
    {
        if(!isset($this->widget)) {
            return true;
        }

        return $this->widget->isDisabled();
    }

    /**
     * @inheritDoc
     */
    public static function getDefaultTranslationTokens() : array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getTranslationTokens(): array
    {
        return $this->getDefaultTranslationTokens();
    }
}