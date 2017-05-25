<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\CommonLogic\Workbench;
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
     * @return DataAggregator
     */
    public function get($key);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getFirst()
     * @return DataAggregator
     */
    public function getFirst();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getLast()
     * @return DataAggregator
     */
    public function getLast();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getNth()
     * @return DataAggregator
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
     * @return DataAggregatorList
     */
    public function addFromString($attribute_alias);

    /**
     *
     * @param array $uxon            
     * @return void
     */
    public function importUxonArray(array $uxon);
}
?>