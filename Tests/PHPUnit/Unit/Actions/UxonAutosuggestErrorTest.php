<?php
namespace exface\Core\Tests\PHPUnit\Unit\Actions;

use exface\Core\Actions\UxonAutosuggest;
use exface\Core\CommonLogic\DataManager;
use exface\Core\CommonLogic\DataTransaction;
use exface\Core\CommonLogic\Log\Logger;
use exface\Core\CommonLogic\Model\App;
use exface\Core\CommonLogic\Selectors\AppSelector;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\CommonLogic\Tasks\ResultJSON;
use exface\Core\CommonLogic\Workbench;
use exface\Core\Exceptions\UxonSyntaxError;
use exface\Core\Interfaces\DataSources\DataTransactionInterface;
use exface\Core\Interfaces\Log\LogEntryInterface;
use exface\Core\Interfaces\Log\LogHandlerInterface;
use exface\Core\Interfaces\Tasks\ResultInterface;
use exface\Core\Interfaces\Tasks\TaskInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Covers the completion error response using real tasks and an unstarted Workbench.
 */
class UxonAutosuggestErrorTest extends TestCase
{
    /**
     * Malformed UXON is visible as one single-line completion and a full log entry.
     */
    public function testMalformedUxonReturnsLoggedDiagnosticForFieldsAndValues() : void
    {
        $workbench = $this->createWorkbench();
        $logger = $workbench->getLogger();
        $handler = new class implements LogHandlerInterface {
            public array $entries = [];

            /**
             * {@inheritDoc}
             * @see LogHandlerInterface::handle()
             */
            public function handle(LogEntryInterface $entry) : void
            {
                $this->entries[] = $entry;
            }
        };
        $logger->appendHandler($handler);
        $action = $this->createAction($workbench);

        foreach (['field', 'value'] as $type) {
            $task = $this->createTask($workbench, $type);
            $task->setParameter('uxon', "{\n invalid");
            $result = $action->perform($task, new DataTransaction(new DataManager($workbench)));
            $this->assertInstanceOf(ResultJSON::class, $result);
            $data = json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertCount(1, $data['values']);
            $entry = $handler->entries[count($handler->entries) - 1];
            $this->assertInstanceOf(UxonSyntaxError::class, $entry->getException());
            $this->assertStringStartsWith('// ERROR: ', $data['values'][0]);
            $this->assertStringContainsString('See Log-ID ' . $entry->getException()->getId(), $data['values'][0]);
            $this->assertStringNotContainsString("\n", $data['values'][0]);
            if ($type === 'field') {
                $this->assertSame([], $data['templates']);
            }
        }
        $this->assertCount(2, $handler->entries);
    }

    /**
     * A backend failure is returned as a diagnostic instead of an empty successful list.
     */
    public function testModelFailureReturnsDiagnostic() : void
    {
        $workbench = $this->createWorkbench();
        $task = $this->createTask($workbench, 'value');
        $task->setParameter('uxon', '{"alias":"0x35370000000000000000000000000000"}');
        $task->setParameter('schema', 'datatype');
        $result = $this->createAction($workbench)->perform($task, new DataTransaction(new DataManager($workbench)));
        $this->assertInstanceOf(ResultJSON::class, $result);
        $data = json_decode($result->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['values']);
        $this->assertStringStartsWith('// ERROR: Meta model not initialized', $data['values'][0]);
        $this->assertStringContainsString('See Log-ID ', $data['values'][0]);
    }

    /**
     * Non-completion operations must not receive the incompatible values response shape.
     */
    public function testModelBrowserErrorsStillPropagate() : void
    {
        $workbench = $this->createWorkbench();
        $task = $this->createTask($workbench, 'modelbrowser');
        $task->setParameter('uxon', '{invalid');
        $this->expectException(UxonSyntaxError::class);
        $this->createAction($workbench)->perform($task, new DataTransaction(new DataManager($workbench)));
    }

    /**
     * Exposes only the action's result boundary, not its authorization lifecycle.
     *
     * @param Workbench $workbench
     * @return UxonAutosuggest
     */
    private function createAction(Workbench $workbench) : UxonAutosuggest
    {
        return new class(new App(new AppSelector($workbench, 'exface.Core'))) extends UxonAutosuggest {
            /**
             * {@inheritDoc}
             * @see UxonAutosuggest::perform()
             */
            public function perform(TaskInterface $task, DataTransactionInterface $transaction) : ResultInterface
            {
                return parent::perform($task, $transaction);
            }
        };
    }

    /**
     * Creates valid request parameters before each scenario overrides its input.
     *
     * @param Workbench $workbench
     * @param string $type
     * @return GenericTask
     */
    private function createTask(Workbench $workbench, string $type) : GenericTask
    {
        $task = new GenericTask($workbench);
        foreach ([
            'input' => $type,
            'text' => '',
            'path' => '["alias"]',
            'uxon' => '{}',
            'schema' => '',
            'prototype' => ''
        ] as $key => $value) {
            $task->setParameter($key, $value);
        }
        return $task;
    }

    /**
     * Uses a real Workbench and an isolated logger without filesystem handlers.
     *
     * @return Workbench
     */
    private function createWorkbench() : Workbench
    {
        $workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'uxon-autosuggest-error-test']);
        $logger = new ReflectionProperty(Workbench::class, 'logger');
        $logger->setAccessible(true);
        $logger->setValue($workbench, new Logger());
        return $workbench;
    }
}
