<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\StringDataType;

/**
 * The string formatter displays NULL values as empty string and takes care of all sorts of validation
 * 
 * @author Andrej Kabachnik
 *
 */
class JsStringFormatter extends JsTransparentFormatter
{
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsTransparentFormatter::buildJsFormatter()
     */
    public function buildJsFormatter($jsInput)
    {
        return "($jsInput == null ? {$this->getJsEmptyText('""')} : $jsInput)";
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Facades\AbstractAjaxFacade\Formatters\JsTransparentFormatter::buildJsValidator()
     */
    public function buildJsValidator(string $jsValue) : string
    {
        $type = $this->getDataType();
        
        $checksOk = [];
        if ($type->getLengthMin() > 0) {
            $checksOk[] = "mVal.toString().length >= {$type->getLengthMin()} \n";
        }
        
        if ($type->getLengthMax() > 0) {
            $checksOk[] = "mVal.toString().length <= {$type->getLengthMax()} \n";
        }
        
        if ($type->getValidatorRegex() !== null) {
            $checksOk[] = "{$type->getValidatorRegex()}.test({$jsValue}) !== false \n";
        }
        $checksOkJs = ! empty($checksOk) ? implode(' && ', $checksOk) : 'true';
        
        $nullStr = '" . EXF_LOGICAL_NULL . "';
        return <<<JS
function(mVal) {
                var bEmpty = (mVal === null || mVal === undefined || mVal.toString() === '' || mVal.toString() === $nullStr);
                return (bEmpty || ($checksOkJs));
            }($jsValue)
JS;
    }

    /**
     * @inheritDoc
     * TODO: Needs to be extended to include other reasons, like conditions or length.
     */
    public function buildJsGetValidatorIssues(string $jsValue): string
    {
        $dataType = $this->getDataType();
        if(!$dataType instanceof StringDataType) {
            return parent::buildJsGetValidatorIssues($jsValue);
        }
        
        $regex = $dataType->getValidatorRegex();
        if($regex === null) {
            return parent::buildJsGetValidatorIssues($jsValue);
        }

        $translator = $this->getWorkbench()->getCoreApp()->getTranslator();
        $regexIssuePreamble = json_encode($translator->translate('DATATYPE.VALIDATION.FILENAME_INVALID_SYMBOLS'));
        
        if(null !== $message = $dataType->getValidationErrorMessage()) {
            $msg = StringDataType::endSentence($message->getTitle());
        } else {
            $msg = $translator->translate('DATATYPE.VALIDATION.FILENAME_INVALID');
        }
        $msg = json_encode($msg);
        
        // Make sure the regex is global and not sticky.
        $regex = StringDataType::removeRegexFlags($regex, ['g','y']);
        $regex .= 'g';
        
        return <<<JS

(function (sValue) {
    var sIssues = {$msg};
    
    // StringDataType::getValidatorRegex()
    var regex = {$regex}; 
    // Apply validator regex to string to extract matches.
    var matches = sValue.match(regex);

    var aRegexIssues = [];
    if (matches !== null || matches.length > 0) {
        // Extract unqiue matches.
        for (const match of matches) {
            if (aRegexIssues.indexOf(match) === -1) {
                aRegexIssues.push(match);
            }
        }
        
        sIssues += ' ' + {$regexIssuePreamble} + JSON.stringify(aRegexIssues, null, 1).slice(1,-1) + '.';
    }
    
    return sIssues;
})($jsValue)
JS;
    }
}
