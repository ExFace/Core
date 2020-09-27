<?php
namespace exface\Core\CommonLogic;

use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Translation\Loader\JsonFileLoader;
use exface\Core\Interfaces\TranslationInterface;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;

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
    
    private $domains = [];
    
    private $domains_data = [];
    
    private $app = null;
    
    private $workbench = null;
    
    private $translationsFolder = null;
    
    /**
     * 
     * @param string $locale
     * @param array $fallbackLocales
     */
    public function __construct(AppInterface $app, string $locale, array $fallbackLocales = [], string $translationsFolder = 'Translations')
    {
        $this->app = $app;
        $this->workbench = $app->getWorkbench();
        $this->translationsFolder = $translationsFolder;
        $this->translator = new Translator($locale);
        $this->translator->addLoader('json', new JsonFileLoader());
        $this->translator->setFallbackLocales($fallbackLocales);
        
        $locales = array_unique(
            array_merge(
                [$locale],
                $fallbackLocales
                )
            );
        
        foreach ($locales as $locale) {
            $locale_suffixes = array();
            $locale_suffixes[] = $locale;
            $locale_suffixes[] = explode('_', $locale)[0];
            $locale_suffixes = array_unique($locale_suffixes);
            
            foreach ($locale_suffixes as $suffix) {
                $filename = $this->app->getAliasWithNamespace() . '.' . $suffix . '.json';
                // Load the default translation of the app
                $this->addDictionaryFromFile($this->getTranslationsFolder() . DIRECTORY_SEPARATOR . $filename, $locale);
                
                // Load the installation specific translation of the app
                $this->addDictionaryFromFile($this->getWorkbench()->filemanager()->getPathToTranslationsFolder() . DIRECTORY_SEPARATOR . $filename, $locale);
            }
        }
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
        $locale = $this->getLocale();
        return StringDataType::substringBefore($locale, '_', $locale);
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
    protected function addDictionaryFromFile(string $absolute_path, string $locale, string $domain = null) : Translation
    {
        if (file_exists($absolute_path)) {
            $this->translator->addResource('json', $absolute_path, $locale, $domain);
        }
        return $this;
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
            $result = $this->getTranslator()->trans($message_id, $placeholder_values ?? [], $domain);
        } else {
            $result = $this->getTranslator()->transChoice($message_id, $plural_number, $placeholder_values ?? [], $domain);
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
            if (! $this->hasTranslationDomain($domain)) {
                return [];
            }
            if ($this->domains_data[$domain] === null) {
                $this->domains_data[$domain] = json_decode(file_get_contents($this->domains[$domain]), true);
            }
            return $this->domains_data[$domain];
        } else {
            $cat = $this->translator->getCatalogue($this->translator->getLocale());
            foreach ($cat->all() as $msgs) {
                $dict = array_merge($dict, $msgs);
            }
            return $dict;
        }
    }
    
    /**
     * @return string
     */
    protected function getTranslationsFolder(bool $absolute = true) : string
    {
        return ($absolute ? $this->app->getDirectoryAbsolutePath() . DIRECTORY_SEPARATOR : '') . $this->translationsFolder;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::hasTranslationDomain()
     */
    public function hasTranslationDomain(string $name) : bool
    {
        if ($this->domains[$name] !== null) {
            return true;
        }
        
        $filePath = $this->getTranslationsFolder() . DIRECTORY_SEPARATOR . $name . '.' . $this->getLanguage() . '.json';
        if (file_exists($filePath)) {
            $this->domains[$name] = $filePath;
            $this->addDictionaryFromFile($filePath, $this->getLocale(), $name);
            return true;
        } else {
            return false;
        }
    }
    
    /**
     * 
     * @return WorkbenchInterface
     */
    protected function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\TranslationInterface::getLanguagesAvailable()
     */
    public function getLanguagesAvailable(bool $forceLocale = true) : array
    {
        $langs = [$this->app->getLanguageDefault()];
        foreach (glob($this->getTranslationsFolder() . DIRECTORY_SEPARATOR . "*.json") as $path) {
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $lang = StringDataType::substringAfter($filename, '.', false, false, true);
            if ($forceLocale) {
                $json = json_decode(file_get_contents($path), true);
                if ($json && $locale = $json['LOCALIZATION.LOCALE']) {
                    $langs[] = $locale;
                } else {
                    $langs[] = $lang;
                }
            } else {
                $langs[] = $lang;
            }
        }
        return array_unique($langs);
    }
    
    public function translateUxonProperties(UxonObject $uxon, string $domain, string $namespace) : UxonObject
    {
        $uxon = $uxon->copy();
        
        if ($uxon->isEmpty()) {
            return $uxon;
        }
        
        if (! $this->hasTranslationDomain($domain)) {
            return $uxon;
        }
        
        foreach ($uxon->getPropertiesAll() as $prop => $val) {
            if (is_string($val)) {
                $key = static::buildTranslationKey([$namespace, $prop, $val]);
                if (($trans = $this->translate($key, null, null, $domain)) !== $key) {
                    $uxon->setProperty($prop, $trans);
                }
            }
            
            if ($val instanceof UxonObject) {
                $uxon->setProperty($prop, $this->translateUxonProperties($val, $domain, $namespace));
            }
        }
        
        return $uxon;
    }
    
    public static function buildTranslationKey(array $parts) : string
    {
        $key = implode('.', $parts);
        $key = str_replace(' ', '_', $key);
        $key = mb_strtoupper($key);
        return $key;
    }
}