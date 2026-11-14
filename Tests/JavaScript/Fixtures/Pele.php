<?php

use exface\Core\Behaviors\StateMachineBehavior;
use exface\Core\CommonLogic\Debugger\LogBooks\ActionLogBook;
use exface\Core\CommonLogic\Model\MetaObject;
use exface\Core\CommonLogic\Model\Model;
use exface\Core\CommonLogic\Selectors\BehaviorSelector;
use exface\Core\CommonLogic\Tasks\GenericTask;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Workbench;
use exface\Core\DataTypes\MarkdownDataType;
use exface\Core\Factories\ActionFactory;

require dirname(__DIR__, 2) . '/PHPUnit/bootstrap.php';

$workbench = new Workbench(['SERVER.INSTALLATION_NAME' => 'pele-browser-fixture']);
$behavior = new StateMachineBehavior(
    new BehaviorSelector($workbench, StateMachineBehavior::class),
    new MetaObject(new Model($workbench))
);
// Diagram-only behaviors have not registered any child notification behaviors.
$children = new ReflectionProperty(StateMachineBehavior::class, 'behaviors');
$children->setAccessible(true);
$children->setValue($behavior, []);
$behavior->setStates(new UxonObject([
    'Draft' => [
        'name' => 'Draft',
        'color' => '#ffeecc',
        'start_state' => true,
        'description' => 'Awaiting review',
        'transitions' => ['Done' => 'exface.Core.UpdateData']
    ],
    'Done' => ['name' => 'Done', 'start_state' => false, 'end_state' => true]
]));
$method = new ReflectionMethod($behavior, 'buildMermaidDiagram');
$method->setAccessible(true);
$stateMarkdown = $method->invoke($behavior);
$stateMarkdown = str_replace("\n```\n", "\nclassDef custom fill:#ffeecc,stroke:#ff6600,color:#552200\nclass Draft custom\n```\n", $stateMarkdown);

$action = ActionFactory::createFromString($workbench, 'exface.Core.ActionChain');
$action->setName('Diagram fixture');
$logbook = new ActionLogBook('Diagram fixture', $action, new GenericTask($workbench));
$errorStyle = $logbook->getFlowDiagramStyleError();
$chainMarkdown = "```mermaid\ngraph LR\n lbT(Task)\n lb0[UpdateData\non exface.Core.OBJECT]\n lbT -->|2 rows| lb0\n lb0 --> lbERR(Error)\n style lbERR {$errorStyle}\n style lb0 {$errorStyle}\n```";
$noteMarkdown = "```mermaid\nsequenceDiagram\n participant Agent\n participant Service\n Agent->>Service: Run\n Note over Agent,Service: Tool loop\n```\n\n```mermaid\nflowchart LR\n N@{shape: note, label: \"Flow note\"}\n```";
$markdown = $stateMarkdown . "\n" . $chainMarkdown . "\n\n" . $noteMarkdown;
echo json_encode([
    'markdown' => $markdown,
    'html' => MarkdownDataType::convertMarkdownToHtml($markdown)
], JSON_THROW_ON_ERROR);
