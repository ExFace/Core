---
name: "Contexts and context scopes"
description: "Use when working with contexts, their scopes and the context bar in the UI"
---

The user interface of all apps always shows a global "context toolbar" with icons for some global features, that do not depend on the current UI page. This is similar to the Windows system tray. Most facades will display the context bar within the global header on the right side.

Each icon in the context bar represents a context. Contexts are PHP classes implementing `exface\Core\Interfaces\ContextInterface`, that take care of a group of global functions. Typical examples are:

- `DebugContext` - shows a menu with debug tools
- `FavoritesContext` - allows to mark any object with a UID as a favorite for the current user
- `ObjectBasketContext` - similar to the `FavoritesContext`, but the data only lives for the duration of the current session.

## Context scopes

Contexts can save their data in different scopes:

- the request scope keeps data in the current requests only, saving it in-memory only
- the window scope lives "within" the current browser window or tab (currently not really working and falling back to the session)
- the session scope saves its data in the session
- the user scope stores data for the user independently of the session
- the installation scope stores data globally

Theoretically each context can be placed in any of the scopes. But in practice, contexts make only sense in certain scopes - e.g. The debug context lives in the installation scope, while favorites make sense for user scope only.

## Context menus

Each context can provide an interactive popup via the `getContextBarPopup()` method. This popup is displayed when the user clicks the context icon in the context bar. The popup is a container widget that the context fills with its own widgets (buttons, lists, forms, etc.).

The `ShowContextPopup` action is responsible for rendering the context popup and can be triggered by clicking a context icon. The popup exists in a specific scope and can interact with that scope's data.

### Example: implementing a context popup

```php
class MyContext extends AbstractContext
{
    public function getContextBarPopup(Container $container)
    {
        // Add widgets to the popup container
        $button = WidgetFactory::createFromUxon(...);
        $container->addWidget($button);
        return $container;
    }
}
```

### Example: triggering a context popup

A context popup is automatically shown when clicking a context bar icon. You can also trigger it programmatically via the `ShowContextPopup` action by passing the context alias and scope name as parameters.

### Context indicators

Contexts can display an indicator (small badge or text) on their icon in the context bar. For example, the `NotificationContext` displays the number of unread notifications. Use `setIndicator()` to set the indicator value and `setColor()` to control its visual appearance.

### Visibility control

Use `setVisibility()` to control when a context appears in the context bar:

- `CONTEXT_BAR_SHOW` - always visible
- `CONTEXT_BAR_SHOW_IF_NOT_EMPTY` - only visible if the context has data (default)
- `CONTEXT_BAR_HIDE` - never visible
