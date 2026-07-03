---
description: "Use when working with action prototype classes"
name: "Actions"
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