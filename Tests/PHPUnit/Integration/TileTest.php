<?php
namespace exface\Core\Tests\PHPUnit\Integration;

use exface\Core\Actions\GoToPage;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Interfaces\Log\LoggerInterface;
use exface\Core\Interfaces\Model\UiPageInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Widgets\Tile;
use PHPUnit\Framework\TestCase;

class TileTest extends TestCase
{
    public function testNavigationTileUsesPageNameAndDescription(): void
    {
        $tile = $this->createNavigationTile();

        $this->assertSame('Orders', $tile->getCaption());
        $this->assertSame('Orders', $tile->getTitle());
        $this->assertSame('Manage orders', $tile->getSubtitle());
    }

    public function testExplicitTitleAndSubtitleOverridePageDefaults(): void
    {
        $tile = $this->createNavigationTile();
        $tile->setTitle('Pending orders');
        $tile->setSubtitle('To approve');

        $this->assertSame('Pending orders', $tile->getCaption());
        $this->assertSame('Pending orders', $tile->getTitle());
        $this->assertSame('To approve', $tile->getSubtitle());
    }

    public function testCaptionAndSubtitleCanBeOverriddenIndependently(): void
    {
        $tile = $this->createNavigationTile();
        $tile->setCaption('Custom caption');

        $this->assertSame('Custom caption', $tile->getTitle());
        $this->assertSame('Manage orders', $tile->getSubtitle());

        $tile->setCaption(null);
        $tile->setSubtitle('Custom subtitle');

        $this->assertSame('Orders', $tile->getTitle());
        $this->assertSame('Custom subtitle', $tile->getSubtitle());
    }

    public function testExplicitEmptyLabelsArePreserved(): void
    {
        $tile = $this->createNavigationTile();
        $tile->setCaption('');
        $tile->setSubtitle('');

        $this->assertSame('', $tile->getCaption());
        $this->assertSame('', $tile->getTitle());
        $this->assertSame('', $tile->getSubtitle());
    }

    public function testNavigationTileWithoutDescriptionDoesNotUseActionHint(): void
    {
        $tile = $this->createNavigationTile(null);

        $this->assertSame('Orders', $tile->getTitle());
        $this->assertNull($tile->getSubtitle());
    }

    public function testOtherActionsStillUseActionNameAndHint(): void
    {
        $action = $this->createMock(ActionInterface::class);
        $action->method('isDefinedInWidget')->willReturn(true);
        $action->method('getName')->willReturn('Run action');
        $action->method('getHint')->willReturn('Action hint');
        $tile = $this->createTile();
        $tile->setAction($action);

        $this->assertSame('Run action', $tile->getCaption());
        $this->assertSame('Run action', $tile->getTitle());
        $this->assertSame('Action hint', $tile->getSubtitle());
    }

    public function testTileWithoutActionKeepsExistingDefaults(): void
    {
        $tile = $this->createTile();

        $this->assertNull($tile->getTitle());
        $this->assertNull($tile->getSubtitle());

        $tile->setTitle('Static tile');
        $this->assertSame('Static tile', $tile->getTitle());
        $this->assertNull($tile->getSubtitle());

        $tile->setHint('Static hint');
        $this->assertSame('Static hint', $tile->getSubtitle());
    }

    public function testPageLookupErrorsReturnErrorCaptionAndEmptySubtitle(): void
    {
        $error = new \RuntimeException('Page not found');
        $action = $this->getMockBuilder(GoToPage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPage', 'isDefinedInWidget'])
            ->getMock();
        $action->method('getPage')->willThrowException($error);
        $action->method('isDefinedInWidget')->willReturn(true);
        $tile = $this->createTile();
        $tile->setAction($action);
        $this->expectLoggedLabelErrors($tile, $error, 3);

        $this->assertSame('Error', $tile->getCaption());
        $this->assertSame('Error', $tile->getTitle());
        $this->assertNull($tile->getSubtitle());
    }

    public function testActionLookupErrorsReturnErrorCaptionAndEmptySubtitle(): void
    {
        $error = new \Error('Action could not be created');
        $tile = $this->getMockBuilder(Tile::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAction', 'getWorkbench', 'translate'])
            ->getMock();
        $tile->method('getAction')->willThrowException($error);
        $this->expectLoggedLabelErrors($tile, $error, 2);

        $this->assertSame('Error', $tile->getCaption());
        $this->assertNull($tile->getSubtitle());

        $tile->setTitle('Explicit title');
        $tile->setSubtitle('Explicit subtitle');
        $this->assertSame('Explicit title', $tile->getTitle());
        $this->assertSame('Explicit subtitle', $tile->getSubtitle());
    }

    public function testPageMetadataErrorsAreLoggedIndependently(): void
    {
        $error = new \RuntimeException('Description unavailable');
        $page = $this->createMock(UiPageInterface::class);
        $page->method('getName')->willReturn('Orders');
        $page->method('getDescription')->willThrowException($error);
        $tile = $this->createNavigationTile('Manage orders', $page);
        $this->expectLoggedLabelErrors($tile, $error, 1);

        $this->assertSame('Orders', $tile->getTitle());
        $this->assertNull($tile->getSubtitle());
    }

    private function expectLoggedLabelErrors(Tile $tile, \Throwable $error, int $count): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly($count))->method('logException')->with($error);
        $workbench = $this->createMock(WorkbenchInterface::class);
        $workbench->method('getLogger')->willReturn($logger);
        $tile->method('getWorkbench')->willReturn($workbench);
        $tile->method('translate')->with('ERROR.CAPTION')->willReturn('Error');
    }

    private function createNavigationTile(?string $description = 'Manage orders', ?UiPageInterface $page = null): Tile
    {
        if ($page === null) {
            $page = $this->createMock(UiPageInterface::class);
            $page->method('getName')->willReturn('Orders');
            $page->method('getDescription')->willReturn($description);
        }
        $action = $this->getMockBuilder(GoToPage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPage', 'getName', 'getHint', 'isDefinedInWidget'])
            ->getMock();
        $action->method('getPage')->willReturn($page);
        $action->method('getName')->willReturn('Go to page');
        $action->method('getHint')->willReturn('Navigation hint');
        $action->method('isDefinedInWidget')->willReturn(true);
        $tile = $this->createTile();
        $tile->setAction($action);
        return $tile;
    }

    private function createTile(): Tile
    {
        $tile = $this->getMockBuilder(Tile::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isDisabled', 'getWorkbench', 'translate'])
            ->getMock();
        $tile->method('isDisabled')->willReturn(false);
        return $tile;
    }
}
