<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\Selectors\ActionSelector;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Interfaces\Exceptions\NotFoundExceptionInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;

/**
 * Verifies the exception contract for missing app components.
 */
class AppComponentLookupTest extends TestCase
{
    /**
     * A missing component can be caught through both Core and PSR container interfaces.
     */
    public function testMissingComponentUsesBothNotFoundContracts() : void
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'app-component-lookup-test']);
        $app = new App(new AppSelector($workbench, 'exface.Core'));
        $selector = new ActionSelector($workbench, 'exface.Core.MissingComponentLookupTestAction');

        try {
            $app->get($selector);
            $this->fail('Expected a missing component exception');
        } catch (NotFoundExceptionInterface $e) {
            $this->assertInstanceOf(PsrNotFoundExceptionInterface::class, $e);
        }
    }
}
