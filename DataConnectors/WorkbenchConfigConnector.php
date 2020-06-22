<?php
namespace exface\Core\DataConnectors;

use exface\Core\Interfaces\DataSources\DataQueryInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;
use exface\UrlDataConnector\Psr7DataQuery;
use function GuzzleHttp\Psr7\stream_for;

/**
 * Reads the combined JSON of the current configuration: system, app, user scope, etc.
 * 
 * @author Andrej Kabachnik
 *        
 */
class WorkbenchConfigConnector extends TransparentConnector
{

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     * 
     * @return DataQueryInterface
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof Psr7DataQuery)) {
            throw new DataConnectionQueryTypeError($this, 'Connector "' . $this->getAliasWithNamespace() . '" expects a Psr7DataQuery as input, "' . get_class($query) . '" given instead!');
        }
            
        $query->setResponse(new Response(200, array(), stream_for($this->getWorkbench()->getConfig()->exportUxonObject()->toJson())));
        return $query;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        if ($this->getWorkbench()->isStarted() === false) {
            $this->getWorkbench()->start();
        }
        return;
    }
}
?>