<?php
namespace exface\Core\DataTypes;

use exface\Core\Interfaces\DataTypes\EnumDataTypeInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Enumeration of currently installed locales: en_EN, de_DE, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
class LocaleDataType extends StringDataType implements EnumDataTypeInterface
{
    private $locales = null;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabelOfValue()
     */
    public function getLabelOfValue($value = null, string $inLocale = null): string
    {
        if ($inLocale === null) {
            $inLocale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        }
        return \Locale::getDisplayName($value, $inLocale); 
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getLabels()
     */
    public function getLabels()
    {
        if ($this->locales === null) {
            $this->locales = [];
            $currentLocale = $this->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
            foreach ($this->getWorkbench()->getCoreApp()->getLanguages() as $locale) {
                $this->locales[$locale] = $this->getLabelOfValue($locale, $currentLocale);
            }
        }
        return $this->locales;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::setValues()
     */
    public function setValues($uxon_or_array)
    {
        if ($uxon_or_array instanceof UxonObject) {
            $array = $uxon_or_array->toArray();
        } else {
            $array = $uxon_or_array;
        }
        $this->locales = array_merge($this->getLabels(), $array);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::toArray()
     */
    public function toArray(): array
    {
        return $this->getLabels();
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\DataTypes\EnumDataTypeInterface::getValues()
     */
    public function getValues()
    {
        return array_keys($this->getLabels());
    }
}