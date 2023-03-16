<?php
namespace exface\Core\Factories;

use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;
use exface\Core\Interfaces\Communication\CommunicationMessageInterface;
use exface\Core\Communication\Messages\TextMessage;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Communication\CommunicationChannel;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\Selectors\CommunicationMessageSelectorInterface;
use exface\Core\Interfaces\Communication\CommunicationTemplateInterface;
use exface\Core\Interfaces\Selectors\CommunicationTemplateSelectorInterface;
use exface\Core\CommonLogic\Communication\CommunicationTemplate;
use exface\Core\CommonLogic\Selectors\CommunicationTemplateSelector;
use exface\Core\CommonLogic\Selectors\CommunicationMessageSelector;
use exface\Core\CommonLogic\Selectors\PWASelector;
use exface\Core\Interfaces\PWA\PWAInterface;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Interfaces\Facades\PWAFacadeInterface;
use exface\Core\Interfaces\Selectors\PWASelectorInterface;
use exface\Core\Exceptions\InvalidArgumentException;
use exface\Core\Exceptions\RuntimeException;

/**
 * Produces components related to the communication framework
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class PWAFactory extends AbstractSelectableComponentFactory
{

    /**
     * 
     * @param SelectorInterface $selector
     * @param array $constructorArguments
     * @return \exface\Core\Interfaces\Communication\CommunicationChannelInterface|mixed
     */
    public static function createFromSelector(SelectorInterface $selector, array $constructorArguments = null)
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($selector->getWorkbench(), 'exface.Core.PWA');
        $ds->getColumns()->addFromUidAttribute();
        $ds->getColumns()->addMultiple([
            'PAGE_TEMPLATE__FACADE'
        ]);
        if ($selector->isUid()) {
            $ds->getFilters()->addConditionFromString('UID', $selector->toString(), ComparatorDataType::EQUALS);
        } else {
            $ds->getFilters()->addConditionFromString('ALIAS', $selector->toString(), ComparatorDataType::EQUALS);
        }
        $ds->getFilters()->addConditionFromString('ACTIVE_FLAG', true, ComparatorDataType::EQUALS);
        
        $ds->dataRead();
        switch ($ds->countRows()) {
            case 0:
                throw new RuntimeException('No active PWA found with selector "' . $selector->toString() . '"!');
            case 1:
                break;
            default:
                throw new RuntimeException('Multiple active PWAs found with selector "' . $selector->toString() . '"!');
        }
        $pwaUid = $ds->getUidColumn()->getValue(0);
        $facadeClass = $ds->getColumns()->get('PAGE_TEMPLATE__FACADE')->getValue(0);
        $facade = FacadeFactory::createFromString($facadeClass, $selector->getWorkbench());
        if (! $facade instanceof PWAFacadeInterface) {
            throw new InvalidArgumentException('Cannot create PWA in facade ' . $facade->getAliasWithNamespace() . ': this facade does not support progressive web apps!');
        }
        return $facade->getPWA($pwaUid);
    }
    
    public static function createFromURL(WorkbenchInterface $workbench, string $baseUrl) : PWAInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.PWA');
        $ds->getColumns()->addFromUidAttribute();
        $ds->getColumns()->addMultiple([
            'PAGE_TEMPLATE__FACADE'
        ]);
        $ds->getFilters()->addConditionFromString('URL', $baseUrl, ComparatorDataType::EQUALS);
        $ds->getFilters()->addConditionFromString('ACTIVE_FLAG', true, ComparatorDataType::EQUALS);
        
        $ds->dataRead();
        switch ($ds->countRows()) {
            case 0:
                throw new RuntimeException('No active PWA found with URL "' . $baseUrl . '"!');
            case 1:
                break;
            default:
                throw new RuntimeException('Multiple active PWAs found with selector "' . $baseUrl . '"!');
        }
        $pwaUid = $ds->getUidColumn()->getValue(0);
        $facadeClass = $ds->getColumns()->get('PAGE_TEMPLATE__FACADE')->getValue(0);
        $facade = FacadeFactory::createFromString($facadeClass, $workbench);
        if (! $facade instanceof PWAFacadeInterface) {
            throw new InvalidArgumentException('Cannot create PWA in facade ' . $facade->getAliasWithNamespace() . ': this facade does not support progressive web apps!');
        }
        return $facade->getPWA($pwaUid);
    }
    
    /**
     * 
     * @param WorkbenchInterface $workbench
     * @param string $aliasOrClassOrPath
     * @return CommunicationChannelInterface
     */
    public static function createFromString(WorkbenchInterface $workbench, string $aliasOrUid) : PWAInterface
    {
        $selector = new PWASelector($workbench, $aliasOrUid);
        return static::createFromSelector($selector);
    }
    
    /**
     * 
     * @param PWAFacadeInterface $facade
     * @param PWASelectorInterface|string $pwaSelectorOrString
     * @return PWAInterface
     */
    public static function crateFromFacade(PWAFacadeInterface $facade, $pwaSelectorOrString) : PWAInterface
    {
        return $facade->getPWA($pwaSelectorOrString);
    }
}