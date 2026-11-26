# Workflows

This file describes a new unified approach to managing workflows in the metamodel.

There are already many workflows implicitly defined in our models: `StateMachineBehavior`s, complex `ActionChain`s, data flows, and probably many more. They all work differently and have different configuration, but for many of them we use flow charts to make them better understandable for designers.

We will not add a global workflow designer widget and common interfaces, that different model components can implement to be editable via WorkflowDesigner. Thus, the editor for StateMachineBehavior will have and edit workflow button, the editor for ETL flows, the editor for Autonomous agents, etc. The models of these components will still be saved where they are now, but using a common interface will allow the WorkflowDesigner to open and edit them.

We will also add a generic workflow storage model, which will allow infrastructure app developers to add workflows to their apps in a plug-and-play manner in future. Without reinventing the wheel or having to copy one of the existing approaches.

## Workflow model

All the current implicitly included workflows will need to be made compatible with the global workflow model by implementing certain PHP interfaces and translating their current model components to the following workflow entities.

### Nodes

Nodes represent the steps of a workflow. A node can be an action, a DataFlow step, or a control-flow node like `Switch` or `ForEachDataRow`

Node properties are:
- type
- name
- description
- alias (can be used in UXON)
- icon
- color

### Edges

Edges are connections between nodes. Their properties are:
- name
- description
- line (soli, dashed, dotted)
- color

### Ports

Ports are points on the border of a node, that edges can connect to. Port propeties are:

- name
- description
- alias (used in edge config)
- direction

### Start and end nodes

It is important to show clearly, where start and end nodes of the workflow are and what they mean. Start nodes are triggers:

Start nodes define the information available at the beginning of the workflow

- `StartWithSchedulerNode` - if the workflow is triggered by a scheduled task, there may be no input data at all
- `StartWithActionNode` - when the workflow is triggered by `CallWorkflow` action we will have the input data at the beginning of it, we will also know the page and the trigger widget if the action was triggered by a button.

End nodes define, what the workflow can pass to the outside:

- `EndNode` - no output at all
- `EndWithDataNode` - the result is some data (needs to be passed into the node)

## Important entities

Important definitions:

- **Workflow implementation** = whatever represents a workflow and can supply a workflow prototype to work with the designer API. E.g. `StateMachineBehavior`, `EtlStepGroup`.
- **Workflow prototype** = a bridge between the workflow implementation and the designer API. It translates the generic workflow API into whatever the implementation can work with. E.G. `StateMachineFlow` will allow to render a workflow from the `StateMachineBehavior` and to save changes back to the behavior.
- **Workflow and edge prototypes** = PHP classes, that implement node functionality. Some of the will be wrappers around other classes - e.g. the `StateMachineStateNode` will wrap a `StateMachineState` making it compatible with workflows. There will also be stand-alone prototypes like `SwitchNode` and `ForEachDataRowRone`. And there may even be mixtures like the `ActionNode`, which will allow to call an existing action or specify a new one (like a `Button` widget does)
- **WorkflowDesigner** - a widget for viewing/editing Workflows. Needs to know the workflow prototype and have a compatible MetaObject. In contrast to regular widgets it has ist own server api (middleware), that lets us build a tailored interaction model. It must be really easy to use!

## Workflow designer

A unified workflow designer widget is very important! Workflows are hard to understand, to develop and to test. The new WorkflowDesigner shall make all of this more intuitive. App designers shall be able to edit Workflows in different components in a similar way, without bothering much about the specific implementation underneath.

The Designer widget shall allow to create, edit and even simulate a workflow easily - without having to jump between different dialogs. It shall save the workflow as a whole and not ervery part separately, like current editors often do. Simulation shall add confidence before saving.

### Visualization

The WorkflowDesigner shall be built around an interactive flow chart. Basic features like adding nodes, connecting them, etc. shall be the same for all workflow types.

### Look&Feel

The WorkflowDesigner shall be a JS library shipped with the Core, but easy to integrate with jEasyUI, UI5 and potential other facades. For the first step, only an integration in jEasyUI is required.

The UI shall be visually similar to the Markian Editor. However, the WorkflowDesigner does not need to work stand-alone without the workbench.

Global layout:
- Top toolbar
    - buttons, that can be disabled and have an optional toggled state
    - button groups visually separated
    - overflow on narrow scrrens
    - dropdown buttons
- left sidebar for the node library. Title shows the Workflow prototype.
- right sidebar with the details for the selected node or edge: description, ports/ends, list of actions/buttons. But no editing here - only in popup dialogs.
- Edit-dialogs for nodes and edges. Maybe only nodes for some prototypes.

### WorkflowDesigner PoC

There is a PoC for the WorkflowDesigner. The PoC worked well. Use it as base. However, it was only there to prototype the look&feel. It did not have the correct JSON schema or storage models.

### Toolbar

The toolbar structure shall be the same as in the Markian editor. Allow workflow prototypes to configure the toolbar like users can configure the Markian toolbar.

- Undor/redo group
    - undo
    - redo
    - save
    - reset (reload the workflow discarding all changes)
- Editing group
    - Open workflow editor
    - Open node editor
    - Open edge editor
    - Clone node - this does not save the workflow yet! After cloning the editor is opened automatically, so the user can change the new node.
    - Delete node
- Layout group
    - Auto-layout
    - Reset manual connection points
    - Alignment tools from the PoC
- Context group - right aligned
    - Export dropdown
        - Download as PNG
    - Zoom control like in PoC
    - Fit to screen (auto-zoom)
    - Maximize - makes the editor go full screen (see same feaure in Markian editor)

#### Context menu

Some of the toolbar action are also available in a right-click menu for nodes and edges

- Open editor
- Clone (node only)
- Delete
- Reset manual connection points (edges only)
### Keyboard shortcuts

- ctrl+c, ctrl+x, ctrl+v for nodes
- ctrl+drag to copy a node

### Canvas elements
#### Nodes

Node visualization is standardized. The Workflow prototype supplies a set of node types, that define how the node looks like. If the user should be able to change some of these properties, this must be done in the model component, that gets converted to a node - e.g. to change the color of a state machine state, the user must open the node editor and change the `color` property of the corresponding state there.

#### Edges

Similarly, edges are generated from the model by the workflow prototype.

#### Ports

Ports are part of node definition. A node can have any number of input and output ports.

The number and properties of ports depend on the configuration of the model component behind the node. Thus, if a `SwitchNode` has three cases, we need three output ports. Some nodes (like the `NoOpNode`) can even allow direct configuration of ports in their UXON.

### Node and edge editors

Type specific editors can be opened for nodes and edges showing default editors or other action widgets for the objects behind the nodes of this specific workflow.

### Node library

Each workflow prototype shall supply a number of node types to show in the node library.

We should have a `NodeTemplate` PHP class, that a prototype can configure to make nodes of this type appear in a common way. The template should set node properies in a way, that the node is recognizable even without any inner UXON config. Of course, when the config is there, the workflow prototype will generate node properties from that config.

### Disabled mode

Disabled WorkflowDesigner will be used to visualize Workflows without editing. It should still be interactive though: show descriptions of nodes and edges, expand group nodes, open info dialogs, etc.

### Facade integration

We need to reuse the often complex editors for parts of the workflow (mainly nodes and edges). The editors shall still be rendered using the currently active facade. However, we cannot use ShowObjectEditDialog and similar actions as-is because the save data directly. Instead we can use their dialog widget and replace the saving buttons with a JS script, that will pass the data to the big workflow model in the WorkflowDesigner instead of saving it.

To allow sich a hybrid approcha, we will use a WorkflowEditorMiddleware, that will provide the same server-side API across all facades. The middleware will translate AJAX requests required for a good designer widgets into PHP method calls for the workflow prototype classes, that will be supplied by the different workflow implementations.

### Buttons and permissions

For buttons in the designer, that require server interaction, the workflow prototype will supply button widgets to be placed in the toolbar of the designer widget: e.g. `$workflow->getButtonToEditNode()`. There should be such a method for every button, the WorkflowDesigner needs.

The actions will need to be modified for use in the WorkflowDesigner. In most cases, we will replace the primary (save) button with a `CustomFacadeScript` action to pass data to the main editor model.

Since we will not explicitly perform the actions in the server, their policies will have limited effect. When building the buttons for the toolbar, the WorkflowDesigner should check, if the user has access to the buttons action and only include the button in that case. This will at least allow non-data-dependent policies to work properly.

### Saving the workflow

The editor keeps all changes in memory until the save button is pressed. When this happens, the entire workflow is sent to the API at once. The middleware needs to translate this into various data sheet create/update operations.

It is important, to know which nodes/edges actually were changed in the WorkflowDesigner. This information should be included in the global JSON model behind the designer, so the API can know, what really changed and take care of changes only, leaving unchanged model components untouched (to avoid changing their create/modified_on timestamps). Similarly, the model must let the API know, which items were created or deleted.

## Running workflows

Some workflow prototypes are just there for editing and visualization (like for the StateMachineBehavior), others can explicitly be run. Since we want to keep the architecture as open as possible, there should be different types of runnable workflows expecting different input and producing different output.

The most obvious input/output contract is our standard TaskInterface (input) and TaskResultInterface (output) - this is what the `GenericTaskWorkflow` will be. Its configuration is saved in the core generic workflow storage, the input is a task and the output is a task result.

These workflows can be triggered by the `CallWorkflow` action. They are similar to

## Workflow classes and interfaces

- exface\Core
    - Interfaces\Workflows
        - WorkflowImplementationInterface
        - WorkflowInterface
        - WorkflowNodeInterface
        - StartNodeInterface
        - EndNodeInterface
        - WorkflowEdgeInterface
        - WorkflowPortInterface (?)
        - SimulatableWorkflowInterface
        - TaskHandlingWorkflowInterface
        - TaskHandlingNodeInterface
    - Workflows\
        - Nodes\
            - CallActionNode
            - SwitchNode
            - ForEachDataRowNode - must have something with a DataSheet as input (e.g. a task) and will call its out-port with a task containing the same sheet with each row separately - similarly to `ActionChain`
            - GroupNode - just a group of nodes, that does not do anything except grouping
            - NoOpNode
            - PlaceholderNode (temp. node, where we can configure everything in the UXON)
            - TryNode - Group node, that catches errors from any of the inner nodes and offers multiple out-ports: success, error (possible different error ports in future for different errors)
            - ErrorNode - throws an exception
        - GenericTaskWorkflow
        - StateMachineBehaviorWorkflow
- axenox\GenAI
    - Workflows\
        - Nodes\
            - CallAgentNode
        - AutonomousWorkflow
- axenox\AtlassianConnector
    - Workflows\
        - Nodes\
            - ReadTicketsNode
            - UpdateTicketNode
            - CreateCommentNode

### WorkflowImplementationInterface

All classes, that can be loaded into the WorkflowDesigner API must implement this interface.

- `getWorkflow() : WorkflowInterface` - returns the configured workflow prototype

### WorkflowInterface

This is the interface for workflow prototypes.

- getName()
- getDescription()
- getButtonToEditWorkflow()
- getButtonToEditNode()
- getButtonToEditEdge()
- saveWorkflowUxon()
- loadWorkflowUxon()

### TaskHandlingWorkflowInterface

These workflows can run triggerd by a TaskInterface instance. The GenericTaskWorkflow is one of them. These workflow prototypes will need to know, which node to pass the task to initially. The GenericTaskWorkflow should allow some configuration to pick the correct start node depending on the task type (interface). On the other hand, we could iterate over all start tasks and ask them if they support the task we have and find the best fit: there should be one for ScheduledTask, one for AiPrompt, etc.

- handle($task)
### SimulatableWorkflowInterface

- simulate($task)

## Workflow prototypes

- GenericTaskWorkflow
- StateMachineBehaviorWorkflow

## Generic workflow storage

### Tables

- exf_workflow
    - name, description
    - alias - чтобы можно было указать в [[CallWorkflow]].
    - app_oid - workflow можно экспортировать как часть модели серез [[MetamodelInstaller]].
- exf_workflow_node - от прототипа и конфига тут зависят порты.
    - workflow_oid
    - name, description
    - prototype_path
    - config_uxon
    - diagram_uxon - JSON with position, size, color, icon, etc. Keep that separately from config_uxon as we don't want to show this to the user editing the node.
- exf_workflow_edge
    - from_node_oid
    - from_port_alias
    - to_node_oid
    - to_port_alias
    - prototype_path
    - config_uxon
- exf_workflow_sim_scenario
    - name, description
    - input_uxon

## Simulation

One of the big problems of current multi-editor workflow models is trying out the config. We currently need to edit the nodes, save them (close the editors), visit a UI page, navigate to the place where the workflow can be started, start it for some demo data, see logs or traces to dig in. This is slow and often frustrating - especially if test data needs to be recreated.

The new WorkflowDesigner will simplify this! It will allow to define example input data (reusable!) for different scenarios and run the workflow right from the editor. The flow chart should show the path this particular runnhas taken. Such simulation scenarios shall be saved for reuse.

Simulation shall be done on server-side by the workflow prototype. Not all prototypes will support it.

In addition to the input data, sim scenarios will probably need some extra configuration. For example, the prototype could add listeners to data writes of certain objects and prevent them. This would allow to reuse the same data over and over again! 