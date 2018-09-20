<?php
use exface\Core\Formulas\Date;
use exface\Core\CommonLogic\Selectors\FormulaSelector;

class DateTest extends \Codeception\Test\Unit
{

    private $date;

    const TEST_DATE = '2018-01-11';

    const TEST_FORMAT = 'd.m.Y';

    protected function _before()
    {
        global $exface;
        $this->date = new Date(new FormulaSelector($exface, 'exface.Core.Date'));
    }

    protected function _after()
    {
        $this->date = null;
    }

    public function testFormatDate()
    {
        $formattedDate = $this->date->formatDate(self::TEST_DATE, self::TEST_FORMAT);
        $this->assertEquals('11.01.2018', $formattedDate);
    }

    public function testFormatDateFromEmptyFormat()
    {
        $formattedDate = $this->date->formatDate(self::TEST_DATE, '');
        $this->assertEquals('11.01.2018', $formattedDate);
        
        $formattedDate = $this->date->formatDate(self::TEST_DATE, null);
        $this->assertEquals('11.01.2018', $formattedDate);
    }

    public function testFormatDateFromEmptyDate()
    {
        $formattedDate = $this->date->formatDate('', self::TEST_FORMAT);
        $this->assertEquals((new DateTime())->format(self::TEST_FORMAT), $formattedDate);
        
        $formattedDate = $this->date->formatDate(null, self::TEST_FORMAT);
        $this->assertEquals((new DateTime())->format(self::TEST_FORMAT), $formattedDate);
    }

    public function testFormatDateFromInvalidDate()
    {
        $formattedDate = $this->date->formatDate('2018-13-12', self::TEST_FORMAT);
        $this->assertEquals('2018-13-12', $formattedDate);
    }

    public function testRun()
    {
        $formattedDate = $this->date->run(self::TEST_DATE, self::TEST_FORMAT);
        $this->assertEquals('11.01.2018', $formattedDate);
    }
}