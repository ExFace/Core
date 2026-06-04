# PHP naming conventions

When developing in PHP, the [PSR-12](https://www.php-fig.org/psr/psr-12/) standard and the underlying PSR-1 and PSR-2 standards must be followed. Additionally, the conventions described below are a must too. In case of a conflict between the two rulesets, the conventions below are to be preferred.

In general, the code should also adhere to universally accepted principles, such as Robert C. Martin's well-known Clean Code approach ([well summarized here](http://clean-code-developer.de/)). In case of doubt or conflict, the rules stated in this document must always take precedence.

## Namespaces

Namespaces must used in all classes, interfaces and other entities. They must comply with the PSR-4 standard to be compatible with common autoloaders, especially Composer.

Each app receives its own namespace. All classes belonging to the app must be contained within this namespace. Sub-namespaces can be used.

## Classes 

### Class and file names

Class names and file names must follow the PSR-4 standard to ensure compatibility with all common autoloaders.

Each file can contain at most one class.

The file/class name suffix "Class" as used with PHP v4 and v5 must not be used: `exface\Core\Workbench` (correct) vs. `exface\Core\WorkbenchClass` (incorrect).

### Constructors and Factories 

"Static factories" should be used for instantiating classes unless the class has a very narrow scope and will only be instantiated in one or two places. Factories should reside in their own namespace (e.g., exface\Core\Factories). Sub-namespaces may be used.

If public `__construct()` methods are needed in classes with factories, these methods should be marked as @deprecated with a reference to the factory.

This allows for different constructors with different parameters within the factory. Using a dedicated namespace ensures that the factories are easy to find.

### App classes

App Classes App classes must adhere to a specific naming convention. The app class must be directly within the app namespace and named `<app_name>App.php`: e.g., `CoreApp.php`. This allows apps to be automatically recognized and loaded in the background.

## Interfaces

Each reusable class should have at least one interface. Interfaces should be located in a separate sub-namespace within the app (e.g., exface\Core\Interfaces). Sub-namespaces may be used.

For method parameter/return typing within an interface, only interfaces should be used, not concrete classes.

Interfaces should only include public methods really used outside of the class. Do not bloat interfaces by including all possible methods.

Make sure, the scope of an interface is clearly visible. Prefer multiple smaller interfaces used in combination (like property interfaces below) over big single interfaces. 

### Object Interfaces 

Interfaces for entire objects (e.g., Widgets, Actions) must be named according to the following pattern: `[ObjectType]Interface` – e.g., `WidgetInterface`, `ActionInterface`.

Object interfaces mainly serve to abstract method parameters. They are essential for minimizing dependencies between concrete classes.

### Type interfaces

Secondary interfaces for certain types of objects should be located in the same namespace as the main interface for that object: e.g. `EmailMessageInterface` in the same folder as the base `CommunicationMessageInterface`. This gives a quick overview of available interfaces when looking for suitable ones.

### Property Interfaces 

Interfaces that standardize certain properties/roles of objects must be named according to the following pattern: `I[Verb][Property]` – e.g., `IHaveChildren`, `ITriggerAction`. This notation reads as a simple English sentence (i.e., "I have children," "I trigger action").

These interfaces should be in a sub-namespace indicating which object the described properties belong to: e.g., `Interfaces\Widgets\iHaveChildren`, `Interfaces\Actions\iShowWidget`.

Using such property/role interfaces allows typical patterns within complex inheritance structures to be standardized. For instance, the widgets `Menu` and `Data` both have buttons, and the `IHaveButtons` interface ensures that both use the same methods to manage buttons. 

These interfaces also simplify developing new subclasses: much of the API can already be described through interface composition.

## Class properties

All class properties must be private. If external access is required, use `get`/`set` methods. 

This allows to change the internal logic of a class at any time without refactoring. 
## Methods 

Use well readable method names, that clearly show the purpose of the method. 

### `get`, `set`, `is`, `has`, `will`, methods

Use getters and setters to access class properties. Make sure getters are well readable: use `hasNoun()`, `isAdverb()` or `willVerb()` instead of `getXxx()` to improve readability: e.g. `hasHeader()`, `isDisabled()`, `willSaveData()`, etc. 

### `is()` and `isExactly()` methods 

To compare UXON configured prototypes or objects with inheritance, `is()` and `isExactly()` methods should be used (e.g., for meta-objects, widgets, actions).

For UXON prototypes:

- `is()` = same prototype
- `isExactly()` = same UID

### `buildXxx()` methods 

Methods that generate code (e.g., JavaScript, HTML, XML, SQL) should be prefixed with "build": e.g., `buildSql()`.

## Traits 

Traits must include the suffix "Trait" in their names (and therefore in the file names), e.g., `JqueryElementTrait`.

Traits should be placed in a sub-namespace close to the classes, they are intended for. 

## Errors and Exceptions 

### Exceptions

Only exceptions should be used for error handling. "Expressive" exceptions that describe the error type (not the location) should be used, and they should reside in a separate namespace (e.g., `exface\Core\Exceptions`). Sub-namespaces may be used.

In infrastructure apps, all exceptions must have the `ExceptionInterface` to ensure best compatibility with our debug tools. Foreign exceptions should be wrapped. The `InternalError` exception can be used as a generic wrapper. 

Always through the most specific exception available as it will produce the most helpful debug widgets. 

Create dedicated exception classes with a customized `createDebugWidget()` method wherever possible. 

### Error Codes 

Message codes can be defined in the metamodel. They have translatable titles, hints and descriptions. The message code can be passed to the constructor of an exception as `$alias`. 

Create error codes for
- exceptions, that need explanation (e.g. common misconfiguration issues)
- errors, that need to translated

### Translatable errors

Errors intended to be seen by end users must be translatable:

- either having a dedicated error message code (alias)
- or having a translated exception message and marked with `useExceptionMessageAsTitle(true)`

## Events 



## Comments and Annotations 

Comments are primarily to aid readers who think differently than the original author (including the author themself after progressing in their understanding). Even with very good code, writing comments is important as they double the chance the code will be understood: either the code or the comment will be understood.

Especially with Alexa UI, it’s expected that the code or its annotations will be read and interpreted by various people: core developers, app developers, and app designers. The more comprehensive and accessible a description, the easier it is for less experienced programmers or even non-programmers to understand the intent. The latter, in particular, will have no opportunity to view the actual code.

The effort to write comments is accepted to achieve maximum understanding of the code and its meaning in any situation by any developer (regardless of experience, worldview, etc.).

### PHP-Doc 

PHP-Docs must be written in English. Annotations are required for the following entities:

- Interface
- Class
- Public method

Annotations are recommended, but not strictly required, for the following entities:

- Protected method
- Private method

PHP-Docs at the method level should not consist solely of the method name (even if written differently) and the parameter list. They should instead provide extended information about the method, such as typical parameter examples and meanings, effects, and recommendations for overriding the method in derived classes, etc.

### Inline Comments 

Developers are free to use inline comments. Complex code sections with over 20 lines should include inline comments. The language can be freely chosen.

## UXON Annotations 

Some classes, such as Widgets, Actions, DataConnectors, and DataSheets (identifiable by the iCanBeConvertedToUxon interface) can be configured using UXON. These classes require specific annotations at both the class and method levels to make the supported UXON properties identifiable and documented.

### Class Annotations 

Annotations at the class level are used for generating documentation for UXON entities. Descriptions should include how the UXON configuration for this entity should look and points of attention – preferably with examples.

Currently, there are no special doc tags at this level.

### Method Annotations 

For each method that reads a UXON property (typically the setter with the same name), the annotation should include the following doc tags:

```
@uxon-property – Key of the property in UXON
@uxon-type – Data type of the property in UXON.
``` 

Complex data types (e.g., widgets) should be specified as fully qualified PHP class names for the corresponding class, e.g., `\exface\Core\Widgets\Container` for the container widget data type. Internal data types (`\exface\Core\DataTypes\...`) can also be referenced.

## Facades and generated code 

Facades contain PHP logic to generate code in frontend languages like JavaScript, HTML, and XML. They must be meticulously commented, as mixing multiple programming languages in a single document significantly reduces readability.

In addition to the basic rules from section 4.8.1, protected or public helper methods that include non-PHP code must also have a method doc block. This documents the interaction of generators with the generated language and its frameworks and enhances the reusability of generated code snippets. This is especially important, as Clean Code principles are difficult to apply in such cases.

