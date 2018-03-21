<?php
namespace exface\Core\Formulas;

use exface\Core\CommonLogic\NameResolver;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\CommonLogic\Workbench;

class Translate extends \exface\Core\CommonLogic\Model\Formula
{
    
    public function run(string $translationKey)
    {
        try {
            $numOfPoints = substr_count($translationKey, NameResolver::NAMESPACE_SEPARATOR);
            if ($numOfPoints < 2) {
                throw new InvalidArgumentException('The translation key must be prepended with the app alias, i.e. "exface.Core.TRANSLATION_KEY".');
            }
            $sndPointPos = strpos($translationKey, NameResolver::NAMESPACE_SEPARATOR, strpos($translationKey, NameResolver::NAMESPACE_SEPARATOR) + 1);
            $appAlias = substr($translationKey, 0, $sndPointPos);
            $key = substr($translationKey, $sndPointPos + 1);
            $output = $this->getWorkbench()->getApp($appAlias)->getTranslator()->translate($key);
            return $output;
        } catch (\Exception $e) {
            return $translationKey;
        }
    }
    
    public static function isTranslationKey(string $translationKey)
    {
        $key = trim($translationKey);
        if (substr($key, 0, 1) == '%' && substr($key, count($key) - 1, 1) == '%') {
            return true;
        }
        return false;
    }
    
    public static function translate(Workbench $exface, string $translationKey) {
        try {
            $translationKey = trim($translationKey, " \t\n\r\0\x0B%");
            $numOfPoints = substr_count($translationKey, NameResolver::NAMESPACE_SEPARATOR);
            if ($numOfPoints < 2) {
                throw new InvalidArgumentException('The translation key must be prepended with the app alias, i.e. "exface.Core.TRANSLATION_KEY".');
            }
            $sndPointPos = strpos($translationKey, NameResolver::NAMESPACE_SEPARATOR, strpos($translationKey, NameResolver::NAMESPACE_SEPARATOR) + 1);
            $appAlias = substr($translationKey, 0, $sndPointPos);
            $key = substr($translationKey, $sndPointPos + 1);
            $output = $exface->getApp($appAlias)->getTranslator()->translate($key);
            return $output;
        } catch (\Exception $e) {
            return $translationKey;
        }
    }
}
