# Sending messages to and from the ServiceWorker

The examples below were borrowed from [here](https://gist.github.com/Jonarod/450a369cd437acb23f9a365eb5c2da35).

### Page to ServiceWorker

```js
// in regular JavaScript
navigator.serviceWorker.controller.postMessage({'hello':'world'});
```

```js
// in ServiceWorker.js or included files
self.addEventListener('message', event => { 
  console.log(event.data); // outputs {'hello':'world'}
});
```

### ServiceWorker broadcast to all connected Pages

```js
// in ServiceWorker.js

this.clients.matchAll().then(clients => {
  clients.forEach(client => {
    client.postMessage('Broadcast to all clients !');
  )}
});
```

```js
// in regular JavaScript

window.addEventListener('message', event => { console.log(event) }, false);
```

### Private communication

```js
// in regular JavaScript

const messageChannel = new MessageChannel();

// Listen for messages from service worker
messageChannel.port1.onmessage = (event) => {
  console.log('main thread received a new message', event.data);
}

// Send message to service worker
navigator.serviceWorker.controller.postMessage({message: 'something'}, [messageChannel.port2]);
```

```js
// in ServiceWorker.js

// Listen for messages from service worker
this.addEventListener('message', event => {
  console.log('worker received a new message:', event.data)
  
  // Reply to message
  event.ports[0].postMessage('private msg back');
});
```