<?php
namespace exface\Core\AI\Agents;

use exface\Core\AI\Concepts\MetamodelDbmlConcept;
use exface\Core\CommonLogic\AI\AiResponse;
use exface\Core\CommonLogic\AI\DbmlModel;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Interfaces\AI\AiAgentInterface;
use exface\Core\Interfaces\AI\AiPromptInterface;
use exface\Core\Interfaces\AI\AiResponseInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Templates\Placeholders\ArrayPlaceholders;

class SqlFilteringAssistant extends GenericAssistant
{
    protected function getSystemConcepts(AiPromptInterface $prompt) : array
    {
        $concepts = parent::getSystemConcepts($prompt);
        foreach ($concepts as $concept) {
            if ($concept instanceof MetamodelDbmlConcept) {
                if ($prompt->hasMetaObject()) {
                    $obj = $prompt->getMetaObject();
                    $targetConnectionAlias = $obj->getDataConnection()->getAliasWithNamespace();
                } else {
                    throw new RuntimeException('Cannot generate AI filter: no base object specified in prompt');
                }
                $objFilter = function(MetaObjectInterface $obj) use ($targetConnectionAlias) {
                    $isSql = $obj->getDataConnection() instanceof SqlDataConnectorInterface;
                    $isInTargetConnection = $obj->getDataConnection()->isExactly($targetConnectionAlias);
                    $isTable = stripos($obj->getDataAddress(), '(') === false; // Otherwise it is a SQL statement like (SELECT ...)
                    // TODO also only those, that are in the same database as the object we are filtering
                    return $isSql && $isTable && $isInTargetConnection;
                };
                $concept->setObjectFilterCallback($objFilter);
            }
        }
        $concepts[] = new ArrayPlaceholders([
            'main_table_address' => $prompt->getMetaObject()->getDataAddress()
        ]);
        return $concepts;
    }
}