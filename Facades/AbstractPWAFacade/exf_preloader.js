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

// Handle OfflineActionSync Event
self.addEventListener('sync', function(event) {
    if (event.tag === 'OfflineActionSync') {
		event.waitUntil(
			exfPreloader.getActionQueueIds('offline')
			.then(function(ids){
				return exfPreloader.syncActionAll(ids)
			})
			.then(function(){
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage('Sync completed');
					});
				});
				return;
			})
			.catch(error => {
				console.error('Could not sync completely; scheduled for the next time.', error);
				self.clients.matchAll()
				.then(function(all) {
					all.forEach(function(client) {
						client.postMessage(error);
					});
				});
				return Promise.reject(error);
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
			'preloads': 'id, object'
		});
		dexie.version(2).stores({
            'preloads': 'id, object',
            'actionQueue': '&id, object, action',
            'deviceId': 'id'
		});
		dexie.open().catch(function (e) {
		    console.error("Preloader error: " + e.stack);
		});
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
	this.addPreload = function(sAlias, aDataCols, aImageCols, sPageAlias, sWidgetId, sUidAlias) {		
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
			if (sUidAlias) {data.uidAlias = sUidAlias}
			
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
	
	/**
	 * @return Promise
	 */
	this.syncAll = async function(fnCallback) {
		var deferreds = [];
		var data = await _preloadTable.toArray();		
		data.forEach(function(item){
			deferreds.push(
		    	_preloader
		    	.sync(item, true)
		    );
		});
		// Can't pass a literal array, so use apply.
		//return $.when.apply($, deferreds)
		return Promise.all(deferreds)
		.then(function() {
			//delete all actions with status "synced" from actionQueue
			_preloader.getActionQueueData('synced')
			.then(function(data) {
				data.forEach(function(item) {
					_actionsTable.delete(item.id);
				})
			})
		});
	};
	
	/**
	 * @return Promise
	 */
	this.sync = async function(item, bSyncImages, aUid) {
		var sObjectAlias = item.object;
		var sPageAlias = item.page;
		var sWidgetId = item.widget;
		var aImageCols = item.imageCols;
		var sUidAlias = item.uidAlias
		aUid
		//console.log('Syncing preload for object "' + sObjectAlias + '", widget "' + sWidgetId + '" on page "' + sPageAlias + '"');
		if (! sPageAlias || ! sWidgetId) {
			throw {"message": "Cannot sync preload for object " + sObjectAlias + ": incomplete preload configuration!"};
		}
		var requestData = {
				action: 'exface.Core.ReadPreload',
				resource: sPageAlias,
				element: sWidgetId
			};
		if (aUid && sUidAlias) {
			var filters = {
					operator: "OR"
			}
			conditions = []
			aUid.forEach(function(sUid) {
				var cond = {
						expression: sUidAlias,
						comparator: "==",
						value: sUid,
						object_alias: sObjectAlias
				}
				conditions.push(cond);
			})
			filters.conditions = conditions;
			requestData.data = {
					oId: sObjectAlias,
					filters: filters
					};
		}
		try {			
			var response = await fetch('api/ui5?' + encodeURI(_preloader.getUrlString(requestData)), {
				method: 'GET',
			})
		} catch(error) {
			console.error(error);
			return Promise.reject(error.message);
		}
		if (!response.ok) {
			return Promise.reject('Fetch failed for object ' + sObjectAlias);
		} else {
			var promises = [];
			responseData = await response.json()
			var saveData = responseData;
			if (requestData.data !== undefined && requestData.data.filters !== undefined && item.response !== undefined && item.response.rows !== undefined) {
				saveData.rows = _preloader.mergeRows(item.response.rows, responseData.rows, sUidAlias);
			}
			if (requestData.data !== undefined && requestData.data.filters !== undefined && (item.response === undefined || item.response.rows === undefined)) {
				saveData = {};
			}			
			promises.push(
				_preloadTable.update(sObjectAlias, {
					response: saveData,
					lastSync: (+ new Date())
				})
			);
			if (bSyncImages === true && aImageCols && aImageCols.length > 0) {
				for (i in aImageCols) {
					var urls = responseData.rows.map(function(value,index) { return value[aImageCols[i]]; });
					promises.push(_preloader.syncImages(urls));
				}
			}
			return Promise.all(promises);
		}
	};
	
	/**
	 * @return array
	 */
	this.mergeRows = function (aOldRows, aNewRows, sUidAlias) {
		for (var i = 0; i < aNewRows.length; i++) {
			var rowUpdated = false;
			for (var j = 0; j < aOldRows.length; j++) {
				if (aNewRows[i][sUidAlias] == aOldRows[j][sUidAlias]) {
					aOldRows[j] = aNewRows[i];
					rowUpdated = true;
					break;
				}
			}
			//add Row to preload if it wasnt there before/wasnt updated
			if (rowUpdated === false) {
				aOldRows.push(aNewRows[i]);
			}
		}
		return aOldRows;
	}
	
	/**
	 * @return Promise|NULL
	 */
	this.syncImages = function (aUrls, sCacheName = 'image-cache') {
		if (typeof window !== 'undefined') {
			var cachesApi = window.caches;
		} else {
			var cachesApi = caches;
		}
		//var cachesApi = window !== undefined ? window.caches : caches;
		if (cachesApi === undefined) {
			console.error('Cannot preload images: Cache API not supported by browser!');
			return;
		}
		
		return cachesApi
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
			synced: 'not synced'
		};
		if (offlineAction.headers) {
			data.headers = offlineAction.headers
		}
		return _actionsTable.put(data)
		.then(function(){
			navigator.serviceWorker.ready
			.then(registration => registration.sync.register('OfflineActionSync'))
			//.then(() => console.log("Registered background sync"))
			.catch(err => console.error("Error registering background sync", err))
		});
	};
	
	/**
	 * @return Promise
	 */
	this.getActionQueueData = function(status, objectAlias) {
		return _actionsTable.toArray()
		.then(function(dbContent) {
			var data = [];
			dbContent.forEach(function(element) {
				//if an element got stuck in the proccessing state, check here if that sync attempt was already more than 5 minutes ago, if so, change the state of that element to offline again
				element = _preloader.updateProccessingState(element);
				
				if (status && element.status != status) {
					return;
				}
				if (objectAlias && element.object != objectAlias) {
					return;
				}				
				data.push(element);
				return;
			})
			return data;
		})
		.catch(function(error) {
			return [];
		})
	};
	
	/**
	 * @return array
	 */
	this.getActionObjectData = async function(objectUid) {
		var dbContent = await _actionsTable.toArray();
		var actionRows = [];
		dbContent.forEach(function(element) {
			if (element.status !== 'offline' ) {
				return;
			}
			if (element.request == undefined && element.request.data == undefined && element.request.data.object !== objectUid) {
				return;
			}
			if (element.request.data.data == undefined && element.request.data.data.rows == undefined) {
				return;
			}
			element.request.data.data.rows.forEach(function(row) {
				actionRows.push(row);	
			})
		})
		return actionRows;
	}
	
	/**
	 * @return Promise
	 */
	this.getActionQueueIds = function(filter) {
		return _actionsTable.toArray()
		.then(function(dbContent) {
			var ids = [];
			dbContent.forEach(function(element) {
				//if an element got stuck in the proccessing state, check here if that sync attempt was already more than 5 minutes ago, if so, change the state of that element to offline again
				element = _preloader.updateProccessingState(element);
				
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
	 * If element is in proccessing state and last sync attempt was more than 5 minutes ago, change it's state to 'offline'
	 * @return object
	 */
	this.updateProccessingState = function(element) {
		if (element.status === 'proccessing' && element.lastSyncAttempt !== undefined && element.lastSyncAttempt + 3000 < (+ new Date())) {
			element.status = 'offline';		
			_actionsTable.update(element.id, element);
		}
		return element;
	}
	
	/**
	 * @return Promise
	 */
	this.syncActionAll = async function(selectedIds) {
		var result = true;
		var id = null;
		for (var i = 0; i < selectedIds.length; i++) {
			var id = selectedIds[i];		
			var result = await _preloader.syncAction(id);
			if (result === false) {
				break;
			}
		}
		await _preloader.updatePreloadData();
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
		//if item was already synced or tried to synced since it was added to list of items to be synced, skip it, continue with next one
		if (element.status !== 'offline' && element.satus !== 'proccessing') {
			return true
		}
		//if item is in the proccess of syncing or the last try is fewer than 5 minutes ago and still ongoing, skip it
		if (element.status === 'proccessing' && element.lastSync !== undefined && element.lastSyncAttempt + 3000 > (+ new Date())) {
			return true
		}
		
		// update Element so it has the processing state, therefor no other sync Attempt tries to sync it aswell.
		var updatedElement = {
				lastSyncAttempt: (+ new Date()),
				status: 'proccessing',
				tries: element.tries + 1
		};		
		var updated = await _actionsTable.update(element.id, updatedElement);
		
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
				response: error.message,
				status: 'offline'
			});
			if (updated) {
				//console.log ("Tries for Action with id " + element.id + " increased");
			} else {
				//console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return false;			
		}
		var data = await response.json()
		if (response.ok) {
			var date = (+ new Date());
			updatedElement.status = 'synced';
			updatedElement.response = data;
			updatedElement.synced = new Date(date).toLocaleString();
			var updated = await _actionsTable.update(element.id, updatedElement);			
			if (updated) {
				//console.log ("Action with id " + element.id + " synced. Action removed from queue");
			} else {
				//console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return true;
		}
		if (response.statusText === 'timeout' || response.status === 0) {
			//console.log('Timeout syncing action with id: ' + element.id);
			updatedElement.response = response.statusText;
			updatedElement.status = 'offline';
			var updated = _actionsTable.update(element.id, updatedElement);
			if (updated) {
				//console.log ("Tries for Action with id " + element.id + " increased");
			} else {
				//console.log ("Nothing was updated - there was no action with id: ", element.id);
			}
			return false;
		}
		console.log('Server responded with an error syncing action with id: '+ element.id);
		//await _actionsTable.delete(element.id);
		//update the entry now for test purposes, normally it gets deleted from the queue
		updatedElement.status = 'error';
		updatedElement.response = data;
		var updated = await _actionsTable.update(element.id, updatedElement);
		if (updated) {
			//console.log ("Action with id " + element.id + " was updated");
		} else {
			//console.log ("Nothing was updated - there was no action with id: ", element.id);
		}
		return false;		
	};
	
	/**
	 * @return String
	 */
	this.encodeJson = function(srcjson, parent=""){
		if(typeof srcjson !== "object")
		  if(typeof console !== "undefined"){
			//console.log("\"srcjson\" is not a JSON object");
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
	this.getUrlString = function(params, keys = [], isArray = false) {
		  const p = Object.keys(params).map(key => {
		    let val = params[key]

		    if ("[object Object]" === Object.prototype.toString.call(val) || Array.isArray(val)) {
		      if (Array.isArray(params)) {
		        keys.push(key)
		      } else {
		        keys.push(key)
		      }
		      return _preloader.getUrlString(val, keys, Array.isArray(val))
		    } else {
		      let tKey = key

		      if (keys.length > 0) {
		        const tKeys = isArray ? keys : [...keys, key]
		        tKey = tKeys.reduce((str, k) => { return "" === str ? k : `${str}[${k}]` }, "")
		      }
		      if (isArray) {
		        return `${ tKey }[]=${ val }`
		      } else {
		        return `${ tKey }=${ val }`
		      }

		    }
		  }).join('&')

		  keys.pop()
		  return p
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
	
	/**
	 * @return Promise
	 */
	this.updatePreloadData = async function() {
		var preloads = await _preloadTable.toArray();
		/*if (preloads.length == 0) {
			return;
		}*/
		var promises = []
		preloads.forEach(async function(preloadItem) {
			var syncedActions = await _preloader.getActionQueueData('synced', preloadItem.object);
			if (syncedActions.length === 0) {
				return;
			}
			var uidAlias = preloadItem.uidAlias;
			if (!uidAlias) {
				return;
			}
			var uidValues = [];
			syncedActions.forEach(function(action) {
				if (!(action.request && action.request.data && action.request.data.data && action.request.data.data.rows)) {
					return;
				}
				action.request.data.data.rows.forEach(function(row) {
					uidValues.push(row[uidAlias]);
				})								
			})			
			promises.push(
				_preloader.sync(preloadItem, true, uidValues)
				.catch (function(error){
					console.error(error);
				})
				/*.then(function() {
					syncedActions.forEach(function(action) {
						_actionsTable.delete(action.id);
					})
				}, function(error){
					console.error(error);
				})*/
			)		
		})
		
		//after preloads are updated, delete all actiosn with status 'synced' from the IndexedDB
		var syncedIds = await _preloader.getActionQueueIds('synced');
		syncedIds.forEach(function(id){
			promises.push(
				_actionsTable.delete(id)
			)
		})
		return Promise.all(promises);		
	}
	
	/**
	 * @return object
	 */
	this.loadErrorData = function() {
		var body = {
			action: "exface.Core.ReadData",
			resource: "0x11ebaf708ef99298af708c04ba002958",
			object: "0x11ea8f3c9ff2c5e68f3c8c04ba002958",
			element: "TaskQueue_table",
			sort: "TASK_ASSIGNED_ON",
			order: "asc",
			data: {
				oId: "0x11ea8f3c9ff2c5e68f3c8c04ba002958",
				filters: {
					operator: "AND",
					conditions:  [
						{
							expression: "STATUS",
							comparator: "==",
							value: "70",
							object_alias: "exface.Core.QUEUED_TASK"
						},{
							expression: "PRODUCER",
							comparator: "==",
							value: exfPreloader.getDeviceId(),
							object_alias: "exface.Core.QUEUED_TASK"
						}
					]
				}
			}
		};
		
		return fetch('api/ui5?' + $.param(body), {
			method: 'GET'
		})
		.then(function(response){
			if (response.ok) {
				return response.json();
			}
			else {
				return {};
			}
		})
		.catch(function(error){
			console.error('Cannot read sync errors from server:', error);
			return {};
		})
	}
	
	/**
	 * 
	 * @param aIds
	 * @return array 
	 */
	this.getActionsData = function(aIds) {		
		return _preloader.getActionQueueData('offline')
		.then(function(actionsData) {
			var data = {deviceId: _preloader.getDeviceId()};
			var selectedActions = [];
			actionsData.forEach(function(action) {
				if (aIds.includes(action.id)) {
					selectedActions.push(action);
				}
			})
			data.actions = selectedActions;
			return data;
		})
		
	}
	
	/**
	 * @return void
	 */
	this.download = function (data, filename, type) {
	    var file = new Blob([data], {type: type});
	    if (window.navigator.msSaveOrOpenBlob) // IE10+
	        window.navigator.msSaveOrOpenBlob(file, filename);
	    else { // Others
	        var a = document.createElement("a"),
	                url = URL.createObjectURL(file);
	        a.href = url;
	        a.download = filename;
	        document.body.appendChild(a);
	        a.click();
	        setTimeout(function() {
	            document.body.removeChild(a);
	            window.URL.revokeObjectURL(url);  
	        }, 0); 
	    }
	    return;
	}
}).apply(exfPreloader);