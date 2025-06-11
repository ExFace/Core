<?php
namespace exface\Core\CommonLogic\Translation;

use Symfony\Component\Translation\Loader\JsonFileLoader;
use exface\Core\Interfaces\AppInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\FileNotReadableError;

/**
 * Uses JSON files in the `Translations` folder in an app and in the workbench root as dictionaries.
 * 
 * Also allows to load additional files from subfolder on-demand using domains: if the provided
 * domain matches a JSON file within the translations foler, it is used as the dictionary for
 * the domain.
 * 
 * This is the default translation implementation for workbench apps.
 * 
 * @author Andrej Kabachnik
 *
 */
class AppTranslation extends Translation
{
    private $app = null;
    
    private $workbench = null;
    
    private $translationsFolder = null;
    
    private $domains = [];
    
    private $domains_data = [];
    
    /**
     * 
     * @param string $locale
     * @param array $fallbackLocales
     */
    public function __construct(string $locale, AppInterface $app, array $fallbackLocales = [], string $translationsFolder = 'Translations')
    {
        parent::__construct($locale);
        $this->app = $app;
        $this->workbench = $app->getWorkbench();
        $this->translationsFolder = $translationsFolder;
        
        $translator = parent::getTranslator();
        $translator->addLoader('json', new JsonFileLoader());
        $translator->setFallbackLocales($fallbackLocales);
        
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
     * @param string $absolute_path
     * @param string $locale
     * @return Translation
     */
    protected function addDictionaryFromFile(string $absolute_path, string $locale, string $domain = null) : bool
    {
        if (file_exists($absolute_path)) {
            $this->getTranslator()->addResource('json', $absolute_path, $locale, $domain);
            return true;
        }
        return false;
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
                $json = file_get_contents($path);
                if ($json === false) {
                    throw new FileNotReadableError('Cannot read file "' . $path . '"!');
                }
                $array = json_decode($json, true);
                if ($array && null !== $locale = ($array['LOCALIZATION.LOCALE'] ?? null)) {
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
                $json = file_get_contents($this->domains[$domain]);
                if ($json === false) {
                    throw new FileNotReadableError('Cannot read file "' . $this->domains[$domain] . '"!');
                }
                $this->domains_data[$domain] = json_decode($json, true);
            }
            return $this->domains_data[$domain];
        } else {
            $cat = $this->getTranslator()->getCatalogue($this->getTranslator()->getLocale());
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
        if (($this->domains[$name] ?? null) !== null) {
            return true;
        }
        
        $filePath = $this->getTranslationsFolder() . DIRECTORY_SEPARATOR . $name . '.' . $this->getLanguage() . '.json';
        $exists = $this->addDictionaryFromFile($filePath, $this->getLocale(), $name);
        if ($exists) {
            $this->domains[$name] = $filePath;
        }
        return $exists;
    }
}