<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\DataSheets\DataAggregatorInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Interfaces\EntityListInterface;

interface DataAggregatorListInterface extends EntityListInterface
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getAll()
     * @return DataAggregatorInterface[]
     */
    public function getAll();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::get()
     * @return DataAggregatorInterface
     */
    public function get($key);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getFirst()
     * @return DataAggregatorInterface
     */
    public function getFirst();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getLast()
     * @return DataAggregatorInterface
     */
    public function getLast();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getNth()
     * @return DataAggregatorInterface
     */
    public function getNth($number);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getParent()
     * @return DataSheetInterface
     */
    public function getParent();

    /**
     *
     * @param string $attribute_alias            
     * @return DataAggregatorListInterface
     */
    public function addFromString($attribute_alias);
}
?>