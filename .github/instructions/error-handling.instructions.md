---
description: "Use when throwing exceptions, writing exception classes and planning error handling"
name: "Error handling"
---

The workbench uses its own advanced exception classes, that allow to generate very detailed debug widgets for the logs. 

Error handling and logging is very important as most app designers and support people are not programmers, but still need to understand the cause of every error quickly. We monitor our apps every day and attempt to fix errors even before users contact the support. Consequently, we need the logs to be very well organized and easy to read. This is achieved by a combination of features:

- Permanent Log-ID for every log message
- Debug-widgets with detail tabs for affected data, performed SQL statement or HTTP request, summary of action logic, etc. 
- Logbooks with markdown descriptions of performed logic and mermaid flow diagrams in actions, behaviors, data flows, etc. 
- Message codes with explanation attached. Message codes are part of the model and allow app designers to define separate texts for end-users in every language. 

## Global rules

- always throw exceptions for errors
- create custom exception classes for errors in important components: e.g. special DataSheet-exceptions, DataQuery-exceptions, etc. Pass the component instance into the exception constructor. 
- Think about who the exception is for: if it is primarily intended for end-users, make the message translatable, consider creating a message code in the model with an appropriate hint. 
	- normally, the exception message is visible to developers only, while the message code and model (title, hint) is shown to end users. The message model is accessible via `getMessageModel()` and is loaded automatically if a message code was provided. 
	- However, if the exception message is built explicitly for end users, use a translation key for it and `setUseMessageAsTitle(true)` on the exception. 
- only use existing message codes (from `Model/07_MESSAGE.json` in every app) in exception constructors
- implement `createDebugWidget()` to add helpful tabs to the debug widgets - in particular, if the exception represents an error closely connected to a model component. 
- if an exception is to be silenced, log it via `$this->getWorkbench()->getLogger()->logException()` with a appropriate log level. 
- use Logbooks to document the flow of complex processes (e.g. in action, behaviors, etc.). Pass logbooks to exceptions and include them in the debug widgets

## Custom exception classes

All exceptions thrown within the workbench must implement the `exface\Core\Interfaces\Exceptions\ExceptionInterface`. In addition to the regular message, these exceptions have 

- an `alias`, which corresponds to a message code stored in the metamodel object `exface.Core.MESSAGE` and is used to load additional information/documentation about this type of error
- a `Log-ID` - a short unique string, that can be used to reference this particular occurrence of the error - e.g. in a support inquiry. 
- the method `createDebugWidget()`, which generates a widget UXON, that is saved in a separate log and is linked with every regular log entry. Thus, any log entry can be "opened" to see a detailed debug widget showing exactly, what happened. 

## Message codes and models

The alias of an exception connects it to a message model. The latter is part of the metamodel. Every app contains a list of messages. The dump of that list is available in `Model/07_MESSAGE.json`. 

- unique searchable message code
- user-oriented translatable title and hint (remediation idea)
- a detailed description for app designers

Messages are primarily used for errors but can also be warnings or informative hints. 

### Message translation

The exception message itself is normally a non-translatable technical explanation only visible for designers and developers, while the message model (title, hint and description) is intended for end users. 

However, there are situations, when the user-oriented message must be built by code to include data or other runtime information. In this case, an exception can be explicitly told to show its message to end users too: `setUseMessageAsTitle(true)`. This will print the exception message instead of the title of the message model. The developer must then take care of making the message translatable. 

## Debug widgets

Exceptions and other classes with the `exface\Core\Interfaces\iCanGenerateDebugWidgets` interface, build special debug widgets, that are saved beside regular logs. A `DebugWidget` is basically a tabbed dialog, which collects its tabs from all classes involved in the error or its trace. 

For example, an SQL exception wrapped in a `DataQueryFailedError` will produce a tab with native error details from the respective DB engine and a tab with the SQL being performed. If the exception was thrown by a DataSheet it is likely to be caught and wrapped in a DataSheet-exception before being thrown further. The DataSheet-exception will add its own tab with data from the sheet. This way, the resulting debug widget will show everything needed for a detailed understanding of the error. 

All debug widgets include 

- a general information tab with the description from the message model and a collection of documentation links for further reference 
- a stack trace tab with a markdown stack trace
## Log performance

**IMPORTANT:** despite the high complexity of logged information, logging must not affect performance of regular (non-error) requests! 

We follow these rules to minimize performance issues:
- Write logs only if at least one message with log level `ERROR` or above is produced
- Save debug widgets as UXON and not rendered. They are rendered when being opened. 
- Render Logbook markdown and widget UXON only when their log entry is written