<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;

class BooleanDataType extends AbstractDataType
{
    /**
     * 
     * {@inheritdoc}
     * @see AbstractDataType::format()
     */
    public static function cast($string)
    {
        if ($string === null || $string === '' || strcasecmp($string, EXF_LOGICAL_NULL) === 0){
            return null;
        }
        return filter_var($string, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\DataTypes\AbstractDataType::format()
     */
    public function format($value = null, bool $silent = true) : string
    {
        try {
            $val = $this->parse($value ?? $this->getValue());
        } catch (DataTypeExceptionInterface $e) {
            $val = $value ?? $this->getValue();
            $e = $this->createFormatterError($val, $e);
            // When formatting, casting/parsing/validation errors should not break the operation
            if ($silent === true) {
                $this->getWorkbench()->getLogger()->logException($e, LoggerInterface::WARNING);
                return $val ?? '';
            } else {
                throw $e;
            }
        }
        if ($val === null || $val === EXF_LOGICAL_NULL) {
            return '';
        }
        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        return $val ? $translator->translate('WIDGET.SELECT_YES') : $translator->translate('WIDGET.SELECT_NO');
    }
}