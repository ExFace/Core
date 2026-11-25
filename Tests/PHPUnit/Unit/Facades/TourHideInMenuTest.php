<?php

namespace exface\Core\Tests\PHPUnit\Unit\Facades;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Widgets\Input;
use exface\Core\Widgets\Parts\Tours\Tour;
use PHPUnit\Framework\TestCase;

/**
 * Covers the UXON configuration for hiding tours from manual selection.
 */
class TourHideInMenuTest extends TestCase
{
    /**
     * Existing tours remain visible when the property is omitted.
     */
    public function testTourIsVisibleByDefault() : void
    {
        self::assertFalse($this->createTour([])->getHideInMenu());
    }

    /**
     * The property accepts true to hide a tour from the menu.
     */
    public function testTourCanBeHiddenThroughUxon() : void
    {
        self::assertTrue($this->createTour(['hide_in_menu' => true])->getHideInMenu());
    }

    /**
     * The property accepts an explicit false to keep a tour visible.
     */
    public function testTourCanBeExplicitlyVisibleThroughUxon() : void
    {
        self::assertFalse($this->createTour(['hide_in_menu' => false])->getHideInMenu());
    }

    /**
     * Creates a real tour with an unstarted Workbench and no persisted metamodel.
     *
     * @param array $config
     * @return Tour
     */
    private function createTour(array $config) : Tour
    {
        $workbench = new Workbench([
            'SERVER.INSTALLATION_NAME' => 'TourHideInMenuTest',
            'DEBUG.LIVE_CLASS_AUTOLOADER' => false
        ]);
        $widget = $this->getMockBuilder(Input::class)->disableOriginalConstructor()
            ->onlyMethods(['getWorkbench'])->getMock();
        $widget->method('getWorkbench')->willReturn($workbench);
        return new Tour($widget, new UxonObject($config));
    }
}
