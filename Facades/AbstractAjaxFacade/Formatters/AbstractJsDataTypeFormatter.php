<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\DataTypes\DataTypeInterface;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;

abstract class AbstractJsDataTypeFormatter implements JsDataTypeFormatterInterface, WorkbenchDependantInterface
{
    /**
     * 
     * @var DataTypeInterface
     */
    private $dataType = null;
    
    /**
     * 
     * @param DataTypeInterface $dataType
     */
    public function __construct(DataTypeInterface $dataType)
    {
        $this->setDataType($dataType);
    }
    
    /**
     * Sets the data type for this formatter. 
     * 
     * Override this method to include additional checks for specific compatible data types.
     * 
     * @param DataTypeInterface $dataType
     * @return \exface\Core\Facades\AbstractAjaxFacade\Formatters\AbstractJsDataTypeFormatter
     */
    protected function setDataType(DataTypeInterface $dataType)
    {
        $this->dataType = $dataType;
        return $this;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface::getDataType()
     */
    public function getDataType()
    {
        return $this->dataType;
    }
    
    public function getWorkbench()
    {
        return $this->getDataType()->getWorkbench();
    }

    /**
     * @inerhitDoc 
     * @see JsDataTypeFormatterInterface::getJsEmptyText()
     */
    public function getJsEmptyText(string $jsFallback = '', bool $encode = true) : ?string
    {
        $result = $this->getDataType()->getEmptyText(); 
        
        if($result === null) {
            return $jsFallback;
        }
        
        return $encode ? json_encode($result) : $result;
    }

    /**
     * Returns an inline snippet that checks whether a variable with
     * name `$jsVar` is empty.
     * 
     * ```
     * ({$jsVar} === null || {$jsVar} === undefined || {$jsVar} === '')
     * ```
     * 
     * @param string $jsVar
     * @return string
     */
    protected function getJsEmptyCheck(string $jsVar) : string 
    {
        return "({$jsVar} === null || {$jsVar} === undefined || {$jsVar} === '')";
    }

    /**
     * @inheritDoc
     */
    public function buildJsGetValidatorIssues(string $jsValue): string
    {
        $dataType = $this->getDataType();
        if(null !== $message = $dataType->getValidationErrorMessage()) {
            $msg = StringDataType::endSentence($message->getTitle());
        } else {
            $msg = '';
        }
        
        return <<<JS

(function (value){
    if ({$this->buildJsValidator('value')}) {
        return {$msg};
    } else {
        return '';
    }
})({$jsValue})
JS;
    }
}
