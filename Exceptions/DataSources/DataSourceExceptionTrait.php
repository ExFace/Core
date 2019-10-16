<?php
namespace exface\Core\Exceptions\DataSources;

use exface\Core\Interfaces\DataSources\DataConnectionInterface;
use exface\Core\Interfaces\DataSources\DataSourceInterface;

/**
 * This trait enables an exception to output data source specific debug information.
 *
 * @author Andrej Kabachnik
 *        
 */
trait DataSourceExceptionTrait {
    
    private $dataSource = null;

    /**
     *
     * @param DataConnectionInterface $dataSource            
     * @param string $message            
     * @param string $alias            
     * @param \Throwable $previous            
     */
    public function __construct(DataSourceInterface $dataSource, $message, $alias = null, $previous = null)
    {
        parent::__construct($message, null, $previous);
        $this->setAlias($alias);
        $this->dataSource = $dataSource;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\Exceptions\DataSourceExceptionInterface::getDataSource()
     */
    public function getDataSource() : DataSourceInterface
    {
        return $this->dataSource;
    }
}