<?php
namespace exface\Core\CommonLogic\DataSheets;

use exface\Core\Interfaces\iCanBeConvertedToUxon;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\DataSheets\DataSheetStructureError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;

class DataSorter implements iCanBeConvertedToUxon, WorkbenchDependantInterface
{

    const DIRECTION_ASC = 'ASC';

    const DIRECTION_DESC = 'DESC';

    private $exface = null;

    private $attribute_alias = null;

    private $direction = null;

    private $data_sheet = null;

    function __construct(Workbench $exface)
    {
        $this->exface = $exface;
    }

    public function getAttributeAlias()
    {
        return $this->attribute_alias;
    }

    /**
     * 
     * @uxon-property attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $value
     * @throws DataSheetStructureError
     * @return \exface\Core\CommonLogic\DataSheets\DataSorter
     */
    public function setAttributeAlias($value)
    {
        if ($this->getDataSheet() && ! $this->getDataSheet()->getMetaObject()->hasAttribute($value)) {
            throw new DataSheetStructureError($this->getDataSheet(), 'Cannot add a sorter over "' . $value . '" to data sheet with object "' . $this->getDataSheet()->getMetaObject()->getAliasWithNamespace() . '": only sorters over meta attributes are supported!', '6UQBX9K');
        }
        $this->attribute_alias = $value;
        return $this;
    }

    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * 
     * @uxon-property direction
     * @uxon-type [asc,desc]
     * 
     * @param string $value
     * @throws UnexpectedValueException
     * @return \exface\Core\CommonLogic\DataSheets\DataSorter
     */
    public function setDirection($value)
    {
        if (strtoupper($value) == $this::DIRECTION_ASC) {
            $this->direction = $this::DIRECTION_ASC;
        } elseif (strtoupper($value) == $this::DIRECTION_DESC) {
            $this->direction = $this::DIRECTION_DESC;
        } else {
            throw new UnexpectedValueException('Invalid sort direction "' . $value . '" for a data sheet sorter: only DESC or ASC are allowed!', '6T5V9KS');
        }
        return $this;
    }

    public function getDataSheet()
    {
        return $this->data_sheet;
    }

    public function setDataSheet(DataSheetInterface $data_sheet)
    {
        if ($this->getAttributeAlias() && ! $data_sheet->getMetaObject()->hasAttribute($this->getAttributeAlias())) {
            throw new DataSheetStructureError($data_sheet, 'Cannot use a sorter over "' . $this->getAttributeAlias() . '" in data sheet with object "' . $this->getDataSheet()->getMetaObject()->getAliasWithNamespace() . '": only sorters over meta attributes are supported!', '6UQBX9K');
        }
        $this->data_sheet = $data_sheet;
        return $this;
    }

    public function exportUxonObject()
    {
        $uxon = new UxonObject();
        $uxon->setProperty('attribute_alias', $this->getAttributeAlias());
        $uxon->setProperty('direction', $this->getDirection());
        return $uxon;
    }

    public function importUxonObject(UxonObject $uxon)
    {
        $this->setAttributeAlias($uxon->getProperty('attribute_alias'));
        if ($direction = $uxon->getProperty('direction')) {
            $this->setDirection($direction);
        }
    }

    public function getWorkbench()
    {
        return $this->exface;
    }

    /**
     * Returns a copy of this sorter still belonging to the same data sheet
     *
     * @return DataSorter
     */
    public function copy() : self
    {
        return clone $this;
    }
    
    public function __toString()
    {
        return $this->getAttributeAlias() . ':' . $this->getDirection();
    }

    public function getAttribute() : MetaAttributeInterface
    {
        return $this->getDataSheet()->getMetaObject()->getAttribute($this->getAttributeAlias());
    }
    
    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return null;
    }
}