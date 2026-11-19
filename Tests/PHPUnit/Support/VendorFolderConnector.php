<?php
namespace exface\Core\Tests\PHPUnit\Support;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\DataConnectors\LocalFileConnector;
use exface\Core\Interfaces\Selectors\DataConnectorSelectorInterface;

/**
 * Provides a known connection identity before a factory returns, without a model database.
 */
class VendorFolderConnector extends LocalFileConnector
{
    /**
     * Initializes a real connector with the vendor connection's alias.
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::__construct()
     */
    public function __construct(DataConnectorSelectorInterface $prototypeSelector, UxonObject $config = null)
    {
        parent::__construct($prototypeSelector, $config);
        $this->setAlias('LOCAL_VENDOR_FOLDERS', 'exface.Core');
    }
}
