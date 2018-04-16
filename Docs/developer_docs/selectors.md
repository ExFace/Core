# Component selectors as references between code and model

Selectors are strings that uniquely identify a component of the plattform or the model.

Selector interfaces allow to explicitly define, which types of selectors are usable for a specific component. Their implementation must be able to detect the selector type and to figure out, which app is able to load the component. The actual loadding is performed by the app and does not depend on the implementation of the selector.

For example, the <code>ActionSelectorInterface</code> defines all ways to identify an action: by alias, class name or file path. On the other hand, the MetaObjectSelectorInterface states, that objects can be selected via alias or UID.

Now, to get an instance of an action, we need the corresponding app to create it first, so the ActionFactory will use the selector it gets to get the selector of the app, use it to fetch the app from the workbench and ask the app (which is a DI-container) to hand out the action. Neither the factory, nor the selector need to know, how exactly the app creates it's actions, where the corresponding classes are stored, etc. 

Selectors should be used for all components that can be referenced from UXON or any kind of configuration.

## FAQ

### Why do aliases not include the component type?

Indeed, while file and class based selectors seem to include the component type (in for of the respective subfolder like "exface/Core/Actions/ReadData.php", "exface\Core\DataConnectors\MySqlConnector", etc.), aliases do not (e.g. "exface.Core.ReadData"). The reason is simple: aliases are mostly used by modelers or UI designers, who do not know the internal structure of the apps they are using and, thus, cannot tell, which file or class the want to use. If fact, they do not even care wether it is a class or file or anything else - they just require the functionality of a component of a specific app and that app is free to load that functionality from whatever file or folder it likes. On the other hand, for programmers, who will use file and class selectors (e.g. via the static property ::class) it is essential to know, which specific class they actually want to use.

### Why use different selectors at all? Aren't aliases/UIDs enough?

Well, any type selector would technically be enough to identify a component. However, differents forms of selectors come in handy in different situations: a UI designer requires short, but readable selectors (aliases), while a developer likes to reference a specific class most explicitly (for simple dependency management). However far not all components are represented by a dedicated class - many are more like saved configurations for classes). When it comes down to reading a model or exporting it, UIDs are a great choice as they never change and can easily be used as keys in a data source.

### Why selector classes and not strings?

Indeed, the PSR-11 standard for dependency containers suggests using strings only, however having all the different types of selectors and components to select, would make loader methods overcomplicated with all the different options. Selector classes and interfaces are a great way to standartize these options and provide commmon logic for them like validation (e.g. selector::isUid() etc.). They also enable typehinting in PHP.