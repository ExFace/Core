<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\DataTypes\BinaryDataType;

/**
 * Shows an embedded PDF from  an attribute's data.
 *
 * @author Andrej Kabachnik
 *        
 */
class PDFViewer extends Display implements iFillEntireContainer
{
    
    /**
     * Returns TRUE if the PDF is represented by an URL and FALSE otherwise
     * 
     * @return bool
     */
    public function isValueUrl() : bool
    {
        return $this->getValueDataType() instanceof UrlDataType;
    }

    /**
     * 
     * @return bool
     */
    public function isValueBinary() : bool
    {
        return $this->getValueDataType() instanceof BinaryDataType;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings(): ?iContainOtherWidgets
    {
        return null;
    }
}