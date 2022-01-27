<?php
namespace exface\Core\CommonLogic\Communication;

use exface\Core\Interfaces\Communication\CommunicationChannelInterface;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Traits\AliasTrait;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\Selectors\CommunicationChannelSelectorInterface;
use exface\Core\CommonLogic\Selectors\Traits\AliasSelectorTrait;
use exface\Core\Interfaces\Selectors\AliasSelectorInterface;
use exface\Core\CommonLogic\Selectors\DataConnectionSelector;
use exface\Core\Interfaces\Selectors\DataConnectionSelectorInterface;
use exface\Core\Factories\DataConnectionFactory;
use exface\Core\CommonLogic\Selectors\CommunicationChannelSelector;

abstract class AbstractCommunicationChannel implements CommunicationChannelInterface
{
    use ImportUxonObjectTrait;
    
    use AliasTrait {
        getAlias as getAliasFromSelector;
    }
    
    private $alias = null;
    
    private $name = '';
    
    private $workbench = null;
    
    private $connection = null;
    
    private $connectionSelector = null;
    
    private $appSelector = null;
    
    private $selector = null;
    
    public function __construct(CommunicationChannelSelectorInterface $selector, UxonObject $config = null)
    {
        $this->selector = $selector;
        $this->workbench = $selector->getWorkbench();
        if ($config !== null) {
            $this->importUxonObject($config);
        }
    }
    
    /**
     * {@inheritdoc}
     * @see AliasSelectorTrait::getSelector()
     */
    public function getSelector() : AliasSelectorInterface
    {
        return $this->selector;
    }
    
    public function getName(): string
    {
        return $this->getName();
    }
    
    public function setName(string $name) : CommunicationChannelInterface
    {
        $this->name = $name;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->workbench;
    }

    /**
     * 
     * @return string|NULL
     */
    public static function getUxonSchemaClass(): ?string
    {
        return null;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Communication\CommunicationChannelInterface::getConnection()
     */
    public function getConnection() : DataConnectionInterface
    {
        if ($this->connection === null && $this->connectionSelector !== null) {
            $this->connection = DataConnectionFactory::createFromSelector($this->connectionSelector);
        }
        return $this->connection;
    }
    
    /**
     * 
     * @param DataConnectionInterface|DataConnectionSelectorInterface|string $connectionOrSelectorOrString
     * @return AbstractCommunicationChannel
     */
    public function setConnection($connectionOrSelectorOrString) : AbstractCommunicationChannel
    {
        $this->connection = null;
        $this->connectionSelector = null;
        switch (true) {
            case $connectionOrSelectorOrString instanceof DataConnectionInterface:
                $this->connection = $connectionOrSelectorOrString;
                break;
            case $connectionOrSelectorOrString instanceof DataConnectionSelectorInterface:
                $this->connectionSelector = $connectionOrSelectorOrString;
                break;
            default:
                $this->connectionSelector = new DataConnectionSelector($this->getWorkbench(), $connectionOrSelectorOrString);
        }
        return $this;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\AliasInterface::getAlias()
     * @see AliasTrait::getAlias()
     */
    public function getAlias()
    {
        if ($this->alias === null) {
            if ($this->selector->isAlias()) {
                $this->alias = $this->selector::stripNamespace($this->selector->toString());
            } else {
                $this->alias = $this->getAliasFromSelector();
            }
        }
        return $this->alias;
    }
    
    /**
     * Alias of the data type
     *
     * @uxon-property alias
     * @uxon-type metamodel:datatype
     *
     * @param string $string
     */
    public function setAlias($string)
    {
        $selector = new CommunicationChannelSelector($this->getWorkbench(), $string);
        $this->selector = $selector;
        $this->alias = null;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    protected function getClassnameSuffixToStripFromAlias() : string
    {
        return 'Channel';
    }
}