<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\EntityListInterface;

interface DataAggregationListInterface extends EntityListInterface
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getAll()
     * @return DataAggregationInterface[]
     */
    public function getAll();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::get()
     * @return DataAggregationInterface
     */
    public function get($key);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getFirst()
     * @return DataAggregationInterface
     */
    public function getFirst();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getLast()
     * @return DataAggregationInterface
     */
    public function getLast();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getNth()
     * @return DataAggregationInterface
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
     * @return DataAggregationListInterface
     */
    public function addFromString($attribute_alias);
}
?>