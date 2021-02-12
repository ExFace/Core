<?php
namespace exface\Core\Widgets;

use exface\Core\Interfaces\Widgets\iContainOtherWidgets;
use exface\Core\Interfaces\Widgets\iFillEntireContainer;
use exface\Core\DataTypes\UrlDataType;
use exface\Core\DataTypes\BinaryDataType;
use exface\Core\Exceptions\Widgets\WidgetConfigurationError;
use exface\Core\Interfaces\Model\MetaAttributeInterface;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Events\Widget\OnPrefillChangePropertyEvent;
use exface\Core\Factories\DataPointerFactory;
use exface\Core\CommonLogic\DataSheets\DataColumn;

/**
 * Shows an embedded PDF from an attribute's data.
 * 
 * If the data is of binary data type, everything should work automatically, but
 * if it's a different data type, you should use `value_type` to tell the widget,
 * how the binary data is encoded: `base64`, `hex` or plain `binary`.
 * 
 * You can also specify a `filename_attribute_alias` to display the PDFs filename.
 * 
 * By default, the user will be able to download the displayed PDF. To avoid this,
 * set `download_enabled` to `false`. The downloaded file will be named via the
 * `filename_attribute_alias` if defined or will have the attribute's name as
 * filename.
 *
 * @author Andrej Kabachnik
 *        
 */
class PDFViewer extends Display implements iFillEntireContainer
{
    private $valueType = null;
    
    private $downloadEnabled = true;
    
    private $fileNameAttributeAlias = null;
    
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
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iFillEntireContainer::getAlternativeContainerForOrphanedSiblings()
     */
    public function getAlternativeContainerForOrphanedSiblings(): ?iContainOtherWidgets
    {
        return null;
    }
    
    /**
     * Force treating value as binary, base64 or hex
     * 
     * @uxon-property value_type
     * @uxon-type [hex,binary,base64]
     * 
     * @param string $type
     * @throws WidgetConfigurationError
     * @return PDFViewer
     */
    public function setValueType(string $type) : PDFViewer
    {
        switch (mb_strtoupper($type)) {
            case strtoupper(BinaryDataType::ENCODING_HEX):
                $this->valueType = BinaryDataType::ENCODING_HEX;
                break;
            case strtoupper(BinaryDataType::ENCODING_BINARY):
                $this->valueType = BinaryDataType::ENCODING_BINARY;
                break;
            case strtoupper(BinaryDataType::ENCODING_BASE64):
                $this->valueType = BinaryDataType::ENCODING_BASE64;
                break;
            default:
                throw new WidgetConfigurationError('Invalid PDF value type "' . $type . '" specified: expecting "Hex", "Binary" or "Base64"!');
        }
        return $this; 
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getValueType() : ?string
    {
        if ($this->valueType === null && $this->isBoundToAttribute()) {
            $dt = $this->getAttribute()->getDataType();
            if ($dt instanceof BinaryDataType) {
                return $dt->getEncoding();
            }
        }
        return $this->valueType;
    }
    
    /**
     * 
     * @return bool
     */
    public function getDownloadEnabled() : bool
    {
        return $this->downloadEnabled;
    }
    
    /**
     * Set to FALSE to make it impossible to download the original PDF from the viewer.
     * 
     * @uxon-property download_enabled
     * @uxon-type boolean
     * @uxon-default true
     * 
     * @param bool $trueOrFalse
     * @return PDFViewer
     */
    public function setDownloadEnabled(bool $trueOrFalse) : PDFViewer
    {
        $this->downloadEnabled = $trueOrFalse;
        return $this;
    }
    
    /**
     * Alias of the attribute to be used as the filename if downloading the PDF
     * 
     * @uxon-property filename_attribute_alias
     * @uxon-type metamodel:attribute
     * 
     * @param string $alias
     * @return PDFViewer
     */
    public function setFilenameAttributeAlias(string $alias) : PDFViewer
    {
        $this->fileNameAttributeAlias = $alias;
        return $this;
    }
    
    /**
     * 
     * @return bool
     */
    public function isFilenameBoundToAttribute() : bool
    {
        return $this->fileNameAttributeAlias !== null;
    }
    
    /**
     * 
     * @return MetaAttributeInterface
     */
    public function getFilenameAttribute() : ?MetaAttributeInterface
    {
        return $this->getMetaObject()->getAttribute($this->getFilenameAttributeAlias());
    }
    
    public function getFilenameAttributeAlias() : ?string
    {
        return $this->fileNameAttributeAlias;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Widgets\Value::prepareDataSheetToRead()
     */
    public function prepareDataSheetToRead(DataSheetInterface $data_sheet = null)
    {
        $data_sheet = parent::prepareDataSheetToRead($data_sheet);
        if ($this->isFilenameBoundToAttribute() === true) {
            $filenamePrefillExpr = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getFilenameAttributeAlias());
            if ($filenamePrefillExpr !== null) {
                $data_sheet->getColumns()->addFromExpression($filenamePrefillExpr);
            }
        }
        return $data_sheet;
    }
    
   /**
    * 
    * {@inheritDoc}
    * @see \exface\Core\Widgets\Value::doPrefill()
    */
    protected function doPrefill(DataSheetInterface $data_sheet)
    {
        parent::doPrefill($data_sheet);
        if ($this->isFilenameBoundToAttribute() === true) {
            $filenamePrefillExpression = $this->getPrefillExpression($data_sheet, $this->getMetaObject(), $this->getFilenameAttributeAlias());
            if ($filenamePrefillExpression !== null && $col = $data_sheet->getColumns()->getByExpression($filenamePrefillExpression)) {
                if (count($col->getValues(false)) > 1 && $this->getAggregator()) {
                    // TODO #OnPrefillChangeProperty
                    $valuePointer = DataPointerFactory::createFromColumn($col);
                    $value = $col->aggregate($this->getAggregator());
                } else {
                    $valuePointer = DataPointerFactory::createFromColumn($col, 0);
                    $value = $valuePointer->getValue();
                }
                // Ignore empty values because if value is a live-references as the ref would get overwritten
                // even without a meaningfull prefill value
                if ($this->isBoundByReference() === false || ($value !== null && $value != '')) {
                    $this->setValue($value, false);
                    $this->dispatchEvent(new OnPrefillChangePropertyEvent($this, 'filename', $valuePointer));
                }
            }
        }
        return;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Widgets\iShowDataColumn::getDataColumnName()
     */
    public function getFilenameDataColumnName()
    {
        return $this->isFilenameBoundToAttribute() ? DataColumn::sanitizeColumnName($this->getFilenameAttributeAlias()) : $this->getDataColumnName();
    }
}