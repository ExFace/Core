<?php
namespace exface\Core\Tests\PHPUnit\Unit\Formulas;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\FormulaError;
use exface\Core\Factories\FormulaFactory;
use PHPUnit\Framework\TestCase;

/**
 * Covers duration formatting through the public formula evaluator.
 */
class FormatMsTest extends TestCase
{
    /**
     * Switches units only above the shared formatter's 1000 ms boundary.
     * 
     * @return void
     */
    public function testAutomaticUnitsAndRounding() : void
    {
        $workbench = new Workbench();
        $cases = [
            '=FormatMs(0)' => '0 ms',
            '=FormatMs(125.56)' => '125.6 ms',
            '=FormatMs(1000)' => '1000 ms',
            '=FormatMs(1001)' => '1 s',
            '=FormatMs(1250)' => '1.3 s'
        ];

        foreach ($cases as $expression => $expected) {
            $this->assertSame($expected, FormulaFactory::createFromString($workbench, $expression)->evaluate());
        }
    }

    /**
     * Accepts numeric strings and an explicit rounding precision.
     * 
     * @return void
     */
    public function testNumericStringAndCustomPrecision() : void
    {
        $formula = FormulaFactory::createFromString(new Workbench(), '=FormatMs("1250", 2)');

        $this->assertSame('1.25 s', $formula->evaluate());
    }

    /**
     * Leaves missing values empty rather than formatting them as zero.
     * 
     * @return void
     */
    public function testEmptyValues() : void
    {
        $workbench = new Workbench();

        foreach (['=FormatMs()', '=FormatMs(null)', '=FormatMs("")'] as $expression) {
            $this->assertSame('', FormulaFactory::createFromString($workbench, $expression)->evaluate());
        }
    }

    /**
     * Rejects invalid values with a formula-specific error.
     * 
     * @return void
     */
    public function testInvalidValue() : void
    {
        $formula = FormulaFactory::createFromString(new Workbench(), '=FormatMs("invalid")');

        $this->expectException(FormulaError::class);
        $this->expectExceptionMessage('FormatMs() expects a finite number of milliseconds.');
        $formula->evaluate();
    }

    /**
     * Rejects non-finite durations instead of returning a misleading display value.
     * 
     * @return void
     */
    public function testNonFiniteValue() : void
    {
        $formula = FormulaFactory::createFromString(new Workbench(), '=FormatMs()');

        $this->expectException(FormulaError::class);
        $formula->run(INF);
    }
}
