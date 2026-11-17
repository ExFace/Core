# JSON and UXON editing with JSONEditor

The AJAX facades integrate [JSONEditor](https://github.com/josdejong/jsoneditor)
through Core's shared `exfJsonEditor` adapter. JSONEditor supplies structured
tree/view editing, code editing with Ace, search, history and the underlying
validation UI. The adapter adds platform-specific UXON tools and owns their
lifecycle; facade elements supply configuration and connect the editor to their UI.

For the facade-independent feature overview, see
[InputJson and InputUxon for app designers](../../../understanding_the_metamodel/Global_UI_principles/Input_widgets/InputJSON_InputUXON.md).
This page describes the concrete implementation, not a requirement that every
facade must use JSONEditor.

## Responsibilities

| Layer | Responsibility |
| --- | --- |
| `InputJson` / `InputUxon` widgets | Declare editor settings and UXON context in the widget model. |
| [JsonEditorTrait](../../../../Facades/AbstractAjaxFacade/Elements/JsonEditorTrait.php) | Generate instance configuration, dependency URLs, context getters and facade integration snippets. |
| [exfJsonEditor.js](../../../../Facades/AbstractAjaxFacade/js/exfJsonEditor.js) | Wrap JSONEditor, implement shared interaction logic and manage instance resources. |
| [exfJsonEditor.css](../../../../Facades/AbstractAjaxFacade/css/exfJsonEditor.css) | Style the shared tools, popups and indicators. |
| Concrete facade element/control | Load dependencies, provide the host, propagate changes and state, render server errors and destroy the adapter. |

Reusable logic lives in the external JS file rather than being emitted again for
every widget. Generated pages retain instance-specific configuration and
callbacks. The external asset can be cached independently of page content.

## What Core adds

For InputUxon, the adapter extends JSONEditor with:

- Context-aware autosuggest and automatic insertion of property templates.
- Preset search, preview, replacement, wrapping and insertion.
- Property details and a model browser for attributes and relation paths.
- Context-menu tools for JSON paths, clipboard operations, larger string editing,
  clearing content and UXON comment conventions.
- Asynchronous UXON validation, a toolbar warning and error breadcrumbs that
  remain useful when parent nodes are collapsed.
- UXON help, an empty-editor preset hint and scroll restoration.

Popups use picoModal; embedded previews use additional JSONEditor instances.
The adapter scopes lookups and handlers to their editor or popup, so multiple
editors can coexist. It cleans up previews and popups along with the main editor.
InputJson uses the shared value/state handling without enabling UXON assistance.

## Dependencies and configuration

The integration supports JSONEditor `^7.0 || ^8.0 || ^9.0 || ^10` and picoModal
`^3.0.0`, and requires jQuery and `exfTools`. Facade asset configuration supplies:

| Key | Purpose |
| --- | --- |
| `LIBS.JSONEDITOR.JS` | JSONEditor library, including Ace for code mode. |
| `LIBS.JSONEDITOR.CSS` | JSONEditor styles. |
| `LIBS.JSONEDITOR.PICOMODAL` | Popup library. |
| `LIBS.MARKDOWN.CSS` | Styling for descriptions shown in the tools. |

The trait adds the Core adapter and stylesheet URLs. Load the libraries before
creating an adapter. UI5 registers them as module dependencies rather than
injecting HTML script tags.

`buildJsJsonEditorConfig()` maps widget settings to JS data and callbacks:

- `uxon`, `autosuggest`, `validate`, `disabled`, `required` and JSONEditor options.
- Editor name, endpoint/help URLs and translations from `WIDGET.UXONEDITOR.*`.
- `schema()`, `rootPrototype()` and `rootObject()` getters. Widget links are
  evaluated when used rather than frozen at initialization.
- `serverAvailable()`, allowing a facade to restrict custom requests.
- `onChange`, `onError`, `onAjaxError` and `onSuccess` integration callbacks.

Keep facade-specific behavior in those callbacks or the host control, not in the
shared adapter. In PHP-generated JavaScript, use JS escaping explicitly:
`escapeString($value, true, false)`. A facade may default to HTML escaping.

## Instance and value contract

Create one adapter per host:

```javascript
var oAdapter = exfJsonEditor.create(domHost, oConfig, sJson);
```

The instance is also available as `domHost.exfJsonEditor`; no generated global
editor variable or function prefix is needed.

| Method | Purpose |
| --- | --- |
| `getEditor()` | Access the underlying JSONEditor when integration requires it. |
| `getValue()` / `setValue(value)` | Read or replace the widget's JSON text. |
| `setDisabled(boolean)` | Switch between editable/read-only behavior, including Ace. |
| `setRequired(boolean)` / `getRequired()` | Maintain the widget's required state. |
| `restoreScroll()` | Restore the saved tree scroll position. |
| `refreshServerAvailability()` | Refresh assistance availability and its indicator. |
| `destroy()` | Release the editor, requests, handlers, timers and popups. |

Values cross the widget boundary as JSON text. Setters also accept objects and
arrays; null/empty input is normalized to an empty object. Empty objects and
arrays read back as an empty widget value. Invalid JSON text is preserved and
opens in code mode instead of being discarded.

Programmatic setters do not report user changes. User edits invoke `onChange`;
Ace's synchronous replacement events are coalesced before propagation. Destroy
the adapter before removing its host. Creating another adapter on the same host
destroys the previous one.

## Server assistance and errors

Autosuggest, templates, presets, property details and model browsing use
`exface.Core.UxonAutosuggest`; UXON validation uses `exface.Core.UxonValidate`.
The shared adapter sends custom requests to `ajaxUrl` using fetch or jQuery AJAX,
not the facade's general action-request pipeline.

Consequently, every server operation is gated by browser connectivity, the
launcher's `isOnline()` state when present, and `serverAvailable()`. UI5 permits
these endpoints only when the configured online backend is
`UI5FacadeServerAdapter` or a subclass. This includes a PWA whose offline adapter
wraps that backend; an incompatible exported backend must not call these endpoints.

When assistance is unavailable, the adapter shows a small translated warning
badge, suppresses new requests and clears cached suggestion templates. Local
editing, JSON syntax validation and local tools remain available. Browser
online/offline events and editor interactions refresh availability; the UI5 host
also observes the launcher's network model for forced-offline changes.

Model-search failures appear inline, with server messages, hints and log IDs
where available. New searches can retry. HTML error-widget responses are passed
intact to `onAjaxError` for native facade rendering rather than inserted by the
shared adapter. jEasyUI delegates to `buildJsShowErrorAjax()` and raises the error
dialog above the editor popup; UI5 uses its component's error-dialog renderer.
Empty model/preset searches show an informational message, not an error.

## Facade integration

### HTML-based facades

Use `buildHtmlJsonEditor()` for the host and `buildJsJsonEditor()` for
initialization. Merge `buildHtmlHeadTagsForJsonEditor()` into the facade's
dependency tags. The trait's value and state methods address the host-owned
adapter; override its change callback if the facade needs different event wiring.

jEasyUI's `EuiInputJson` uses this approach, with `EuiInputUxon` sharing it.
The facade owns label/layout integration and tab visibility hooks for scroll
restoration. The trait exposes dependency helpers separately because some facades
have a final head-tag method.

### UI5

`UI5InputJson` and `UI5InputUxon` use the trait's configuration and dependencies
with the custom `exface.ui5Custom.JsonEditor` control. Its native string `value`
property supports named models, relative contexts and standard binding modes.
User edits update the property and fire `change`; model updates use the adapter's
setter without generating synthetic user changes.

The control forwards enabled/required and value-state properties. It destroys the
adapter before rerendering and on exit, recreates it from the current value and
preserves code mode. It also disposes its network-model binding. Popups live in
UI5's static area and participate in its focus handling and z-index allocation.

## Regression checks

See the [shared editor testing instructions](../UI_facades/index.md#shared-json-and-uxon-editor)
for the real-browser fixtures and commands. Core covers editor behavior,
connectivity gates and error delegation; UI5Facade additionally covers model
binding, rerendering and forced-offline lifecycle behavior. Focused PHPUnit
generator tests live in each owning app's `Tests/PHPUnit` suite.