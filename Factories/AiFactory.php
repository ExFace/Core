<?php
namespace exface\Core\Factories;

use exface\Core\CommonLogic\Selectors\AiAgentSelector;
use exface\Core\CommonLogic\Selectors\AiConceptSelector;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\Interfaces\AI\AiAgentInterface;
use exface\Core\Interfaces\AI\AiConceptInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\Selectors\AiAgentSelectorInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Produces AI framework components: agents, concepts, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AiFactory extends AbstractSelectableComponentFactory
{
    public static function createFromSelector(SelectorInterface $selector, array $constructorArguments = null)
    {
        switch (true) { 
            case ($selector instanceof AiAgentSelectorInterface) && $selector->isAlias():
                return static::createAgentFromString($selector->getWorkbench(), $selector->toString());
    
        }
        return parent::createFromSelector($selector, $constructorArguments);
    }
    /**
     *
     * @param string $aliasOrPathOrClassname            
     * @param WorkbenchInterface $exface            
     * @return FacadeInterface
     */
    public static function createConceptFromUxon(WorkbenchInterface $workbench, string $placeholder, UxonObject $uxon) : AiConceptInterface
    {
        if ($uxon->hasProperty('class')) {
            $selector = new AiConceptSelector($workbench, $uxon->getProperty('class'));
            $uxon->unsetProperty('class');
        } else {
            throw new UxonParserError($uxon, 'Cannot instatiate AI concept: no class property found in UXON model');
        }
        return static::createFromSelector($selector, [$workbench, $placeholder, $uxon]);
    }

    public static function createAgentFromString(WorkbenchInterface $workbench, string $aliasOrPathOrClass) : AiAgentInterface
    {
        $ds = DataSheetFactory::createFromObjectIdOrAlias($workbench, 'exface.Core.AI_AGENT');
        $ds->getFilters()->addConditionFromString('ALIAS_WITH_NS', $aliasOrPathOrClass, ComparatorDataType::EQUALS);
        $ds->getColumns()->addFromAttributeGroup($ds->getMetaObject()->getAttributes());
        $ds->dataRead();
        $row = $ds->getRow(0);

        $uxon = UxonObject::fromAnything($row['CONFIG_UXON']);
        $uxon->setProperty('data_connection_alias', $row['DATA_CONNECTION']);
        $uxon->setProperty('name', $row['NAME']);
        $uxon->setProperty('alias', $row['ALIAS']);

        $selector = new AiAgentSelector($workbench, $aliasOrPathOrClass);
        $prototypeSelector = new AiAgentSelector($workbench, $row['PROTOTYPE_CLASS']);
        $agent = static::createFromSelector($prototypeSelector, [$selector, $uxon]);

        return $agent;
    }
}