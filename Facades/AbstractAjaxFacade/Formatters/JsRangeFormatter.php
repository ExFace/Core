<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Formatters;

use exface\Core\DataTypes\ComparatorDataType;
use exface\Core\DataTypes\DateDataType;
use exface\Core\DataTypes\RangeDataType;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsDataTypeFormatterInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;

/** Formats and parses closed scalar ranges using the formatter for each bound. */
class JsRangeFormatter extends AbstractJsDataTypeFormatter
{
    public function __construct(RangeDataType $dataType, private JsDataTypeFormatterInterface $endpointFormatter)
    {
        parent::__construct($dataType);
    }

    public function buildJsFormatter($jsInput)
    {
        return $this->buildJsMap($jsInput, $this->endpointFormatter->buildJsFormatter('bound'), ' ' . ComparatorDataType::BETWEEN . ' ', 'value');
    }

    public function buildJsFormatParser($jsInput)
    {
        $endpointType = $this->endpointFormatter->getDataType();
        if ($this->endpointFormatter instanceof JsDateFormatter && $endpointType instanceof DateDataType) {
            $format = json_encode($this->endpointFormatter->getFormat(), JSON_THROW_ON_ERROR);
            $internalFormat = json_encode($endpointType->getFormatToParseTo(), JSON_THROW_ON_ERROR);
            $parse = "exfTools.date.formatRangeBound(exfTools.date.parseRangeBound(bound, {$format}), {$internalFormat})";
        } else {
            $parse = $this->endpointFormatter->buildJsFormatParser('bound');
        }
        return $this->buildJsMap($jsInput, $parse, ComparatorDataType::BETWEEN, 'null');
    }

    public function buildJsFilterParser(string $jsValue, string $jsComparator) : string
    {
        $parse = $this->buildJsFormatParser('extracted.value');
        return <<<JS
((input, comparator) => {
    const extracted = exfTools.data.filterComparator.extract(input);
    if (extracted.isEmpty || extracted.isNullConstant) {
        return {comparator: extracted.comparator ?? comparator, value: extracted.value};
    }
    const value = {$parse};
    if (value === null) {
        throw new Error("Expected a range with two valid endpoints in the format from..to.");
    }
    return {comparator: extracted.comparator ?? comparator, value};
})({$jsValue}, {$jsComparator})
JS;
    }

    public function buildJsValidator(string $jsValue) : string
    {
        $validate = $this->endpointFormatter->buildJsValidator('bound');
        return <<<JS
(value => {
    if ({$this->buildJsUnsetCheck('value')}) {
        return true;
    }
    const bounds = exfTools.range.split(value);
    return bounds !== null && bounds.every(bound => ({$validate})) && {$this->buildJsFormatParser('value')} !== null;
})({$jsValue})
JS;
    }

    public function buildHtmlHeadIncludes(FacadeInterface $facade) : array
    {
        return $this->endpointFormatter->buildHtmlHeadIncludes($facade);
    }

    public function buildHtmlBodyIncludes(FacadeInterface $facade) : array
    {
        return $this->endpointFormatter->buildHtmlBodyIncludes($facade);
    }

    private function buildJsMap(string $input, string $map, string $separator, string $invalid) : string
    {
        $separatorJs = json_encode($separator, JSON_THROW_ON_ERROR);
        return <<<JS
(value => {
    if ({$this->buildJsUnsetCheck('value')}) {
        return value;
    }
    const bounds = exfTools.range.split(value);
    const mapped = bounds?.map(bound => ({$map}));
    return mapped && mapped.every(bound => bound != null && bound !== '' && !Number.isNaN(bound))
        ? mapped.join({$separatorJs})
        : {$invalid};
})({$input})
JS;
    }

    private function buildJsUnsetCheck(string $value) : string
    {
        $logicalNull = json_encode(EXF_LOGICAL_NULL, JSON_THROW_ON_ERROR);
        return "{$this->getJsEmptyCheck($value)} || (typeof {$value} === 'string' && {$value}.toUpperCase() === {$logicalNull})";
    }
}
