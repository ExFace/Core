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

abstract class AbstractCommunicationChannel implements CommunicationChannelInterface
{
    use ImportUxonObjectTrait;
    
    use AliasTrait;
    
    private $name = '';
    
    private $workbench = null;
    
    private $connection = null;
    
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
    
    protected function setName(string $name) : CommunicationChannelInterface
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
    
    public function getConnection() : DataConnectionInterface
    {
        return $this->connection;
    }
    
    public function setConnection(DataConnectionInterface $value) : AbstractCommunicationChannel
    {
        $this->connection = $value;
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