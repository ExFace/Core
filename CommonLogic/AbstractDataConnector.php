<?php
namespace exface\Core\CommonLogic;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\NameResolverInterface;
use exface\Core\Factories\EventFactory;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionConfigurationError;
use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\Exceptions\UxonMapError;

abstract class AbstractDataConnector implements DataConnectionInterface
{
    
    use ImportUxonObjectTrait {
		importUxonObject as importUxonObjectDefault;
	}

    private $config_array = array();

    private $exface = null;

    /**
     *
     * @deprecated Use DataConnectorFactory instead!
     */
    function __construct(Workbench $exface, array $config = null)
    {
        $this->exface = $exface;
        if ($config) {
            $this->importUxonObject(UxonObject::fromArray($config));
        }
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
     * @return NameResolverInterface
     */
    public function getNameResolver()
    {
        return $this->name_resolver;
    }

    /**
     *
     * @param NameResolverInterface $value            
     */
    public function setNameResolver(NameResolverInterface $value)
    {
        $this->name_resolver = $value;
        return $this;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getAlias()
     */
    public function getAlias()
    {
        return $this->getNameResolver()->getAlias();
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\DataSources\DataConnectionInterface::getAliasWithNamespace()
     */
    public function getAliasWithNamespace()
    {
        return $this->getNameResolver()->getAliasWithNamespace();
    }

    public function getNamespace()
    {
        return $this->getNameResolver()->getNamespace();
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
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Query.Before'));
        $result = $this->performQuery($query);
        $this->getWorkbench()->eventManager()->dispatch(EventFactory::createDataConnectionEvent($this, 'Query.After'));
        $this->getWorkbench()->getLogger()->notice('Performed data query via "' . $this->getAliasWithNamespace() . '"', array(), $query);
        return $result;
    }

    protected abstract function performQuery(DataQueryInterface $query);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\ExfaceClassInterface::getWorkbench()
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
}