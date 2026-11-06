---
name: "Behaviors"
description: "Use when when working with model behaviors"
applyTo: "Behaviors/*.php"
---
# Behaviors instructions

Behaviors are configurable UXON prototype classes, that attach event driven logic to a metaobject. Typical examples are:

- `UndeletableBehavior` will prevent delete operations on certain conditions
- `SoftDeleteBehavior` will replace a real delete with an update setting a deleted flag. It can also make sure, a filter over the deleted flag is added to every read
- `StateMachineBehavior` will validate changes against rules in its state model, preventing certain changes of the the attribute defined as the state. It can also send notifications on certain changes, block updates to certain attributes in specific states, and many other things.

Each behavior is attached to exactly one object. The behavior prototype implements a specific type of business logic, while its UXON config adjusts this logic to the needs of the particular object.

## Prototype classes

Behavior prototypes are PHP classes implementing the `\exface\Core\Interfaces\BehaviorInterface`. Extend the `exface\Core\CommonLogic\Model\Behaviors\AbstractBehavior` to get comfortable base functionality.

Behavior classes must be located the folder `my/app/Behaviors` in order for the model editor to discover and list them correctly. Additional classes used inside behaviors should be placed in the `Common` folder or it's subfolders.
## Workflow

### Listening to events

Behaviors are instatiated when their object is loaded. If the behavior is based on the `AbstractBehavior`, the method `registerEventsListeners()` will be called. Override this method and attach behavior methods to specific workbench events.

### Handling events

When an event of interest is fired, all behaviors listening to it will be triggered. Make sure, your listener checks, if the event really fits: e.g. behaviors listening to data events like `OnUpdate`, `OnCreate`,  etc. will typically need to check, if the DataSheet in the event is based on their object.

Once the behavior knows, that it will react to the event, a separate `OnBeforeBehaviorApplied` event must be fired. This is particularly important for debugging and performance monitoring. Only fire this event if really doing something!

Behaviors can modify DataSheets and other objects bound to the events. They can also `preventDefault()` and `stopPropagation()` - especially if the behavior is to substitute some core logic by its own approach.

When the behavior is done processing an event, it must fire `OnBehaviorApplied`.

### Using logbooks

Many behaviors implement complex and highly configurable logic, so it is very important to let app designers understand the exact logic flow. Create and instance of `BehaviorLogbook`, fill it with a brief description, of what the behavior is doing, and pass it on to events and exceptions. 