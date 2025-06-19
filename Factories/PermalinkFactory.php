<?php

namespace exface\Core\Factories;

use exface\Core\CommonLogic\Selectors\PermalinkSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\PhpFilePathDataType;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Interfaces\Permalinks\PermalinkInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Manages the creation of permalinks.
 */
class PermalinkFactory extends AbstractStaticFactory
{
    /**
     * Create a new permalink instance based on a URL or selector string.
     *
     * NOTE: The created instance might not be initialized, especially if the selector
     * or URL did not point to a config alias.
     *
     * @param WorkbenchInterface $workbench
     * @param string             $urlOrSelector
     * @return PermalinkInterface
     */
    public static function fromUrlOrSelector(WorkbenchInterface $workbench, string $urlOrSelector) : PermalinkInterface
    {
        $urlOrSelector = StringDataType::substringAfter($urlOrSelector, PermalinkInterface::API_ROUTE . '/', $urlOrSelector);
        list($alias, $innerPath) = explode('/', $urlOrSelector, 2);
        $selector = new PermalinkSelector($workbench, $alias);

        $configUxon = null;
        switch (true) {
            case  $selector->isClassname():
                $class = $selector->toString();
                break;
            case $selector->isFilepath():
                $class = PhpFilePathDataType::findClassInFile($selector->toString());
                break;
            case $selector->isAlias():
                $sheet = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.PERMALINK');

                $sheet->getColumns()->addMultiple([
                    'NAME',
                    'PROTOTYPE_FILE',
                    'CONFIG_UXON'
                ]);

                $aliasOfSelector = StringDataType::substringAfter($selector->toString(), $selector->getAppAlias() . AliasSelectorInterface::ALIAS_NAMESPACE_DELIMITER);
                $sheet->getFilters()->addConditionFromString('APP__ALIAS', $selector->getAppAlias(), ComparatorDataType::EQUALS);
                $sheet->getFilters()->addConditionFromString('ALIAS', $aliasOfSelector, ComparatorDataType::EQUALS);
                $sheet->dataRead();

                switch ($sheet->countRows()) {
                    case 0:
                        throw new InvalidArgumentException('Could not find config for permalink with alias "' . $aliasOfSelector . '": Make sure a permalink with this alias exists and contains config data!');
                    case 1:
                        $row = $sheet->getRow();
                        break;
                    default:
                        throw new InvalidArgumentException('Permalink alias "' . $aliasOfSelector . '" is ambiguous for app "' . $selector->getAppSelector()->getAppAlias() . '": Make sure only one permalink with this alias exists in that app!');
                }

                $app = $workbench->getApp($selector->getAppSelector());
                $class = $app->getPrototypeClass(new PermalinkSelector($workbench, $row['PROTOTYPE_FILE']));
                $configUxon = UxonObject::fromJson($row['CONFIG_UXON']);
                break;
            default:
                throw new InvalidArgumentException('Could not create permalink: "' . $selector->toString() . '" is not a valid classname, filepath or alias!');
        }

        $instance = new $class($workbench, $alias, $configUxon);

        if(empty($innerPath)) {
            return $instance;
        } else {
            return $instance->withUrl($innerPath);
        }
    }

    /**
     * Returns a user-friendly, relative permalink with the given parameters.
     *
     * @param string             $configAlias
     * @param string             $innerUrl
     * @return string
     */
    public static function buildRelativePermalinkUrl(string $configAlias, string $innerUrl) : string
    {
        return PermalinkInterface::API_ROUTE . '/' . $configAlias . '/' . $innerUrl;
    }
    
    /**
     * Returns a user-friendly, absolute permalink with the given parameters. 
     * 
     * @param WorkbenchInterface $workbench
     * @param string             $configAlias
     * @param string             $innerUrl
     * @return string
     */
    public static function buildAbsolutePermalinkUrl(WorkbenchInterface $workbench, string $configAlias, string $innerUrl) : string
    {
        return $workbench->getUrl() . self::buildRelativePermalinkUrl($configAlias, $innerUrl);
    }
}