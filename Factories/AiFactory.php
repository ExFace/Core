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
use exface\Core\Interfaces\WorkbenchInterface;

/**
 * Produces AI framework components: agents, concepts, etc.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AiFactory extends AbstractSelectableComponentFactory
{
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
        // $uxon->setProperty('description', $row['DESCRIPTION']);
        $uxon->setProperty('alias', $row['ALIAS']);
        //$uxon->setProperty('app_uid', $row['APP']);

        $selector = new AiAgentSelector($workbench, $aliasOrPathOrClass);
        $prototypeSelector = new AiAgentSelector($workbench, $row['PROTOTYPE_CLASS']);
        $agent = static::createFromSelector($prototypeSelector, [$selector, $uxon]);

        return $agent;
    }
}