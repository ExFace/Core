<?php
namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\WidgetLinkCoordinate;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use PHPUnit\Framework\TestCase;

/**
 * Covers coordinate-specific event registration without initializing facade services.
 */
class WidgetLinkCoordinateEventsTest extends TestCase
{
    /**
     * Explicit scopes refresh with data; only fixed numeric rows ignore selection changes.
     *
     * @return void
     */
    public function testRegistersRefreshAndSelectionUpdatesForExplicitScopes() : void
    {
        foreach ([
            ['NAME$~loaded:LIST', true, true],
            ['NAME$~selected:SUM', true, true],
            ['NAME$2', true, false],
            ['NAME', false, true]
        ] as [$expression, $refresh, $selection]) {
            $element = $this->getMockBuilder(AbstractJqueryElement::class)
                ->disableOriginalConstructor()
                ->onlyMethods([
                    'addOnChangeScript', 'addOnRefreshScript',
                    'buildJs', 'buildHtml', 'buildJsBusyIconShow', 'buildJsBusyIconHide'
                ])
                ->getMock();
            $coordinate = new WidgetLinkCoordinate($expression);
            $element->expects($refresh ? $this->once() : $this->never())
                ->method('addOnRefreshScript')->with('updateValue();')->willReturnSelf();
            $element->expects($selection ? $this->once() : $this->never())
                ->method('addOnChangeScript')->with('updateValue();')->willReturnSelf();
            $element->addOnChangeAtCoordinateScript('updateValue();', $coordinate);
        }
    }
}