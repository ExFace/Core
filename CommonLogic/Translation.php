<?php

namespace exface\Core\CommonLogic;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use exface\Core\Interfaces\TranslationInterface;

class Translation implements TranslationInterface
{

    private $locale = null;

    private $translator = null;

    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    public function setLocale($string)
    {
        $this->translator = new Translator($string);
        $this->translator->addLoader('json', new JsonFileLoader());
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::getFallbackLocales()
     */
    public function getFallbackLocales()
    {
        return $this->translator->getFallbackLocales();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::setFallbackLocale()
     */
    public function setFallbackLocale($string)
    {
        $locales = $this->translator->getFallbackLocales();
        $this->translator->setFallbackLocales(array_unshift($locales, $string));
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::setFallbackLocales()
     */
    public function setFallbackLocales(array $locale_strings)
    {
        $this->translator->setFallbackLocales($locale_strings);
        return $this;
    }

    public function addDictionaryFromFile($absolute_path, $locale)
    {
        if (file_exists($absolute_path)) {
            $this->translator->addResource('json', $absolute_path, $locale);
        }
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::translate()
     */
    public function translate($message_id, array $placeholder_values = null, $plural_number = null)
    {
        if (is_null($plural_number)) {
            return $this->getTranslator()->trans($message_id, is_null($placeholder_values) ? array() : $placeholder_values);
        } else {
            return $this->getTranslator()->transChoice($message_id, $plural_number, is_null($placeholder_values) ? array() : $placeholder_values);
        }
    }

    /**
     *
     * @return TranslatorInterface
     */
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::hasTranslation()
     */
    public function hasTranslation($message_id)
    {
        return $this->tranlate($message_id) === $message_id ? false : true;
    }
}

?>