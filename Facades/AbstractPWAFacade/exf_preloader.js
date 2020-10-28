/*
 * Toolbox for offline capabilities in apps, e.g. saving data in IndexedDB, saving offline actions and syncing them with the server.
 * 
 * Dependencies:
 * - Dexie (IndexedDB wrapper)
 * 
 * Example in ServiceWorker.js
 * 
-----------------------------------------------------------
importScripts('exface/vendor/npm-asset/dexie/dist/dexie.min.js');
importScripts('vendor/exface/Core/Facades/AbstractPWAFacade/exf_preloader.js');

var sync = async function(){
	var ids = await exfPreloader.getActionQueueIds('offline')
	return exfPreloader.syncActionAll(ids);
}

self.addEventListener('sync', function(event) {
	console.log("sync event", event);
    if (event.tag === 'OfflineActionSync') {
		event.waitUntil(
			sync()
			.then(function(){
				console.log('all offline actions synced');
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage('Sync completed');
					});
				});
				return;
			})
			.catch(error => {
				console.log('Could not sync completely; scheduled for the next time', error);
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage('Sync failed: ' + error);
					});
				});
				return Promise.reject(error); // Alternatively, `return Promise.reject(error);`
			})
		)
    }
});
-----------------------------------------------------------
 * 
 * @author Ralf Mulansky
 *
 */
const exfPreloader = {};
(function(){
	
	var _preloader = this;
		
	var _db = function() {
		var dexie = new Dexie('exf-preload');
		dexie.version(1).stores({
			'preloads': 'id, object',
			'actionQueue': 'id, object, action',
			'deviceId': 'id'
		});
		dexie.open();
		return dexie;
	}();
	
	
	var _preloadTable = _db.table('preloads');
	var _actionsTable = _db.table('actionQueue');
	var _deviceIdTable = _db.table('deviceId');
	var _topics = ['offlineTask'];
	
	var _deviceId;
	
	(function() {
		_deviceIdTable.toArray()
		.then(function(data) {
			if (data.length !== 0) {
				_deviceId = data[0].id;
			} else {
				_deviceId = _preloader.createUniqueId();
				_deviceIdTable.put({id: _deviceId});
			}
		})
	}());
	
	/**
	 * @return string
	 */
	this.getDeviceId = function() {
		return _deviceId;
	}
	
	/**
	 * @return void
	 */
	this.setTopics = function(aTopics) {
		_topics = aTopics;
		return;
	}
	
	/**
	 * @return array
	 */
	this.getTopics = function() {
		return _topics;
	}
	
	/**
	 * @return exfPreloader
	 */
	this.addPreload = function(sAlias, aDataCols, aImageCols, sPageAlias, sWidgetId) {		
		_preloadTable
		.get(sAlias)
		.then(item => {
			var data = {
				id: sAlias,
				object: sAlias
			};
			
			if (aDataCols) { data.dataCols = aDataCols; }
			if (aImageCols) { data.imageCols = aImageCols; }
			if (sPageAlias) { data.page = sPageAlias; }
			if (sWidgetId) { data.widget = sWidgetId; }
			
			if (item === undefined) {
				_preloadTable.put(data);
			} else {
				_preloadTable.update(sAlias, data);
			}
		})
		return _preloader;
	};
	
	/**
	 * @return Promise
	 */
	this.getPreload = function(sAlias, sPageAlias, sWidgetId) {
		return _preloadTable.get(sAlias);
	};
	
	this.syncAll = function(fnCallback) {
		var deferreds = [];
		return _preloadTable.toArray()
		.then(data => {
			$.each(data, function(idx, item){
				deferreds.push(
			    	_preloader
			    	.sync(item.object, item.page, item.widget, item.imageCols)
			    );
			});
			// Can't pass a literal array, so use apply.
			return $.when.apply($, deferreds)
		})
	};
	
	/**
	 * @return jqXHR
	 */
	this.sync = function(sObjectAlias, sPageAlias, sWidgetId, aImageCols) {
		console.log('Syncing preload for object "' + sObjectAlias + '", widget "' + sWidgetId + '" on page "' + sPageAlias + '"');
		if (! sPageAlias || ! sWidgetId) {
			throw {"message": "Cannot sync preload for object " + sObjectAlias + ": incomplete preload configuration!"};
		}
		return $.ajax({
			type: 'POST',
			url: 'api/ui5',
			dataType: 'json',
			data: {
				action: 'exface.Core.ReadPreload',
				resource: sPageAlias,
				element: sWidgetId
			}
		})
		.then(
			function(data, textStatus, jqXHR) {
				var promises = [];
				promises.push(
					_preloadTable.update(sObjectAlias, {
						response: data,
						lastSync: (+ new Date())
					})
				);
				if (aImageCols && aImageCols.length > 0) {
					for (i in aImageCols) {
						var urls = data.rows.map(function(value,index) { return value[aImageCols[i]]; });
						promises.push(_preloader.syncImages(urls));
					}
				}
				return Promise.all(promises);
			},
			function(jqXHR, textStatus, errorThrown){
				console.error(jqXHR.status + " " + jqXHR.statusText);
				//exfLauncher.contextBar.getComponent().showAjaxErrorDialog(jqXHR);
				return textStatus;
			}
		);
	};
	
	/**
	 * @return Promise|NULL
	 */
	this.syncImages = function (aUrls, sCacheName = 'image-cache') {
		if (window.caches === undefined) {
			console.error('Cannot preload images: Cache API not supported by browser!');
			return;
		}
		
		return window.caches
		.open(sCacheName)
		.then(cache => {
			// Remove duplicates
			aUrls = aUrls.filter((value, index, self) => { 
			    return self.indexOf(value) === index;
			});
			// Fetch and cache images
			var requests = [];
			for (var i in aUrls) {
				if (! aUrls[i]) continue;
				var request = new Request(aUrls[i]);
				requests.push(
					fetch(request.clone())
					.then(response => {
						// Check if we received a valid response
						if(! response || response.status !== 200 || response.type !== 'basic') {
						  return response;
						}
						
						// IMPORTANT: Clone the response. A response is a stream
						// and because we want the browser to consume the response
						// as well as the cache consuming the response, we need
						// to clone it so we have two streams.
						var responseToCache = response.clone();
						
						return cache.put(request, responseToCache);
					})
				);
			}
			return Promise.all(requests);
		});
	};
	
	/**
	 * @return Promise
	 */
	this.reset = function() {
		return clear = _preloadTable.toArray()
		.then(function(dbContent) {
			var promises = [];
			dbContent.forEach(function(element) {
				promises.push(
					_preloadTable.update(element.id, {
						response: {},
						lastSync: 'not synced'
					})
				);
			});
			return Promise.all(promises);
		});
	};
	
	/**
	 * @return Promise
	 */
	this.addAction = function(offlineAction, objectAlias) {
		var topics = _preloader.getTopics();
		offlineAction.url = 'api/task/' + topics.join('/');
		var xRequestId = _preloader.createUniqueId();
		var date = (+ new Date());
		offlineAction.data.assignedOn = new Date(date).toLocaleString()
		var data = {
			id: _preloader.createUniqueId(),
			object: objectAlias,
			action: offlineAction.data.action,
			request: offlineAction,
			triggered: offlineAction.data.assignedOn,
			status: 'offline',
			tries: 0,
			synced: 'not snyced'
		};
		if (offlineAction.headers) {
			data.headers = offlineAction.headers
		}
		return _actionsTable.put(data)
		.then(function(){
			navigator.serviceWorker.ready
			.then(registration => registration.sync.register('OfflineActionSync'))
			.then(() => console.log("Registered background sync"))
			.catch(err => console.error("Error registering background sync", err))
		});
	};
	
	/**
	 * @return Promise
	 */
	this.getActionQueueData = function(filter) {
		return _actionsTable.toArray()
		.then(function(dbContent) {
			var data = [];
			dbContent.forEach(function(element) {
				if (element.status != filter) {
					return;
				}
				item = {
						id: element.id,
						action_alias: element.action,
						object: element.object,
						triggered: element.triggered,
						status: element.status,
						tries: element.tries
				}
				if (element.response) {
					item.response = element.response;
				}
				data.push(item);
				return;
			})
			return data;
		})
		.catch(function(error) {
			return [];
		})
	};
	
	/**
	 * @return Promise
	 */
	this.getActionQueueIds = function(filter) {
		return _actionsTable.toArray()
		.then(function(dbContent) {
			var ids = [];
			dbContent.forEach(function(element) {
				if (element.status != filter) {
					return;
				}
				ids.push(element.id);
				return;
			})
			return ids;
		})
		.catch(function(error) {
			return [];
		})
	};
	
	/**
	 * @return Promise
	 */
	this.syncActionAll = async function(selectedIds) {
		var result = true;
		var id = null;
		for (var i = 0; i < selectedIds.length; i++) {
			console.log('Sync Actions');
			var id = selectedIds[i];		
			var result = await _preloader.syncAction(id);
			if (result === false) {
				break;
			}
		}
		if (result === false) {
			return Promise.reject("Syncing failed at action with id: " + id + ". Syncing aborted!");
		}
		return Promise.resolve('Success');
	};
	
	/**
	 * @return Promise
	 */
	this.syncAction = async function(id) {
		element = await _actionsTable.get(id);
		if (element === undefined) {
			return false
		}
		console.log('Syncing action with id: ',element.id);
		var params = element.request.data;
		params = _preloader.encodeJson(params);
		try {
			var response = await fetch(element.request.url, {
				method: element.request.type,
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Request-ID': element.id,
					'X-Client-ID': _preloader.getDeviceId()
				},
				body: params
			})
		} catch (error) {
			console.error("Error sync action with id " + element.id + ". " + error.message);
			var updated = await _actionsTable.update(element.id, {
				tries: element.tries + 1,
				response: error.message
			});
			if (updated) {
				console.log ("Tries for Action with id " + element.id + " increased");
			} else {
				console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return false;			
		}
		var data = await response.json()
		if (response.ok) {
			var date = (+ new Date());
			//await _actionsTable.delete(element.id);
			//we update the entry now for test purposes, normally we delete it from the queue
			var updated = await _actionsTable.update(element.id, {
				status: 'success',
				tries: element.tries + 1,
				response: data,
				synced: new Date(date).toLocaleString()
			})				
			if (updated) {
				console.log ("Action with id " + element.id + " synced. Action removed from queue");
			} else {
				console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return true;
		}
		if (response.statusText === 'timeout' || response.status === 0) {
			console.log('Timeout syncing action with id: ' + element.id);
			var updated = _actionsTable.update(element.id, {
				tries: element.tries + 1,
				response: response.statusText
			});
			if (updated) {
				console.log ("Tries for Action with id " + element.id + " increased");
			} else {
				console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return false;
		}
		console.log('Server responded with an error syncing action with id: '+ element.id);
		//await _actionsTable.delete(element.id);
		//we update the entry now for test purposes, normally we delete it from the queue
		var updated = await _actionsTable.update(element.id, {
			status: 'error',
			tries: element.tries + 1,
			response: data
		});
		if (updated) {
			console.log ("Action with id " + element.id + " was updated");
		} else {
			console.log ("Nothing was updated - there was no action with id: ", element.id);
		}
		return false;		
	};
	
	/**
	 * @return String
	 */
	this.encodeJson = function(srcjson, parent=""){
		if(typeof srcjson !== "object")
		  if(typeof console !== "undefined"){
			console.log("\"srcjson\" is not a JSON object");
			return null;
		}
		let u = encodeURIComponent;
		let urljson = "";
		let keys = Object.keys(srcjson);

		for(let i=0; i < keys.length; i++){
		  let k = parent ? parent + "[" + keys[i] + "]" : keys[i];

		  if(typeof srcjson[keys[i]] !== "object"){
			urljson += u(k) + "=" + u(srcjson[keys[i]]);
		  } else {
			urljson += _preloader.encodeJson(srcjson[keys[i]], k)
		  }
		  if(i < (keys.length-1))urljson+="&";
		}

		return urljson;
	}
	
	/**
	 * @return Promise
	 */
	this.deleteActionAll = function(selectedIds) {
		var promises = [];
		selectedIds.forEach(function(id){
			promises.push(_preloader.deleteAction(id));
		});
		return Promise.all(promises);
	};
	
	/**
	 * @return Promise
	 */
	this.deleteAction = function(id) {
		return _actionsTable.delete(id)
	}
	
	/**
	 * @return Dexie.Table
	 */
	this.getActionsTable = function() {
		return _actionsTable;
	}
	
	/**
	 * @return Dexie.Table
	 */
	this.getPreloadTable = function() {
		return _preloadTable;
	}
	
	/**
	 * @return string
	 */
	this.createUniqueId = function (a = "", b = false) {
	    const c = Date.now()/1000;
	    let d = c.toString(16).split(".").join("");
	    while(d.length < 14) d += "0";
	    let e = "";
	    if(b){
	        e = ".";
	        e += Math.round(Math.random()*100000000);
	    }
	    return a + d + e;
	}
}).apply(exfPreloader);