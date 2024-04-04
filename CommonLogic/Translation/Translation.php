<?php
namespace exface\Core\CommonLogic\Translation;

use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\DataTypes\LocaleDataType;

/**
 * This is the default implementation of the TranslationInterface.
 * 
 * It is basically a wrapper for the Symfony Translation Component.
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
    public function __construct(string $locale)
    {
        $this->translator = new Translator($locale);
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getLanguage()
     */
    public function getLanguage() : string
    {
        return LocaleDataType::findLanguage($this->getLocale());
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
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\TranslationInterface::translate()
     */
    public function translate(string $message_id, array $placeholder_values = null, $plural_number = null, string $domain = null, string $fallback = null) : string
    {
        if ($domain !== null && ! $this->hasTranslationDomain($domain)) {
            $result = $message_id;
        } elseif ($plural_number === null) {
            $result = $this->translator->trans($message_id, $placeholder_values ?? [], $domain);
        } else {
            $result = $this->translator->transChoice($message_id, $plural_number, $placeholder_values ?? [], $domain);
        }
        
        if ($fallback !== null && $result === $message_id) {
            return $fallback;
        }
        
        return $result;
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
    public function hasTranslation($message_id, string $domain = null) : bool
    {
        return $this->translate($message_id, null, null, $domain) === $message_id ? false : true;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getDictionary()
     */
    public function getDictionary(string $domain = null) : array
    {
        $dict = [];
        
        if ($domain !== null) {
            return [];
        } else {
            $cat = $this->translator->getCatalogue($this->translator->getLocale());
            foreach ($cat->all() as $msgs) {
                $dict = array_merge($dict, $msgs);
            }
            return $dict;
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::hasTranslationDomain()
     */
    public function hasTranslationDomain(string $name) : bool
    {
        return false;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getLanguagesAvailable()
     */
    public function getLanguagesAvailable(bool $forceLocale = true) : array
    {
        return [$this->getTranslator()->getLocale()];
    }
    
    /**
     * Transform an array of key parts into a valid translation key
     * 
     * E.g. `['my', 'Key', 'with some info']` => `MY.KEY.WITH_SOME_INFO`
     * 
     * @param string[] $parts
     * @return string
     */
    public static function buildTranslationKey(array $parts) : string
    {
        $key = implode('.', $parts);
        $key = str_replace(' ', '_', $key);
        $key = mb_strtoupper($key);
        return $key;
    }
}