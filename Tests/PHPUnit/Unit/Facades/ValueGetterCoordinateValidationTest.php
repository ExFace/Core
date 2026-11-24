<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\Facades\FacadeOutputError;
use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Factories\UiPageFactory;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\Widgets\Input;
use exface\Core\Tests\PHPUnit\Support\ScalarElementWithDataHelpers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Covers scalar and shared data validation without starting facade services.
 */
class ValueGetterCoordinateValidationTest extends TestCase
{
    /**
     * Sharing data helpers does not grant row support to an inherited scalar getter.
     *
     * @param string $coordinate
     * @return void
     */
    #[DataProvider('scalarRowCoordinates')]
    public function testScalarGetterRejectsRowsEvenWithDataHelpers(string $coordinate) : void
    {
        $this->expectException(FacadeRuntimeError::class);
        $this->element()->buildJsValueGetter($coordinate);
    }

    /**
     * Supplies row coordinates unsupported by a scalar getter.
     *
     * @return array
     */
    public static function scalarRowCoordinates() : array
    {
        return [['NAME$2'], ['NAME$~selected'], ['NAME$~loaded:SUM']];
    }

    /**
     * Standard data validation accepts row numbers, scopes and aggregators.
     *
     * @return void
     */
    public function testDataValidationAcceptsStandardCoordinates() : void
    {
        $this->expectNotToPerformAssertions();
        $element = $this->element();
        $validator = new ReflectionMethod($element, 'validateDataValueGetterCoordinate');
        foreach (['NAME', 'NAME$2', 'NAME$~loaded:SUM', 'NAME$~selected:LIST'] as $coordinate) {
            $validator->invoke($element, WidgetLinkFactory::createCoordinateFromAnything($coordinate));
        }
    }

    /**
     * Custom selectors require an element-specific validation policy.
     *
     * @param string $coordinate
     * @return void
     */
    #[DataProvider('unsupportedDataCoordinates')]
    public function testDataValidationRejectsUnsupportedCoordinates(string $coordinate) : void
    {
        $element = $this->element();
        $validator = new ReflectionMethod($element, 'validateDataValueGetterCoordinate');
        $this->expectException(FacadeOutputError::class);
        $validator->invoke($element, WidgetLinkFactory::createCoordinateFromAnything($coordinate));
    }

    /**
     * Supplies coordinates outside the standard column/row system.
     *
     * @return array
     */
    public static function unsupportedDataCoordinates() : array
    {
        return [['NAME$~unknown'], ['~start_date']];
    }

    /**
     * Uses a real input and Workbench while excluding the rendering lifecycle.
     *
     * @return AbstractJqueryElement
     */
    private function element() : AbstractJqueryElement
    {
        $input = new Input(UiPageFactory::createEmpty(new Workbench()));
        $element = $this->getMockBuilder(ScalarElementWithDataHelpers::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWidget', 'buildJs', 'buildHtml', 'buildJsBusyIconShow', 'buildJsBusyIconHide'])
            ->getMock();
        $element->method('getWidget')->willReturn($input);
        return $element;
    }
}

