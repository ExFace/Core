<?php
namespace exface\Core\CommonLogic;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UxonMapError;
use exface\Core\Exceptions\ModelBuilders\ModelBuilderNotAvailableError;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;
use exface\Core\CommonLogic\Traits\AliasTrait;

abstract class AbstractDataConnector implements DataConnectionInterface
{
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}
	use AliasTrait;

    private $config_array = array();

    private $exface = null;
    
    private $selector = null;

    /**
     *
     * @deprecated Use DataConnectorFactory instead!
     */
    public function __construct(DataConnectorSelectorInterface $selector, UxonObject $config = null)
    {
        $this->exface = $selector->getWorkbench();
        $this->selector = $selector;
        if ($config !== null) {
            $this->importUxonObject($config);
        }
    }
    
    public function getSelector() : DataConnectorSelectorInterface
    {
        return $this->selector;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        return new UxonObject();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        try {
            return $this->importUxonObjectDefault($uxon);
        } catch (UxonMapError $e) {
            throw new DataConnectionConfigurationError($this, 'Invalid data connection configuration: ' . $e->getMessage(), '6T4F41P', $e);
        }
        return;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::connect()
     */
    public final function connect()
    {
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Connect.Before'));
        $result = $this->performConnect();
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Connect.After'));
        return $result;
    }

    protected abstract function performConnect();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::disconnect()
     */
    public final function disconnect()
    {
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Disconnect.Before'));
        $result = $this->performDisconnect();
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Disconnect.After'));
        return $result;
    }

    protected abstract function performDisconnect();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::query()
     */
    public final function query(DataQueryInterface $query)
    {
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Query.Before', $query));
        $result = $this->performQuery($query);
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Query.After', $query));
        return $result;
    }

    protected abstract function performQuery(DataQueryInterface $query);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\WorkbenchDependantInterface::getWorkbench()
     */
    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionStart()
     */
    public abstract function transactionStart();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionCommit()
     */
    public abstract function transactionCommit();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionRollback()
     */
    public abstract function transactionRollback();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::transactionIsStarted()
     */
    public abstract function transactionIsStarted();
    
    
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\SqlDataConnectorInterface::getModelBuilder()
     */
    public function getModelBuilder()
    {
        throw new ModelBuilderNotAvailableError('No model builder implemented for data connector ' . $this->getAliasWithNamespace() . '!');
    }
}