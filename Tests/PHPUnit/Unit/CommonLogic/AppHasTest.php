<?php
namespace exface\Core\Tests\PHPUnit\Unit\CommonLogic;

use exface\Core\CommonLogic\Log\Logger;
use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\Selectors\DataTypeSelector;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\DataTypes\DataTypeNotFoundError;
use exface\Core\Exceptions\LogicException;
use exface\Core\Exceptions\RuntimeException;
use exface\Core\Interfaces\Selectors\AppSelectorInterface;
use exface\Core\Interfaces\Selectors\SelectorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Throwable;

/**
 * Verifies how component existence checks handle model lookup failures.
 */
class AppHasTest extends TestCase
{
    /**
     * An incomplete data type alias is a lookup miss, not a resolution failure.
     */
    public function testMissingDataTypeReturnsFalse() : void
    {
        $app = $this->createApp(new DataTypeNotFoundError('No matching data type'));

        $this->assertFalse($app->has('exface.Core.Dat', DataTypeSelector::class));
    }

    /**
     * An unavailable model must not masquerade as an absent component.
     */
    public function testUnexpectedLookupFailureStillThrows() : void
    {
        $failure = new RuntimeException('Model lookup unavailable');
        $app = $this->createApp($failure);

        try {
            $app->has('exface.Core.Dat', DataTypeSelector::class);
            $this->fail('Expected the model lookup failure to propagate');
        } catch (LogicException $e) {
            $this->assertSame($failure, $e->getPrevious());
        }
    }

    /**
     * Supplies a model lookup failure without connecting to a database.
     */
    private function createApp(Throwable $failure) : App
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'app-has-test']);
        $logger = new ReflectionProperty(Workbench::class, 'logger');
        $logger->setValue($workbench, new Logger());

        return new class(new AppSelector($workbench, 'exface.Core'), $failure) extends App {
            /**
             * Sets the failure for the model lookup boundary.
             */
            public function __construct(AppSelectorInterface $selector, private Throwable $failure)
            {
                parent::__construct($selector);
            }

            /**
             * {@inheritDoc}
             * @see App::loadFromModel()
             */
            protected function loadFromModel(SelectorInterface $selector) : ?object
            {
                throw $this->failure;
            }
        };
    }
}
