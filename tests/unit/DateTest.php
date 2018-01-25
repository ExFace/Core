<?php
use exface\Core\CommonLogic\Workbench;
use exface\Core\Formulas\Date;

class DateTest extends \Codeception\Test\Unit
{

    protected $tester;

    private $date;

    const TEST_DATE = '2018-01-11';

    const TEST_FORMAT = 'd.m.Y';

    protected function _before()
    {
        $exface = new Workbench();
        $exface->start();
        $this->date = new Date($exface);
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

    public function testFormatDateFromInvalidFormat()
    {
        $formattedDate = $this->date->formatDate(self::TEST_DATE, 'cy.we.j');
        $this->assertEquals('11.01.2018', $formattedDate);
    }

    public function testFormatDateFromEmptyDate()
    {
        $formattedDate = $this->date->formatDate('', self::TEST_FORMAT);
        $this->assertEquals('11.01.2018', $formattedDate);
    }

    public function testFormatDateFromInvalidDate()
    {
        $formattedDate = $this->date->formatDate(null, self::TEST_FORMAT);
        $this->assertEquals('11.01.2018', $formattedDate);
    }

    public function testRun()
    {
        $formattedDate = $this->date->run(self::TEST_DATE, self::TEST_FORMAT);
        $this->assertEquals('11.01.2018', $formattedDate);
    }
}