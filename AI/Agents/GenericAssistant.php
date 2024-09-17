<?php
namespace exface\Core\AI\Agents;

use exface\Core\CommonLogic\AI\AiResponse;
use exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Factories\AiFactory;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\Interfaces\AI\AiAgentInterface;
use exface\Core\Interfaces\AI\AiPromptInterface;
use exface\Core\Interfaces\AI\AiResponseInterface;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Selectors\AiAgentSelectorInterface;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\Templates\BracketHashStringTemplateRenderer;
use exface\Core\Templates\Placeholders\ConfigPlaceholders;
use exface\Core\Templates\Placeholders\FormulaPlaceholders;

/**
 * Generic chat assistant with configurable system prompt
 * 
 * ## Examples
 * 
 * ```
 * {
 *   "system_prompt": "
 *      You are a helpful assistant, who will answer questions about the structure of the following database. 
 *      Here is the DB schema in DBML: \n\n[#metamodel_dbml#]
 *      Answer using the following locale \"[#=User('LOCALE')#]\"
 *   ",
 *   "system_concepts": {
 *     "metamodel_bmdb": {
 *       "class": "\\exface\\Core\\AI\\Concepts\\MetamodelDbmlConcept",
 *       "object_filters": {
 *         "operator": "AND",
 *         "conditions": [
 *           {"expression": "APP__ALIAS", "comparator": "==", "value": "exface.Core"}
 *         ]
 *       }
 *     }
 *   }
 * }
 * 
 * ```
 * 
 * @author Andrej Kabachnik
 */
class GenericAssistant implements AiAgentInterface
{
    use ImportUxonObjectTrait;

    use AliasTrait;

    private $workbench = null;

    private $systemPrompt = null;

    private $systemPromptRendered = null;

    private $concepts = [];

    private $dataConnectionAlias = null;

    private $dataConnection = null;

    private $name = null;

    private $selector = null;

    /**
     * 
     * @param \exface\Core\Interfaces\Selectors\AiAgentSelectorInterface $selector
     * @param \exface\Core\CommonLogic\UxonObject|null $uxon
     */
    public function __construct(AiAgentSelectorInterface $selector, UxonObject $uxon = null)
    {
        $this->workbench = $selector->getWorkbench();
        $this->selector = $selector;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    public function handle(AiPromptInterface $prompt) : AiResponseInterface
    {
        $userPromt = $prompt->getUserPrompt();
        $systemPrompt = $this->getSystemPrompt($prompt);
        
        $query = new OpenAiApiDataQuery($this->workbench);
        $query->setSystemPrompt($systemPrompt);
        $query->appendMessage($userPromt);
        if (null !== $val = $prompt->getConversationUid()) {
            $query->setConversationUid($val);
        }

        $performedQuery = $this->getConnection()->query($query);
        
        return $this->parseDataQueryResponse($prompt, $performedQuery);
    }

    /**
     * AI concepts to be used in the system prompt
     * 
     * Each concept is basically a plugin, that generates part of the system prompt. You can use it anywhere in your
     * prompt via placeholder
     * 
     * @uxon-property system_concepts
     * @uxon-type \exface\Core\CommonLogic\AI\AbstractConcept
     * @uxon-template {"metamodel_bmdb": {"class": "\\exface\\Core\\AI\\Concepts\\MetamodelDbmlConcept"}}
     * 
     * @param \exface\Core\CommonLogic\UxonObject $arrayOfConcepts
     * @return \exface\Core\Interfaces\AI\AiAgentInterface
     */
    protected function setSystemConcepts(UxonObject $arrayOfConcepts) : AiAgentInterface
    {
        foreach ($arrayOfConcepts as $placeholder => $uxon) {
            $this->concepts[] = AiFactory::createConceptFromUxon($this->workbench,$placeholder, $uxon);
        }
        return $this;
    }

    /**
     * 
     * @return array
     */
    protected function getSystemConcepts(AiPromptInterface $promt) : array
    {
        return $this->concepts;
    }

    /**
     * An introduction to explain the LLM, what the assistant is supposed to do
     * 
     * @uxon-property system_prompt
     * @uxon-type string
     * @uxon-template You are a helpful assistant, who will answer questions about the structure of the following database. Here is the DB schema in DBML: \n\n[#metamodel_dbml#] \n\nAnswer using the following locale [#=User('LOCALE')#]
     * 
     * @param string $text
     * @return \exface\Core\Interfaces\AI\AiAgentInterface
     */
    protected function setSystemPrompt(string $text) : AiAgentInterface
    {
        $this->systemPrompt = $text;
        return $this;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\AI\AiPromptInterface $promt
     * @return string
     */
    protected function getSystemPrompt(AiPromptInterface $prompt) : string
    {
        if ($this->systemPromptRendered === null) {
            $renderer = new BracketHashStringTemplateRenderer($this->workbench);
            $renderer->addPlaceholder(new FormulaPlaceholders($this->workbench, null, null, '='));
            $renderer->addPlaceholder(new ConfigPlaceholders($this->workbench, '~config:'));
            
            foreach ($this->getSystemConcepts($prompt) as $concept) {
                $renderer->addPlaceholder($concept);
            }
            
            $this->systemPromptRendered = $renderer->render($this->systemPrompt ?? '');
        }
        return $this->systemPromptRendered;
    }

    /**
     * 
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        // TODO
        return $uxon;
    } 

    /**
     * 
     * @return \exface\Core\Interfaces\DataSources\DataConnectionInterface
     */
    protected function getConnection() : DataConnectionInterface
    {
        if ($this->dataConnection === null) {
            $this->dataConnection = DataConnectionFactory::createFromModel($this->workbench, $this->dataConnectionAlias);
        }
        return $this->dataConnection;
    }
    
    /**
     * 
     * @param string $selector
     * @return \exface\Core\Interfaces\AI\AiAgentInterface
     */
    protected function setDataConnectionAlias(string $selector) : AiAgentInterface
    {
        $this->dataConnectionAlias = $selector;
        return $this;
    }

    /**
     * 
     * @param \exface\Core\Interfaces\AI\AiPromptInterface $prompt
     * @param \exface\Core\CommonLogic\DataQueries\OpenAiApiDataQuery $query
     * @return \exface\Core\CommonLogic\AI\AiResponse
     */
    protected function parseDataQueryResponse(AiPromptInterface $prompt, OpenAiApiDataQuery $query) : AiResponse
    {
        return new AiResponse($prompt, $query->getResponseData());
    }

    /**
     * 
     * @param string $alias
     * @return \exface\Core\Interfaces\AI\AiAgentInterface
     */
    protected function setAlias(string $alias) : AiAgentInterface
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * 
     * @return \exface\Core\Interfaces\Selectors\AliasSelectorInterface
     */
    public function getSelector() : AliasSelectorInterface
    {
        return $this->selector;
    }

    /**
     * 
     * @param string $name
     * @return \exface\Core\Interfaces\AI\AiAgentInterface
     */
    protected function setName(string $name) : AiAgentInterface
    {
        $this->name = $name;
        return $this;
    }
}