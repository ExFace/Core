# Introduction to offline work

## Regular web apps with AJAX requests (online)

Normally web apps work by sending browser requests to their facade API all the time, as shown below.

```mermaid
sequenceDiagram
    box Browser
    participant Web UI (e.g. UI5)
    participant JS Runtime
    end
    box Workbench
    participant UI Facade (e.g. UI5)
    end
    Web UI (e.g. UI5) ->> UI Facade (e.g. UI5): Request Webapp
    UI Facade (e.g. UI5) ->> JS Runtime: Regular response with HTML/JS
    JS Runtime ->> Web UI (e.g. UI5): Render UI
    Web UI (e.g. UI5) ->> JS Runtime: User interaction
    JS Runtime ->> UI Facade (e.g. UI5): Load data, additional Views, etc.
```

## Progressive web apps

Progressive web apps (PWA) use special APIs offered by all modern browsers to allow the user to continue to work with the app if the connection is lost. The workbench allows facades to implement multiple levels of offline capability. But in any case, working offline requires a Service Worker - a special type of web worker with the ability to intercept, modify and respond to network requests.

The core includes a JavaScript toolbox `exfPWA.js`, that can be used to quickly add support for working offline with. It offers tools for the following features.

### Caching previously loaded data for offline use

The most simple way to make the app available offlie is to cache all assets (HTML, javascript and server responses) in the browser and use it when offline. Modern browsers offer a special "Cache API" to do this: it can be used by Service Workers to cache entire requests and use them to respond to the regular JS runtime, thus mimicing the behavior of the server. The logic of the web app actually may even be unaware, that it is offline!

```mermaid
sequenceDiagram
    box Browser
    participant Web UI (e.g. UI5)
    participant JS Runtime
    participant Cache API
    participant ServiceWorker
    end
    box Workbench
    participant UI Facade (e.g. UI5)
    end
    Web UI (e.g. UI5) ->> UI Facade (e.g. UI5): Request Webapp
    UI Facade (e.g. UI5) ->> JS Runtime: Regular response with HTML/JS
    JS Runtime ->> Web UI (e.g. UI5): Render UI
    JS Runtime ->> ServiceWorker: Initialize Workbox engine, enable request cache
    alt Online
        Web UI (e.g. UI5) ->> JS Runtime: User interaction (Online)
        JS Runtime ->> ServiceWorker: Load data, additional Views, etc.
        ServiceWorker ->> UI Facade (e.g. UI5): Fetch data from server if required by route config
        UI Facade (e.g. UI5) ->> ServiceWorker: Regular response with HTML/JS
        ServiceWorker ->> Cache API: Cache Response
        ServiceWorker ->> JS Runtime: Regular response with HTML/JS
    else Offline
	    Web UI (e.g. UI5) ->> JS Runtime: User interaction (offline)
	    JS Runtime ->> ServiceWorker: Load data, additional Views, etc.
	    ServiceWorker ->> Cache API: Get response from cache
	    Cache API ->> ServiceWorker: 
	    ServiceWorker ->> JS Runtime: Regular response with HTML/JS
    end

```

### Buffering and syncing actions performed offline

Facades can also buffer actions performed offline and sync them with the server in background when the connection is restored.

```mermaid
sequenceDiagram
    box Browser
    participant Web UI (e.g. UI5)
    participant JS Runtime
    participant IndexedDB
    participant Cache API
    participant ServiceWorker
    end
    box Workbench
    participant UI Facade (e.g. UI5)
    participant TaskFacade
    participant Background Queue
    participant Workbench
    end
    opt If offline
        Web UI (e.g. UI5) ->> JS Runtime: Action triggered offline
        JS Runtime ->> IndexedDB: Put action data in the offline queue
        IndexedDB ->> JS Runtime: Continue as-if the action was performed
        JS Runtime ->> Web UI (e.g. UI5): Notify user, that action was enqueued
    end
    opt When online again
        ServiceWorker ->> IndexedDB: Load offline action queue when online again
        IndexedDB ->> ServiceWorker: 
        ServiceWorker ->> TaskFacade: Send each action to a server queue
        TaskFacade ->> Background Queue: Put the task in the (synchronous) offline queue
        Background Queue ->> Workbench: Handle the task immediately (synchronously)
        Workbench ->> Background Queue: 
        Background Queue ->> TaskFacade: 
        TaskFacade ->> ServiceWorker: 
        ServiceWorker ->> IndexedDB: Mark task as synced or put it in error list
    end

```