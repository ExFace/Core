<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use exface\Core\Interfaces\TranslationInterface;

/**
 * This is the default implementation of the TranslationInterface.
 * 
 * It is basically a wrapper for the Symfony Translation Component.
 * The JSON loade is used to read files passed via addDictionaryFromFile().
 * 
 * @author Andrej Kabachnik
 *
 */
class Translation implements TranslationInterface
{
    private $locale = null;

    private $translator = null;
    
    /**
     * 
     * @param string $locale
     * @param array $fallbackLocales
     */
    public function __construct(string $locale, array $fallbackLocales = [])
    {
        $this->translator = new Translator($locale);
        $this->translator->addLoader('json', new JsonFileLoader());
        $this->translator->setFallbackLocales($fallbackLocales);
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getLocale()
     */
    public function getLocale() : string
    {
        return $this->translator->getLocale();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getFallbackLocales()
     */
    public function getFallbackLocales() : array
    {
        return $this->translator->getFallbackLocales();
    }

    /**
     * 
     * @param string $absolute_path
     * @param string $locale
     * @return Translation
     */
    public function addDictionaryFromFile(string $absolute_path, string $locale) : Translation
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
    public function translate(string $message_id, array $placeholder_values = null, $plural_number = null) : string
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
    protected function getTranslator() : TranslatorInterface
    {
        return $this->translator;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::hasTranslation()
     */
    public function hasTranslation($message_id) : bool
    {
        return $this->translate($message_id) === $message_id ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getDictionary()
     */
    public function getDictionary() : array
    {
        $dict = [];
        $cat = $this->translator->getCatalogue($this->translator->getLocale());
        foreach ($cat->all() as $msgs) {
            $dict = array_merge($dict, $msgs);
        }
        return $dict;
    }
}