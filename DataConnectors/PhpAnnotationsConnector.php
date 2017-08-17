<?php
namespace exface\Core\DataConnectors;

use exface\Core\CommonLogic\DataQueries\PhpAnnotationsDataQuery;
use Wingu\OctopusCore\Reflection\ReflectionClass;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\Exceptions\DataSources\DataConnectionQueryTypeError;

class PhpAnnotationsConnector extends FileContentsConnector
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     *
     */
    protected function performQuery(DataQueryInterface $query)
    {
        if (! ($query instanceof PhpAnnotationsDataQuery))
            throw new DataConnectionQueryTypeError($this, 'DataConnector "' . $this->getAliasWithNamespace() . '" expects an instance of PhpAnnotationsDataQuery as query, "' . get_class($query) . '" given instead!');
        
        if (! $query->getBasePath() && $this->getBasePath()) {
            $query->setBasePath($this->getBasePath());
        }
        
        $query->setReflectionClass(new ReflectionClass($query->getClassNameWithNamespace()));
        return $query;
    }
}
?>