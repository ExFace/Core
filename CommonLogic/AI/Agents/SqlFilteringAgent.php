<?php
namespace exface\Core\CommonLogic\AI\Agents;

use exface\Core\CommonLogic\AI\AiResponse;
use exface\Core\CommonLogic\AI\DbmlModel;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\DataConnectors\TransparentConnector;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Interfaces\AI\AiAgentInterface;
use exface\Core\Interfaces\AI\AiPromptInterface;
use exface\Core\Interfaces\AI\AiResponseInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\SqlDataConnectorInterface;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchInterface;

class SqlFilteringAgent implements AiAgentInterface
{
    private $workbench = null;

    public function __construct(WorkbenchInterface $workbench)
    {
        $this->workbench = $workbench;
    }

    public function handle(AiPromptInterface $prompt) : AiResponseInterface
    {
        // Only export objects, that have an SQL data source
        // Create a filter function that will take care of filtering meta objects
        $targetConnectionAlias = $this->getTargetConnectionAlias($prompt);
        $objFilter = function(MetaObjectInterface $obj) use ($targetConnectionAlias) {
            $isSql = $obj->getDataConnection() instanceof SqlDataConnectorInterface;
            $isInTargetConnection = $obj->getDataConnection()->isExactly($targetConnectionAlias);
            $isTable = stripos($obj->getDataAddress(), '(') === false; // Otherwise it is a SQL statement like (SELECT ...)
            // TODO also only those, that are in the same database as the object we are filtering
            return $isSql && $isTable && $isInTargetConnection;
        };
        $dbmlModel = new DbmlModel($prompt->getWorkbench(), $objFilter);

        $userPromt = $prompt->getUserMessages()[0];
        $systemPrompt = <<<TEXT
            
        You have the following DBML model: 
        {$dbmlModel->toDBML()}

TEXT;
        /* TODO
        $connection = $this->getAiConnection();
        $result = $connection->query($userPrompt, $systemPromt);
        foreach ($result->getMessages() as $msg) {
            $sqlWhere = $this->findSqlInMessage($msg);
        }
        $this->testSql($sqlWhere);
        */
        
        $testJson = [
            "body" => [
                "id" => "cmpl-7QmVI15qgYVllxK0FtxVGG6ywfzaq",
                "created" => 1686617332,
                "choices" => [
                    [
                        "text" => 'Here is the response from the real LLM',
                        "index" => 0,
                        "finish_reason" => "stop",
                        "logprobs" => null
                    ]
                ],
                "usage" => [
                    "completion_tokens" => 20,
                    "prompt_tokens" => 6,
                    "total_tokens" => 26
                ]
            ],
            // TODO Remove this. It's just for initial debugging!
            "dbml" => $dbmlModel->toArray()
        ];
        
        return new AiResponse($prompt, $testJson);
    }

    private function getTargetConnectionAlias(AiPromptInterface $promt) : string
    {
        // TODO find out what app we are interested in
        return 'exface.Core.METAMODEL_DB';
    }
}