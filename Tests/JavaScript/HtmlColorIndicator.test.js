// Run: node Tests/JavaScript/HtmlColorIndicator.test.js (from Core).
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {readFileSync} = require('node:fs');
const path = require('node:path');

const generated = JSON.parse(execFileSync('php', ['-r', String.raw`
require dirname(getcwd(), 2) . '/autoload.php';

$element = new class {
    use \exface\Core\Facades\AbstractAjaxFacade\Elements\HtmlColorIndicatorTrait;

    public $scale = [];
    public $range = false;
    public $bindingColumn = null;
    public $bindingValue = null;
    public $colorOnly = false;

    public function getWidget() { return $this; }
    public function getFacade() { return $this; }
    public function getValueDataType() { return null; }
    public function getDataTypeFormatter($type) { return $this; }
    public function getJsEmptyText($fallback) { return 'null'; }
    public function hasColorScale() { return count($this->scale) > 0; }
    public function getColorScale() { return $this->scale; }
    public function isColorScaleRangeBased() { return $this->range; }
    public function getFill() { return true; }
    public function getColorOnly() { return $this->colorOnly; }
    public function getDataColumnName() { return 'value'; }
    public function getColorBinding()
    {
        return new class($this->bindingColumn, $this->bindingValue) {
            private $column;
            private $value;
            public function __construct($column, $value) { $this->column = $column; $this->value = $value; }
            public function isBoundToDataColumn() { return $this->column !== null; }
            public function getDataColumnName() { return $this->column; }
            public function hasValue() { return $this->value !== null; }
            public function getValue() { return $this->value; }
        };
    }

    protected function buildHtmlIndicator($value = null, string $text = null, string $hint = null, string $color = null) : string
    {
        return '<div style="background-color: ' . $color . '">' . ($this->colorOnly ? '&nbsp;' : $text) . '</div>';
    }

    protected function buildJsValueFormatter($value)
    {
        return $value;
    }
};

$cases = ['fallback' => $element->buildJsValueDecorator('value')];
$element->scale = ['open' => 'red', 'done' => 'green', '' => 'gray'];
$cases['discrete'] = $element->buildJsValueDecorator('value');
$element->scale = [10 => 'red', 100 => 'green'];
$element->range = true;
$cases['range'] = $element->buildJsValueDecorator('value');
$element->scale = [];
$element->colorOnly = true;
$element->bindingColumn = 'value';
$cases['colorOnly'] = $element->buildJsValueDecorator('value');
$element->colorOnly = false;
$element->bindingColumn = 'color';
$cases['rowColor'] = $element->buildJsValueDecorator('value', 'row');
$element->scale = [10 => 'red', 100 => 'green'];
$cases['rowScale'] = $element->buildJsValueDecorator('value', 'row');
$element->scale = [];
$element->bindingColumn = null;
$element->bindingValue = 'gold';
$cases['constantColor'] = $element->buildJsValueDecorator('value');
echo json_encode($cases, JSON_THROW_ON_ERROR);
`], {cwd: path.resolve(__dirname, '..', '..'), encoding: 'utf8'}));

function decorator(name) {
    const render = new Function('value', 'row', 'exfColorTools', '"use strict"; return ' + generated[name] + ';');
    return function(value, row) {
        // Actual contrast and rendered styles are covered by the browser color-indicator fixture.
        return render(value, row, {pickTextColorForBackgroundColor: function() { return '#000'; }});
    };
}

const fallback = decorator('fallback');
for (let row = 0; row < 80; row++) {
    assert.equal(fallback('Row ' + row), '<div style="background-color: transparent">Row ' + row + '</div>');
}
assert.equal(fallback(null), '');
assert.equal(fallback(undefined), '');
assert.equal(fallback(''), '<div style="background-color: transparent">&nbsp;</div>');
assert.equal(fallback(0), '<div style="background-color: transparent">0</div>');

const discrete = decorator('discrete');
assert.equal(discrete('OPEN'), '<div style="background-color: red">OPEN</div>');
assert.equal(discrete('done'), '<div style="background-color: green">done</div>');
assert.equal(discrete('unknown'), '<div style="background-color: gray">unknown</div>');

const range = decorator('range');
assert.equal(range(5), '<div style="background-color: red">5</div>');
assert.equal(range(50), '<div style="background-color: green">50</div>');
assert.equal(range(150), '<div style="background-color: green">150</div>');

const colorOnly = decorator('colorOnly');
assert.equal(colorOnly('#a85a00'), '<div style="background-color: #a85a00">&nbsp;</div>');
assert.equal(colorOnly('steelblue'), '<div style="background-color: steelblue">&nbsp;</div>');
assert.equal(colorOnly(''), '<div style="background-color: transparent">&nbsp;</div>');
assert.equal(colorOnly(null), '');
const rowColor = decorator('rowColor');
assert.equal(rowColor('Archaeology', {color: '#a85a00'}), '<div style="background-color: #a85a00">Archaeology</div>');
assert.equal(rowColor('Another row', {color: 'steelblue'}), '<div style="background-color: steelblue">Another row</div>');
assert.equal(rowColor('No color', {color: null}), '<div style="background-color: transparent">No color</div>');
assert.equal(rowColor('Missing color', {}), '<div style="background-color: transparent">Missing color</div>');
assert.equal(rowColor('No row'), '<div style="background-color: transparent">No row</div>');
assert.equal(decorator('rowScale')('Task', {color: 5}), '<div style="background-color: red">Task</div>');
assert.equal(decorator('rowScale')('Task', {color: 50}), '<div style="background-color: green">Task</div>');
assert.equal(decorator('constantColor')('Static color'), '<div style="background-color: gold">Static color</div>');
const transposerSource = readFileSync(path.resolve(__dirname, '..', '..',
    'Facades', 'AbstractAjaxFacade', 'Elements', 'JqueryDataTransposerTrait.php'), 'utf8');
const formatterCall = transposerSource.match(/return oColOrig\.fnFormatter \? [^\r\n]+/);
assert.ok(formatterCall, 'The transposer formatter call is present');
const transposeCell = new Function('oColOrig', 'newColVals', 'fld', 'oRow', formatterCall[0]);
assert.equal(transposeCell({fnFormatter: rowColor}, {name: 'Archaeology'}, 'name', {color: '#a85a00'}),
    '<div style="background-color: #a85a00">Archaeology</div>');
assert.equal(transposeCell({fnFormatter: null}, {name: 'Unformatted'}, 'name', {}),
    'Unformatted');
assert.equal(Object.prototype.hasOwnProperty.call(globalThis, 'sColor'), false);

console.log('PASS: color indicator labels, color-only values, row bindings, constants and scales');
