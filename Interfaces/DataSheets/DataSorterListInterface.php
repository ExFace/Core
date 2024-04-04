<?php
namespace exface\Core\Interfaces\DataSheets;

use exface\Core\Interfaces\EntityListInterface;

interface DataSorterListInterface extends EntityListInterface
{

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getAll()
     * @return DataSorterInterface[]
     */
    public function getAll();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::get()
     * @return DataSorterInterface
     */
    public function get($key);

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getFirst()
     * @return DataSorterInterface
     */
    public function getFirst();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getLast()
     * @return DataSorterInterface
     */
    public function getLast();

    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\Interfaces\EntityListInterface::getNth()
     * @return DataSorterInterface
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
     * @return DataSorterListInterface
     */
    public function addFromString($attribute_alias, $direction = 'ASC');
}
?>