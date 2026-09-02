---
description: "Use when working with action prototype classes"
name: "Developing action prototypes"
applyTo: "Actions/*.php"
---
# Actions

Actions are based on prototype classes like most components of the platform. 
Action prototypes can be placed in the `Actions` subfolder of any app. They 
are referenced by alias with app namespace: e.g. `exface.Core.SaveData` -> 
`exface/core/Actions/SaveData.php`. In the code actions need to be 
instantiated via `\exface\Core\Factories\ActionFactory`.

The UXON model of an action can be configured in multiple places: in the 
`action` property of a button widget, in a scheduled task, inside behaviors 
like `CallActionBehavior` or even in other actions like `ActionChain` or `CallAction`.

An actions UXON a be saved in the model as "object action" to be reusable - 
it will then get its own action alias. So an action alias can reference an 
action saved in the model or a "naked" prototype. In both cases, additional 
UXON properties can be specified wherever the action is used.

Actions can be triggered by widgets in the UI, by scheduled tasks, by CLI
commands or from model components like behaviors or even other actions.

## Component-specific development instructions

Before modifying an action, inspect its class-level docblock for a linked instruction file under
`Docs/Components/Actions`. If the docblock links such a file, read and follow it before making any
changes. Treat these component instructions as additional constraints and keep them current when
the action's architecture, contract, mapping, or required validation changes.

## Action workflow

Technically actions handle task objects 
(`\exface\Core\Interfaces\Tasks\TaskInterface`) and return result objects 
(`\exface\Core\Interfaces\Tasks\TaskResultInterface`).

In most cases, an action is called by a facade, that translates user input 
into a task, lets the action handle it and responds to the user in a way 
appropriate for the result type: e.g. HTTP facades could render a widget, 
export data as JSON or just show a message toast.

### Tasks

A task is basically what a button sends to the workbench. A task typically has:

- action selector - alias or class of the action to call
- reference to the page and widget ID of its trigger (e.g. a button widget)
- input DataSheet
- a generic "bag" of parameters for all sorts of additions inputs

Depending on the type of the facade, there are different classes 
implementing the `TaskInterface`.

- `\exface\Core\CommonLogic\Tasks\GenericTask`
- `\exface\Core\CommonLogic\Tasks\HttpTask`
- `\exface\Core\CommonLogic\Tasks\CliTask`

Most actions can handle any of these task types, but there can also be 
specialized actions of course.

### Results

Results of actions are also organized in different container classes. The 
result type depends on what the action does: work with data, show widgets, 
call services, etc. Some of the most common result types are:

- ResultMessage
- ResultData
- ResultWidget

### Transaction handling

An transaction object can be passed to the action along the task to make it 
reuse a data source transaction instead of starting one for itself.

## Important action types

### CLI actions

To expose an action through `vendor/bin/action`, implement
`\exface\Core\Interfaces\Actions\iCanBeCalledFromCLI`. The interface requires
two methods returning `ServiceParameter` instances:

- `getCliArguments()` defines positional arguments.
- `getCliOptions()` defines named options such as `--app=my.App`.

Create parameters with `\exface\Core\CommonLogic\Actions\ServiceParameter`.
Set at least a name and a useful description because these are shown by
`--help`. Use `setRequired(true)` for mandatory positional arguments. An option
without a default value requires a value; an option with a default value has an
optional value. Return an empty array when an action has no arguments or
options. See `Actions/GenerateModelFromDataSource.php` for an example.

The `ConsoleFacade` creates a `CliTask` and maps both arguments and options 
into its generic parameter bag. Action code should normally use
`$task->hasParameter('name')` and `$task->getParameter('name')`; this keeps the
business logic independent of the concrete task type. Use the CLI-specific
methods only when behavior genuinely depends on `CliTaskInterface`.

When an action already accepts an input DataSheet from the UI, keep one shared
execution path. Override or add a focused input-building method that returns
the supplied input unchanged and otherwise translates CLI parameters into an
equivalent DataSheet. Derive values from richer selectors where sensible: for
example, an object can provide its app and data source when those options were
omitted. Validate all required combinations and throw an action input exception
before performing side effects.

For long-running actions, use a streaming result (usually via
`AbstractActionDeferred`). The console adapter writes every message yielded by
a `ResultMessageStreamInterface`; regular results are printed as one message.

Document the exact command and examples in the action docblock. Command aliases
use a colon before the action name, for example:

```shell
vendor/bin/action exface.Core:GenerateModelFromDataSource --object=my.App.order
vendor/bin/action axenox.PackageManager:InstallApp exface.Core,axenox.MyApp
```

After implementation, validate syntax and command registration without running
the action's side effects:

```shell
php -l Actions/ExampleAction.php
vendor/bin/action exface.Core:ExampleAction --help
```

The help output must list the intended usage, arguments, option names,
descriptions and required markers. CLI availability does not bypass action
authorization or input validation. Ensure the action can safely run without a
trigger widget and never trust selectors or configuration merely because they
came from a command line.

## Action validation and security

Since the task is everything an action needs, it is important to validate 
every task before handling it.

For example, an action called from a web UI must compare its task to the 
widget it was called from to validate, if it was modified by an attacker. 
Actually the action referenced by the task must be exactly the action defined in the trigger widget in the first place - otherwise attacked could just replace the action alias in an AJAX request. In fact, this is the reason why action configs are never transferred in tasks, but are only save in the model.

The validation of tasks strongly depends on the action itself. Thus, there 
are actions, that always require a trigger widget to be referenced in a task 
(= can only be called from the UI) and actions, that can be called from CLI 
or a background queue - without a widget context. Each action prototype  
must implement methods like `isTriggerWidgetRequired()` to help the 
workbench understand, what is feasable and what is not.

It is important to program the action in a way, that it cannot be misused! Check input data, validate against the trigger widget if possible, etc.