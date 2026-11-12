# Model Components

> <form method="get" target="_blank" rel="noopener" style="display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;">
>   <label style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
>     <span>Component type</span>
>     <input id="component" name="component" type="text" placeholder="action" required>
>   </label>
>   <label style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
>     <span>Selector</span>
>     <input id="selector" name="selector" type="text" placeholder="exface.Core.objectAttributeGroupsDialog" required>
>   </label>
>   <button type="submit">open</button>
> </form>

The metamodel consist of many different components: objects, actions, UI pages, and many more. You can quickly preview any component on this page.

> [!NOTE]
> The search form only works on an installed workbench. It will not work when browsing documentation in third-party tools (e.g. on GitHub).

## Component registry

There is a global component registry, where a available components are described. Apps can add their own components - see the [component registry guide](developer_docs/Component_registry.md) for registry configuration.
