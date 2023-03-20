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
importScripts('vendor/exface/Core/Facades/AbstractPWAFacade/exfPWA.js');

// Handle OfflineActionSync Event
self.addEventListener('sync', function(event) {
    ...
});
-----------------------------------------------------------
 * 
 * @author Ralf Mulansky
 *
 */
 ;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.Dexie) :
    typeof define === 'function' && define.amd ? define(factory(global.Dexie)) :
    global.exfPWA = factory(global.Dexie)
}(this, (function (Dexie) {
	
	var _error = null;
		
	var _db = function() {
		var dexie = new Dexie('exf-offline');
		dexie.version(1).stores({
            'offlineData': 'uid, object_alias',
            'offlineModel': 'url',
            'actionQueue': '&id, object, action',
            'deviceId': 'id'
		});
		dexie.open().catch(function (e) {
			_error = e;
		    console.error("PWA error: " + e.stack);
		});
		return dexie;
	}();
	
	var _deviceId;
	var _queueTopics = ['offlineTask'];
	
	if (_error === null) {
		var _dataTable = _db.table('offlineData');
		var _modelTable = _db.table('offlineModel');
		var _actionsTable = _db.table('actionQueue');
		var _deviceIdTable = _db.table('deviceId');
	}
	
	(function() {
		_deviceIdTable.toArray()
		.then(function(data) {
			if (data.length !== 0) {
				_deviceId = data[0].id;
			} else {
				_deviceId = _pwa.createUniqueId();
				_deviceIdTable.put({
					id: _deviceId
				});
			}
		})
	})();
	
	var _pwa = {
		
		/**
		 * @return {bool}
		 */
		isAvailable : function() {
			return _error === null;
		},
		
		/**
		 * @return {string}
		 */
		getDeviceId : function() {
			return _deviceId;
		},
		
		/**
		 * @return {string}
		 */
		createUniqueId : function (a = "", b = false) {
		    const c = Date.now()/1000;
		    let d = c.toString(16).split(".").join("");
		    while(d.length < 14) d += "0";
		    let e = "";
		    if(b){
		        e = ".";
		        e += Math.round(Math.random()*100000000);
		    }
		    return a + d + e;
		},
		
		/**
		 * @return void
		 */
		download : function (data, filename, type) {
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
		},
		
		/**
		 * @return {promise}
		 */
		syncAll : async function(fnCallback) {
			var deferreds = [];
			var data = await _dataTable.toArray();		
			data.forEach(function(oDataSet){
				deferreds.push(
			    	_pwa
			    	.data.sync(oDataSet.uid)
			    );
			});
			// Can't pass a literal array, so use apply.
			//return $.when.apply($, deferreds)
			return Promise
			.all(deferreds)
			.then(function() {
				if (_error) {
					return Promise.resolve();
				}
				//delete all actions with status "synced" from actionQueue
				return _pwa
				.actionQueue
				.get('synced')
				.then(function(data) {
					data.forEach(function(item) {
						_actionsTable.delete(item.id);
					})
				})
			})
			.then(function(){
				if (fnCallback !== undefined) {
					fnCallback();
				}
			});
		},
		
		/**
		 * @return {promise}
		 */
		reset : function() {
			if (_error) {
				return Promise.resolve(null);
			}
			return _dataTable
			.clear()
			.then(function(){
				var aPromises = [];
				return _modelTable
				.toArray()
				.then(function(aRows) {
					aRows.forEach(function(oPWA){
						aPromises.push(_pwa.model.sync(oPWA.url));
					});
					return Promise.all(aPromises);
				});
			})
		},
		
		actionQueue: {
		
			/**
			 * @return void
			 */
			setTopics : function(aTopics) {
				_queueTopics = aTopics;
				return;
			},
		
			/**
			 * @return {string[]}
			 */
			getTopics : function() {
				return _queueTopics;
			},
			
			/**
			 * Adds an action to the offline action queue
			 * 
			 * @param {object} 	[offlineAction]
			 * @param {string} 	[objectAlias]
			 * @param {string} 	[sActionName]
			 * @param {string} 	[sObjectName]
			 * @param {Array.<{
			 * 			name: String,
			 * 			effected_object_alias: String
			 * 			effected_object_uid: String
			 * 			effected_object_key_alias: String 
			 * 			key_column: String, 
			 * 			key_values: Array
			 * 		  }>} 		[aEffects]
			 * @return Promise
			 */
			add : function(offlineAction, objectAlias, sActionName, sObjectName, aEffects) {
				if (_error) {
					return Promise.resolve(null);
				}
				var topics = _pwa.actionQueue.getTopics();
				var date = (+ new Date());
				var data = {
					id: _pwa.createUniqueId(),
					object: objectAlias,
					action: offlineAction.data.action,
					request: offlineAction,
					triggered: offlineAction.data.assignedOn,
					status: 'offline',
					tries: 0,
					synced: 'not synced',
					action_name: (sActionName || null),
					object_name: (sObjectName || null),
					effects: (aEffects || [])
				};
				offlineAction.url = 'api/task/' + topics.join('/');
				offlineAction.data.assignedOn = new Date(date).toLocaleString()
				if (offlineAction.headers) {
					data.headers = offlineAction.headers
				}
				return _actionsTable.put(data)
				.then(function(){
					if (navigator.serviceWorker) {
						navigator.serviceWorker.ready
						.then(registration => registration.sync.register('OfflineActionSync'))
						//.then(() => console.log("Registered background sync"))
						.catch(err => console.error("Error registering background sync", err))
					}
				});
			},
		
			/**
			 * Returns a promise that resolves to an array of offline action queue items optionally 
			 * filtered by status, action object alias and a filter callback for the action's input 
			 * data rows.
			 * 
			 * @param {string} [sStatus]
			 * @param {string} [sObjectAlias]
			 * @param {function} [sObjectAlias]
			 * @return {promise}
			 */
			get : function(sStatus, sObjectAlias, fnRowFilter) {
				if (_error) {
					return Promise.resolve([]);
				}
				return _actionsTable.toArray()
				.then(function(dbContent) {
					var data = [];
					dbContent.forEach(function(element) {
						//if an element got stuck in the proccessing state, check here if that sync attempt was already more than 5 minutes ago, if so, change the state of that element to offline again
						element = _pwa.actionQueue.updateState(element);
						
						if (sStatus && element.status != sStatus) {
							return;
						}
						if (sObjectAlias && element.object != sObjectAlias) {
							return;
						}	
						if (fnRowFilter) {
							if (element.request === undefined 
								|| element.request.data === undefined 
								|| element.request.data.data === undefined 
								|| element.request.data.data.rows === undefined
							) {
								return;
							}
							
							if (element.request.data.data.rows.filter(fnRowFilter).length === 0) {
								return;
							}
						}
						data.push(element);
					});
					return Promise.resolve(data);
				})
				.catch(function(error) {
					console.warn(error);
					return Promise.resolve([]);
				})
			},
		
			/**
			 * Returns the effects of different actions on the given object alias.
			 * 
			 * Each effect is an object as provided in action.add() with one additional property:
			 * `offline_queue_item` containing the entire action item the effect belongs to.
			 * 
			 * @param {String} [sEffectedObjectAlias]
			 * @return {Array.<{
			 * 			name: String,
			 * 			effected_object_alias: String
			 * 			effected_object_uid: String
			 * 			effected_object_key_alias: String 
			 * 			key_column: String, 
			 * 			key_values: Array
			 * 			offline_queue_item: Object
			 * 		  }>}
			 */
			getEffects : async function(sEffectedObjectAlias) {
				if (_error) {
					return [];
				}
				var dbContent = await _actionsTable.toArray();
				var aEffects = [];
				dbContent.forEach(function(oQueueItem) {
					if (oQueueItem.status !== 'offline' ) {
						return;
					}
					if (oQueueItem.request === undefined || oQueueItem.request.data === undefined) {
						return;
					}
					if (oQueueItem.effects === undefined) {
						return;
					}
					oQueueItem.effects.forEach(function(oEffect){
						if(oEffect.effected_object_alias === sEffectedObjectAlias) {
							oEffect.offline_queue_item = oQueueItem;
							aEffects.push(oEffect);
						}
					})
				})
				return aEffects;
			},
		
			/**
			 * @return {promise}
			 */
			getIds : function(filter) {
				if (_error) {
					return Promise.resolve([]);
				}
				return _actionsTable.toArray()
				.then(function(dbContent) {
					var ids = [];
					dbContent.forEach(function(element) {
						//if an element got stuck in the proccessing state, check here if that sync attempt was already more than 5 minutes ago, if so, change the state of that element to offline again
						element = _pwa.actionQueue.updateState(element);
						
						if (element.status != filter) {
							return;
						}
						ids.push(element.id);
						return;
					})
					return ids;
				})
				.catch(function(error) {
					return Promise.resolve([]);
				})
			},
		
			/**
			 * If element is in proccessing state and last sync attempt was more than 5 minutes ago, change it's state to 'offline'
			 *
			 * @param {object} element
			 * @return {object}
			 */
			updateState : function(element) {
				if (element.status === 'proccessing' && element.lastSyncAttempt !== undefined && element.lastSyncAttempt + 3000 < (+ new Date())) {
					element.status = 'offline';		
					_actionsTable.update(element.id, element);
				}
				return element;
			},
		
			/**
			 * Synchronizes queue ites with provided ids
			 * 
			 * @param {array}
			 * @return {promise}
			 */
			syncIds : async function(selectedIds) {
				var result = true;
				var id = null;
				for (var i = 0; i < selectedIds.length; i++) {
					var id = selectedIds[i];		
					var result = await _pwa.actionQueue.sync(id);
					if (result === false) {
						break;
					}
				}
				await _pwa.data.syncAffectedByActions();
				if (result === false) {
					return Promise.reject("Syncing failed at action with id: " + id + ". Syncing aborted!");
				}
				return Promise.resolve('Success');
			},
			
			/**
			 * Synchronize actions performed offline since the last sync (those still in stats "offline")
			 *
			 * @return {promise}
			 */
			syncOffline : function() {
				return _pwa
				.actionQueue
				.getIds('offline')
				.then(function(ids){
					return _pwa.actionQueue.syncIds(ids)
				})
			},
		
			/**
			 * @return {promise}
			 */
			sync : async function(id) {
				var element = await _actionsTable.get(id);
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
				
				try {
					var response = await fetch(element.request.url, {
						method: element.request.type,
						headers: {
							'Content-Type': 'application/json; charset=UTF-8',
							'X-Request-ID': element.id,
							'X-Client-ID': _pwa.getDeviceId()
						},
						body: JSON.stringify(element.request.data)
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
				try {
					var data = await response.json();
				} catch (e) {
					// Do nothing here. It will result in an error because there is no data.
				}
				if (response.ok && data) {
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
			},
		
			/**
			 * @return {promise}
			 */
			deleteAll : function(selectedIds) {
				var promises = [];
				selectedIds.forEach(function(id){
					promises.push(_pwa.actionQueue.delete(id));
				});
				return Promise.all(promises);
			},
		
			/**
			 * @return {promise}
			 */
			delete : function(id) {
				return _actionsTable.delete(id)
			},
		
			/**
			 * @return {Dexie.Table}
			 */
			getTable : function() {
				return _actionsTable;
			},
			
			/**
			 * Returns items of the offline queue filtered by the given message ids.
			 * 
			 * @param {string[]} aIds
			 * @return {object[]} 
			 */
			getByIds : function(aMessageIds) {
				return _pwa.actionQueue.get('offline')
				.then(function(actionsData) {
					var data = {deviceId: _pwa.getDeviceId()};
					var selectedActions = [];
					actionsData.forEach(function(action) {
						if (aMessageIds.includes(action.id)) {
							selectedActions.push(action);
						}
					})
					data.actions = selectedActions;
					return data;
				})
			},
		}, // EOF actionQueue
		
		model: {
			addPWA : function(sUrl) {
				console.log('add PWA to sync ', sUrl);
				if (_error) {
					return Promise.resolve(null);
				}
				return _modelTable
				.get(sUrl)
				.then(oPWA => {
					if (oPWA === undefined) {
						oPWA = {
							url: sUrl
						};
						return _modelTable
						.put(oPWA)
						.then(function(){
							return Promise.resolve(oPWA);
						})
					} else {
						// TODO sync only in certain intervals?
						return Promise.resolve(oPWA);
					}
				})
				.then(function(oPWA){
					return _pwa.model.sync(oPWA.url);
				});
			},
			
			sync : function(sPwaUrl) {
				return _modelTable
				.get(sPwaUrl)
				.then(function(oRow) {
					var oPWA = oRow;
					return fetch('api/pwa/model/' + sPwaUrl, {
						method: 'GET',
					})
					.then((response) => response.json())
					.then(function(oModel){
						var aPromises = [];
						oPWA.sync_last = (+ new Date());
						$.extend(oPWA, oModel);
						aPromises.push(_modelTable.update(sPwaUrl, oPWA));
						oPWA.data_sets.forEach(function(oDataSet){
							oDataSet.pwa_uid = oPWA.uid;
							aPromises.push(
								_dataTable
								.get(oDataSet.uid)
								.then(function(oRow){
									if (oRow === undefined) {
										return _dataTable.put(oDataSet);
									} else {
										oDataSet = $.extend({}, oRow, oDataSet);
										return _dataTable.update(oDataSet.uid, oDataSet);
									}
								})
							)
						})
						return Promise
						.all(aPromises)
						.then(function(){
							return _pwa.data.syncAll(oPWA.uid);
						});
					})
				})
			},
		
			/**
			 * @return {Dexie.Table}
			 */
			getTable : function() {
				return _modelTable;
			}
		}, // EOF model
		
		data: {
			/**
			 * @return {promise}
			 */
			get : function(oQuery) {
				if (_error === false) {
					return Promise.resolve();
				}
				if (typeof oQuery === 'object') {
					switch (true) {
						case oQuery.widget_id && oQuery.page_alias:
							// TODO
							break;
						
					}
				} else {
					if (oQuery.startsWith('0x')) {
						return _dataTable.get(sDataSetUid);
					} else {
						return _dataTable.filter(function(oDataSet){
							return oDataSet.object_alias === oQuery;
						}).first();
					}
				}
			},
		
			syncAll : function(sPwaUid) {
				_dataTable
				.filter(function(oDataSet){
					return oDataSet.pwa_uid === sPwaUid;
				})
				.toArray(function(aSets){
					aPromises = [];
					aSets.forEach(function(oDataSet) {
						aPromises.push(_pwa.data.sync(oDataSet.uid));
					});
					return Promise.all(aPromises);
				})
			},
		
			sync : function (sDataSetUid) {
				var oDataSet;
				return _dataTable
				.get(sDataSetUid)
				.then(function(oRow){
					oDataSet = oRow;
					if (oRow === undefined) {
						Promise.reject('Faild syncing data set ' + sDataSetUid + ': data set not found!');
					}
					return fetch(oDataSet.url);
				})
				.then(function(oResponse) {
					return oResponse.json()
				})
				.then(function(oDataUpdate) {
					if (oDataUpdate.status === 'remove') {
						return _dataTable.delete(oDataUpdate.uid);
					}
					oDataSet.sync_last = (+ new Date());
					$.extend(oDataSet, oDataUpdate);
					return _dataTable.update(oDataSet.uid, oDataSet);
				})
			},
		
			/**
			 * @return {object[]}
			 */
			mergeRows : function (aOldRows, aNewRows, sUidAlias) {
				for (var i = 0; i < aNewRows.length; i++) {
					var rowUpdated = false;
					for (var j = 0; j < aOldRows.length; j++) {
						if (aNewRows[i][sUidAlias] == aOldRows[j][sUidAlias]) {
							aOldRows[j] = aNewRows[i];
							rowUpdated = true;
							break;
						}
					}
					//add Row to offline if it wasnt there before/wasnt updated
					if (rowUpdated === false) {
						aOldRows.push(aNewRows[i]);
					}
				}
				return aOldRows;
			},
		
			/**
			 * @return {promise|null}
			 */
			syncImages : function (aUrls, sCacheName = 'image-cache') {
				if (typeof window !== 'undefined') {
					var cachesApi = window.caches;
				} else {
					var cachesApi = caches;
				}
				//var cachesApi = window !== undefined ? window.caches : caches;
				if (cachesApi === undefined) {
					console.error('Cannot offline images: Cache API not supported by browser!');
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
			},
			
			/**
			 * @return {Dexie.Table}
			 */
			getTable : function() {
				return _dataTable;
			},
			
			/**
			 * @return {promise}
			 */
			syncAffectedByActions : async function() {
				var aDataSets = await _dataTable.toArray();
				var promises = []
				aDataSets.forEach(async function(oDataSet) {
					var syncedActions = await _pwa.actionQueue.get('synced', oDataSet.object);
					if (syncedActions.length === 0) {
						return;
					}
					var uidAlias = oDataSet.uidAlias;
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
						_pwa.sync(oDataSet, true, uidValues)
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
				
				//after preloads are updated, delete all actions with status 'synced' from the IndexedDB
				var syncedIds = await _pwa.actionQueue.getIds('synced');
				syncedIds.forEach(function(id){
					promises.push(
						_actionsTable.delete(id)
					)
				})
				return Promise.all(promises);		
			},
		
		}, // EOF data
		
		errors : {
			/**
			 * @return {object}
			 */
			sync : function() {
				if (_error) {
					return Promise.resolve({});
				}
				
				return fetch('api/pwa/errors/' + _pwa.getDeviceId(), {
					method: 'GET'
				})
				.then(function(response){
					if (response.ok) {
						return response.json();
					} else {
						return {};
					}
				})
				.catch(function(error){
					console.error('Cannot read sync errors from server:', error);
					return {};
				})
			}
		} // EOF errors
	} // EOF _pwa
	return _pwa;
})));