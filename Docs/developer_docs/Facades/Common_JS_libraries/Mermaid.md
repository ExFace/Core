# Mermaid-syntax diagrams with Pele

Core provides Pele independently of Codiware and Markian. The runtime is installed
through the Core Composer dependency `npm-asset/pele`. The normal and print/PDF
templates of `DocsFacade` use it to render `mermaid` fences. The normal template
retains its pan/zoom controls; print output contains static diagrams without controls.

| Key | Vendor-relative asset |
| --- | --- |
| `LIBS.PELE.JS` | `npm-asset/pele/dist/pele.global.js` |
| `LIBS.PELE.ADAPTER.JS` | `exface/core/Facades/AbstractAjaxFacade/js/exfMermaid.js` |
| `LIBS.PELE.CSS` | `exface/core/Facades/AbstractAjaxFacade/css/exfMermaid.css` |

Other Core-based renderers can load the runtime, adapter and stylesheet in that
order, resolving these keys through their facade. Call
`exfMermaid.renderAll(hostElement)` after placing Markdown HTML in the host.
It recognizes `.language-mermaid` and `code[data-language="mermaid"]`, skips
already rendered diagrams and scopes changes to that host. Optional
`{panZoom: true}` requires `npm-asset/svg-pan-zoom/dist/svg-pan-zoom.min.js`,
installed through Core's Composer dependency `npm-asset/svg-pan-zoom`. A failed diagram retains
its source, displays a diagnostic and logs the error to the browser console.

The Core-owned stylesheet copies the Codiware/Markian palette without loading
either editor: gray shapes, darker borders, and pale-yellow folded notes.
An ancestor with `data-theme="dark"` selects the dark palette. Mermaid `style`
and `classDef` colors remain inline and take precedence over defaults, including
ActionChain's red error nodes and custom state-diagram classes. Pele supports
the inline label formatting emitted by `StateMachineBehavior`, but it does not
reproduce arbitrary HTML labels or HTML tooltips.

Source is limited to 20,000 characters, 500 lines and 500 edges; generated SVG is
limited to 2 MB and 10,000 elements. Source-defined links and images are disabled.
The adapter preserves SVG IDs and styling rather than restyling custom colors.

UI5 and jEasyUI Markdown widgets use Markian's Pele extensions. Other UI5
integrations still use Mermaid.js and `LIBS.MERMAID.JS`.
Do not point that key at Pele: their runtime APIs differ.

### Focused browser validation

Serve the installation's vendor directory over HTTP (for example
`php -S 127.0.0.1:5197 -t <vendor-directory>`). Set `PELE_TEST_URL` to that server,
and `PLAYWRIGHT_MODULE` to an existing `@playwright/test` module, then run
`node --test Tests\JavaScript\Pele.test.js` from Core. No editor package is needed
at runtime; Playwright is test tooling only. The fixtures use a real,
database-free Workbench to generate state labels and ActionChain error colors.