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
; (function (global, factory) {
	typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.Dexie, global.$) :
		typeof define === 'function' && define.amd ? define(factory(global.Dexie, global.$)) :
			global.exfPWA = factory(global.Dexie, global.$)
}(this, (function (Dexie, $) {

	var _error = null;

	var _db = function () {
		var dexie = new Dexie('exf-offline');
		dexie.version(1).stores({
			'offlineData': 'uid, object_alias',
			'offlineModel': 'url',
			'actionQueue': '&id, object, action',
			'deviceId': 'id'
		});
		dexie.version(2).stores({
			'offlineData': 'uid, object_alias',
			'offlineModel': 'url',
			'actionQueue': '&id, object, action',
			'deviceId': 'id',
			'networkStat': 'time',
			'connection': 'time, status'
		});
		dexie.version(3).stores({
			'offlineData': 'uid, object_alias',
			'offlineModel': 'url',
			'actionQueue': '&id, object, action',
			'deviceId': 'id',
			'networkStat': 'time, speed, mime_type, size',
			'connection': 'time, status',
			'autoOfflineToggle': '&id, status' 
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
		var _networkStatTable = _db.table('networkStat');
		var _connectionTable = _db.table('connection');
		var _autoOfflineToggle = _db.table('autoOfflineToggle');
	}

	(function () {
		_deviceIdTable.toArray()
			.then(function (data) {
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

	var _merge = function mergeObjects(target, ...sources) {
		// The last argument may be an array containing excludes
		// Restore it if it is not an array!
		var aIgnoredProps = sources.pop();
		if (!Array.isArray(aIgnoredProps)) {
			sources.push(aIgnoredProps);
			aIgnoredProps = [];
		}
		sources.forEach((source) => {
			Object.keys(source).forEach((key) => {
				if (!aIgnoredProps.includes(key)) {
					target[key] = source[key];
				}
			});
		});
		return target;
	};

	var _deepMerge = function mergeObjects(target, ...sources) {
		// The last argument may be an array containing excludes
		// Restore it if it is not an array!
		var aIgnoredProps = sources.pop();
		if (!Array.isArray(aIgnoredProps)) {
			sources.push(aIgnoredProps);
			aIgnoredProps = [];
		}
		sources.forEach((source) => {
			Object.keys(source).forEach((key) => {
				if (aIgnoredProps.includes(key)) {
					return;
				}

				if (Array.isArray(target[key]) && Array.isArray(source[key]) && key === 'rows') {
					let targetArray = target[key];
					let sourceArray = source[key];


					let newEntries = [];
					for (let i = 0; i < sourceArray.length; i++) {
						let foundElementIndex = Array.prototype.findIndex.call(
							targetArray,
							(entry) => entry[target.uid_column_name] === sourceArray[i][source.uid_column_name]);
						if (foundElementIndex) {
							targetArray[foundElementIndex] = sourceArray[i];
						} else {
							// needs to be added
							newEntries.push(sourceArray[i]);
						}
					}

					// add remaining entries
					if (newEntries.length > 0) {
						targetArray.concat(newEntries);
					}

					target[key] = targetArray;
				}
				else {
					target[key] = source[key];
				}
			});
		});
		return target;
	};

	var getIdentifier = function (identifiers, sourceObject) {
		for (let i = 0; i < identifiers.length; i++) {
			if (Object.keys(sourceObject).includes(identifiers[i])) {
				return identifiers[i];
			}
		}
	};

	var _date = {
		now: function () {
			return _date.normalize(new Date());
		},
		timestamp: function () {
			return Date.now();
		},
		normalize: function (d) {
			var fnPad = function (n, width, z) {
				z = z || '0';
				n = n + '';
				return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
			}
			return d.getFullYear() + "-" + fnPad((d.getMonth() + 1), 2) + "-" + fnPad(d.getDate(), 2) + " " + fnPad(d.getHours(), 2) + ":" + fnPad(d.getMinutes(), 2);
		}
	};

	function checkIndexedDB() {
		return new Promise((resolve, reject) => {
			resolve(true);
		});
	};

	var _pwa = {

		/**
		 * @return {bool}
		 */
		isAvailable: function () {
			return _error === null;
		},

		/**
		 * @return {string}
		 */
		getDeviceId: function () {
			return _deviceId;
		},

		/**
		 * @return {string}
		 */
		createUniqueId: function (a = "", b = false) {
			const c = _date.timestamp() / 1000;
			let d = c.toString(16).split(".").join("");
			while (d.length < 14) d += "0";
			let e = "";
			if (b) {
				e = ".";
				e += Math.round(Math.random() * 100000000);
			}
			return a + d + e;
		},

		/**
		 * @return void
		 */
		download: function (data, filename, type) {
			var file = new Blob([data], { type: type });
			if (window.navigator.msSaveOrOpenBlob) // IE10+
				window.navigator.msSaveOrOpenBlob(file, filename);
			else { // Others
				var a = document.createElement("a"),
					url = URL.createObjectURL(file);
				a.href = url;
				a.download = filename;
				document.body.appendChild(a);
				a.click();
				setTimeout(function () {
					document.body.removeChild(a);
					window.URL.revokeObjectURL(url);
				}, 0);
			}
			return;
		},

		/**
		 * @return {promise}
		 */
		syncAll: async function ({ fnCallback = () => { }, doReSync = false } = {}) {
			var deferreds = [];
			var data = await _dataTable.toArray();
			data.forEach(function (oDataSet) {
				deferreds.push(
					_pwa
						.data.sync(oDataSet.uid, doReSync)
				);
			});
			// Can't pass a literal array, so use apply.
			//return $.when.apply($, deferreds)
			return Promise
				.all(deferreds)
				.then(function () {
					if (_error) {
						return Promise.resolve();
					}
					//delete all actions with status "synced" from actionQueue
					return _pwa
						.actionQueue
						.get('synced')
						.then(function (data) {
							data.forEach(function (item) {
								_actionsTable.delete(item.id);
							})
						})
				})
				.then(function () {
					if (fnCallback !== undefined) {
						fnCallback();
					}
				});
		},

		/**
		 * @return {promise}
		 */
		reset: function () {
			if (_error) {
				return Promise.resolve(null);
			}
			return _dataTable
				.clear()
				.then(function () {
					var aPromises = [];
					return _modelTable
						.toArray()
						.then(function (aRows) {
							aRows.forEach(function (oPWA) {
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
			setTopics: function (aTopics) {
				_queueTopics = aTopics;
				return;
			},

			/**
			 * @return {string[]}
			 */
			getTopics: function () {
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
			 * 			key_values: Array,
			 *          event_params: Array
			 * 		  }>} 		[aEffects]
			 * @return Promise
			 */
			add: function (offlineAction, objectAlias, sActionName, sObjectName, aEffects, sOfflineDataEffect, bSyncNow = true) {
				if (_error) {
					return Promise.resolve(null);
				}
				var topics = _pwa.actionQueue.getTopics();
				var sDate = _date.now();
				var oQueueItem = {
					id: _pwa.createUniqueId(),
					object: objectAlias,
					action: offlineAction.data.action,
					request: offlineAction,
					triggered: sDate,
					status: 'offline',
					tries: 0,
					synced: 'not synced',
					action_name: (sActionName || null),
					object_name: (sObjectName || null),
					effects: (aEffects || [])
				};
				offlineAction.url = 'api/task/' + topics.join('/');
				offlineAction.data.assignedOn = sDate;
				if (offlineAction.headers) {
					oQueueItem.headers = offlineAction.headers
				}
				return _actionsTable.put(oQueueItem)
					.then(function () {
						if (navigator.serviceWorker && bSyncNow) {
							navigator.serviceWorker.ready
								.then(registration => registration.sync.register('OfflineActionSync'))
								//.then(() => console.log("Registered background sync"))
								.catch(err => console.error("Error registering background sync", err))
						}
					})
					.then(function () {
						return _pwa.data.applyAction(oQueueItem, sOfflineDataEffect);
					})
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
			get: function (sStatus, sObjectAlias, fnRowFilter) {
				if (_error) {
					return Promise.resolve([]);
				}
				return _actionsTable.toArray()
					.then(function (dbContent) {
						var data = [];
						dbContent.forEach(function (element) {
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
					.catch(function (error) {
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
			getEffects: async function (sEffectedObjectAlias) {
				if (_error) {
					return [];
				}
				var dbContent = await _actionsTable.toArray();
				var aEffects = [];
				dbContent.forEach(function (oQueueItem) {
					if (oQueueItem.status !== 'offline') {
						return;
					}
					if (oQueueItem.request === undefined || oQueueItem.request.data === undefined) {
						return;
					}
					if (oQueueItem.effects === undefined) {
						return;
					}
					oQueueItem.effects.forEach(function (oEffect) {
						if (oEffect.effected_object_alias === sEffectedObjectAlias) {
							oEffect.offline_queue_item = oQueueItem;
							aEffects.push(oEffect);
						}
					})
				})
				return aEffects;
			},

			/**
			 * Returns the array of data rows from a give action queue item
			 * 
			 */
			getRequestDataRows(oQueueItem) {
				if (!(oQueueItem.request && oQueueItem.request.data && oQueueItem.request.data.data && oQueueItem.request.data.data.rows)) {
					return [];
				}
				return oQueueItem.request.data.data.rows;
			},

			/**
			 * @return {promise}
			 */
			getIds: function (filter) {
				if (_error) {
					return Promise.resolve([]);
				}
				return _actionsTable.toArray()
					.then(function (dbContent) {
						var ids = [];
						dbContent.forEach(function (element) {
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
					.catch(function (error) {
						return Promise.resolve([]);
					})
			},

			/**
			 * If element is in proccessing state and last sync attempt was more than 5 minutes ago, change it's state to 'offline'
			 *
			 * @param {object} element
			 * @return {object}
			 */
			updateState: function (element) {
				if (element.status === 'proccessing' && element.lastSyncAttempt !== undefined && element.lastSyncAttempt + 3000 < _date.timestamp()) {
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
			syncIds: async function (selectedIds) {
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
			syncOffline: function () {
				return _pwa
					.actionQueue
					.getIds('offline')
					.then(function (ids) {
						return _pwa.actionQueue.syncIds(ids)
					})
					// TODO do not sync all every time - instead improve action effects to sync only
					// data sets, that might have bee effected. E.g. need to sync subsheet objects
					// if action had subsheets
					.then(function () {
						return _pwa.data.syncAll();
					})
			},

			/**
			 * @return {promise}
			 */
			sync: async function (id) {
				var oQItem = await _actionsTable.get(id);
				if (oQItem === undefined) {
					return false
				}
				//if item was already synced or tried to synced since it was added to list of items to be synced, skip it, continue with next one
				if (oQItem.status !== 'offline' && oQItem.status !== 'proccessing') {
					return true
				}
				//if item is in the proccess of syncing or the last try is fewer than 5 minutes ago and still ongoing, skip it
				if (oQItem.status === 'proccessing' && oQItem.lastSync !== undefined && oQItem.lastSyncAttempt + 3000 > _date.timestamp()) {
					return true
				}

				// update Element so it has the processing state, therefor no other sync Attempt tries to sync it aswell.
				var oQItemUpdate = {
					lastSyncAttempt: _date.timestamp(),
					status: 'proccessing',
					tries: oQItem.tries + 1
				};
				await _actionsTable.update(oQItem.id, oQItemUpdate);

				try {
					var response = await fetch(oQItem.request.url, {
						method: oQItem.request.type,
						headers: {
							'Content-Type': 'application/json; charset=UTF-8',
							'X-Request-ID': oQItem.id,
							'X-Client-ID': _pwa.getDeviceId()
						},
						body: JSON.stringify(oQItem.request.data)
					})
				} catch (error) {
					console.error("Error sync action with id " + oQItem.id + ". " + error.message);
					oQItemUpdate.response = error.message;
					oQItemUpdate.status = 'offline';
					await _actionsTable.update(oQItem.id, oQItemUpdate);
					return false;
				}

				if (response.statusText === 'timeout' || response.status === 0) {
					oQItemUpdate.response = response.statusText;
					oQItemUpdate.status = 'offline';
				}

				try {
					var data = await response.json();
				} catch (e) {
					// Do nothing here. It will result in an error because there is no data.
				}
				if (response.ok && data) {
					oQItemUpdate.status = 'synced';
					oQItemUpdate.response = data;
					oQItemUpdate.synced = _date.now();
				}

				if (response.status >= 400) {
					oQItemUpdate.response = data || (await response.text());
					oQItemUpdate.status = 'error';
				}

				await _actionsTable.update(oQItem.id, oQItemUpdate);

				if (oQItemUpdate.status === 'synced') {
					return true;
				}

				console.log('Server responded with an error syncing action with id: ' + oQItem.id);
				return false;
			},

			/**
			 * @return {promise}
			 */
			deleteAll: function (selectedIds) {
				var promises = [];
				selectedIds.forEach(function (id) {
					promises.push(_pwa.actionQueue.delete(id));
				});
				return Promise.all(promises);
			},

			/**
			 * @return {promise}
			 */
			delete: function (id) {
				return _actionsTable.delete(id)
			},

			/**
			 * @return {Dexie.Table}
			 */
			getTable: function () {
				return _actionsTable;
			},

			/**
			 * Returns items of the offline queue filtered by the given message ids.
			 * 
			 * @param {string[]} aIds
			 * @return {object[]} 
			 */
			getByIds: function (aMessageIds) {
				return _pwa.actionQueue.get('offline')
					.then(function (actionsData) {
						var selectedActions = [];
						actionsData.forEach(function (action) {
							if (aMessageIds.includes(action.id)) {
								selectedActions.push(action);
							}
						})
						return Promise.resolve(selectedActions);
					})
			},
		}, // EOF actionQueue

		model: {
			addPWA: function (sUrl) {
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
								.then(function () {
									return Promise.resolve(oPWA);
								})
						} else {
							// TODO sync only in certain intervals?
							return Promise.resolve(oPWA);
						}
					})
					.then(function (oPWA) {
						return _pwa.model.sync(oPWA.url);
					});
			},

			/**
			 * Sync the PWA model and its offline data sets (optional) with the facade
			 * 
			 * @param {string} [sPwaUrl]
			 * @param {boolean} [bSyncOfflineData]
			 * @return {Promise}
			 */
			sync: function (sPwaUrl, bSyncOfflineData) {
				var oPWA;
				bSyncOfflineData = bSyncOfflineData === undefined ? true : bSyncOfflineData;
				return _modelTable
					.get(sPwaUrl)
					.then(function (oRow) {
						oPWA = oRow;
						return fetch('api/pwa/' + sPwaUrl + '/model')
					})
					.then(function (oResponse) {
						if (oResponse.status == 404) {
							return _pwa.model.remove(sPwaUrl);
						}
						if (!oResponse.ok) {
							throw 'Failed to update offline data for PWA ' + sPwaUrl;
						}
						return oResponse
							.json()
							.then(function (oModel) {
								var aPromises = [];
								oPWA.sync_last = (+ new Date());
								_merge(oPWA, oModel);
								aPromises.push(_modelTable.update(sPwaUrl, oPWA));
								oPWA.data_sets.forEach(function (oDataSet) {
									oDataSet.pwa_uid = oPWA.uid;
									aPromises.push(
										_dataTable
											.get(oDataSet.uid)
											.then(function (oRow) {
												if (oRow === undefined) {
													return _dataTable.put(oDataSet);
												} else {
													oDataSet = _merge({}, oRow, oDataSet);
													return _dataTable.update(oDataSet.uid, oDataSet);
												}
											})
									)
								})
								return Promise
									.all(aPromises)
									.then(function () {
										if (bSyncOfflineData) {
											return _pwa.data.syncAll(oPWA.uid);
										} else {
											return Promise.resolve();
										}
									});
							})
					})
			},

			/**
			 * Deletes the PWA from the client completely.
			 * Returns a promise resolving to the number of affected data sets (0 or 1)
			 * @param {string} sDataSetUid
			 * @return Promise
			 */
			remove: function (sPwaUrl) {
				return _modelTable.delete(sPwaUrl);
			},

			/**
			 * @return {Dexie.Table}
			 */
			getTable: function () {
				return _modelTable;
			}
		}, // EOF model

		data: {

			/**
		 * Retrieves the auto offline toggle status from the IndexedDB.
		 * @return {Promise<boolean>} A promise that resolves to the auto offline toggle status.
		 */
			getAutoOfflineToggleStatus: function () {
				return checkIndexedDB()
					.then((exists) => {
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						return _autoOfflineToggle
							.get('autoOfflineStatus')
							.then(function (record) {
								return Promise.resolve(record ? record.status : false);
							})
							.catch(function (error) {
								console.error("Error retrieving auto offline toggle status:", error);
								return Promise.reject(error);
							});
					})
					.catch((error) => {
						console.error("Error checking IndexedDB:", error);
						return Promise.reject(error);
					});
			},

			/**
			 * saveAutoOfflineToggleStatus - Saves or updates the auto offline toggle status in IndexedDB.
			 * If the table is empty, it initializes with a default value of true.
			 *
			 * @param {boolean} [status] - The auto offline toggle status to be saved. If not provided, it will check for existing data.
			 *
			 * @return {Promise} - Returns a Promise that resolves if the status is saved successfully.
			 */
			saveAutoOfflineToggleStatus: function (status) {
				return checkIndexedDB()
					.then((exists) => {
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						// First, check if there's existing data
						return _autoOfflineToggle.get('autoOfflineStatus')
							.then(existingData => {
								if (!existingData) {
									// If no existing data, use the provided status or default to true
									status = typeof status !== 'undefined' ? status : true;
								} else if (typeof status === 'undefined') {
									// If status is not provided but we have existing data, keep the existing status
									status = existingData.status;
								}

								var autoOfflineData = {
									id: 'autoOfflineStatus',
									status: status
								};

								return _autoOfflineToggle.put(autoOfflineData);
							});
					})
					.then(() => {
						console.log(`Auto offline toggle status saved: ${status}`);
						return Promise.resolve(status);
					})
					.catch((error) => {
						console.error("Error saving auto offline toggle status:", error);
						return Promise.reject(error);
					});
			},
 
			/**
			* saveConnectionStatus - Saves the current connection status in IndexedDB if it's different from the last saved status.
			* 
			* If the new status is the same as the previously saved status, no new record is created.
			* If the status is different, it saves the new status with the current timestamp.
			* 
			* @param {string} status - The connection status to be saved. Typically, this will be a string 
			*                          such as "online", "offline", 'semi_offline', 'forced_offline'.
			* 
			* @return {Promise} - Returns a Promise that resolves if the status is saved successfully, 
			*                     or does nothing if the status is the same as the last saved status.
			*/
			saveConnectionStatus: function (status) {
				return checkIndexedDB()
					.then((exists) => {
						// Check if IndexedDB exists
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						// Check for any IndexedDB errors
						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						var currentTime = new Date();

						// Fetch the last saved connection status
						return _connectionTable.orderBy('time').last()
							.then(function (lastRecord) {
								// If the last status is the same, do nothing
								if (lastRecord && lastRecord.status === status) {
									return Promise.resolve();
								} else {
									// If the status is different, save the new status
									var connectionData = {
										status: status,
										time: currentTime
									};
									return _connectionTable.put(connectionData)
										.then(function () {
											return Promise.resolve();
										});
								}
							})
							.catch(function (error) {
								//console.error("Error saving connection status:", error);
								return Promise.reject(error);
							});
					})
					.catch((error) => {
						//console.error("Error checking IndexedDB:", error);
						return Promise.reject(error);
					});
			},

			/**
 * Retrieves all network stats from the IndexedDB.
 * @return {promise}
 */
			getAllNetworkStats: function () {
				return checkIndexedDB()
					.then((exists) => {
						// Check if IndexedDB exists
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						// Check if there is an IndexedDB error
						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						return _networkStatTable.toArray()
							.then(function (stats) {
								return Promise.resolve(stats);
							})
							.catch(function (error) {
								//console.error("Error retrieving network stats:", error);
								return Promise.reject(error);
							});
					})
					.catch((error) => {
						//console.error("Error checking IndexedDB:", error);
						return Promise.reject(error);
					});
			},

			/**
			 * Saves a new network stat to the IndexedDB.
			 * @param {time, speed, mime_type, size}
			 * @return {promise}
			 */
			saveNetworkStat: function (time, speed, mime_type, size) {
				return checkIndexedDB()
					.then((exists) => {
						// Check if IndexedDB exists
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						var stat = {
							time: time,
							speed: speed,
							mime_type: mime_type,
							size: size
						};

						return _networkStatTable.put(stat)
							.then(function () {
								return Promise.resolve();
							})
							.catch(function (error) {
								//console.error("Error saving network stat:", error);
								return Promise.reject(error);
							});
					})
					.catch((error) => {
						//console.error("Error checking IndexedDB:", error);
						return Promise.reject(error);
					});
			},

			/**
			 * Deletes all network stats in the IndexedDB that were recorded before the specified timestamp.
			 * @param {number} timestamp - The timestamp to check against.
			 * @return {promise}
			 */
			deleteNetworkStatsBefore: function (timestamp) {
				return checkIndexedDB()
					.then((exists) => {
						// Check if IndexedDB exists
						if (!exists) {
							return Promise.reject("IndexedDB does not exist");
						}

						if (_error) {
							return Promise.reject("IndexedDB error");
						}

						return _networkStatTable
							.where('time')
							.below(timestamp)
							.delete()
							.then(function (deleteCount) {
								//console.log(`Deleted ${deleteCount} network stats older than ${timestamp}`);
								return Promise.resolve(deleteCount);
							})
							.catch(function (error) {
								//console.error("Error deleting old network stats:", error);
								return Promise.reject(error);
							});
					})
					.catch((error) => {
						//console.error("Error checking IndexedDB:", error);
						return Promise.reject(error);
					});
			},

			/**
			 * @return {promise}
			 */
			get: function (oQuery) {
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
						return _dataTable.filter(function (oDataSet) {
							return oDataSet.object_alias === oQuery;
						}).first();
					}
				}
			},

			getRowsAddedOffline: function (oDataSet) {
				return oDataSet.rows_added_offline || [];
			},

			cleanupRowsAddedOffline: function (oDataSet) {
				var aQIds = [];
				var aRows = oDataSet.rows_added_offline;
				var aRowsNew = [];
				console.log('cleaning offline additions', aRows);
				if (aRows === undefined || aRows.length === 0) {
					return Promise.resolve();
				}
				aRows.forEach(function (oRow) {
					aQIds = aQIds.concat(oRow._actionQueueIds);
				});
				return _pwa.actionQueue
					.getByIds(aQIds)
					.then(function (aQItems) {
						aQIds.forEach(function (sQId, i) {
							if (aQItems.filter(function (oQItem) { return oQItem.id === sQId; }).length !== 0) {
								aRowsNew.push(aRows[i]);
							}
						});
						if (aRowsNew.length !== aRows.length) {
							oDataSet.rows_added_offline = aRowsNew;
							return _dataTable
								.update(oDataSet.uid, oDataSet)
								.then(function () {
									return Promise.resolve(oDataSet);
								})
						}
						return Promise.resolve(oDataSet);
					})
			},

			syncAll: function ({ sPwaUid = null, doReSync = false } = {}) {
				if (sPwaUid !== null) {
					_dataTable
						.filter(function (oDataSet) {
							return oDataSet.pwa_uid === sPwaUid;
						})
						.toArray(function (aSets) {
							aPromises = [];
							aSets.forEach(function (oDataSet) {
								aPromises.push(_pwa.data.sync(oDataSet.uid, doReSync));
							});

							return Promise.all(aPromises);
						});
				} else {
					_dataTable
						.toArray(function (aSets) {
							aPromises = [];
							aSets.forEach(function (oDataSet) {
								aPromises.push(_pwa.data.sync(oDataSet.uid, doReSync));
							});

							return Promise.all(aPromises);
						});
				}
			},

			sync: function (sDataSetUid, doReSync) {
				if (doReSync === undefined) {
					doReSync = false;
				}

				var oDataSet;
				return _dataTable
					.get(sDataSetUid)
					.then(function (oRow) {
						oDataSet = oRow;
						if (oDataSet === undefined) {
							Promise.reject('Faild syncing data set ' + sDataSetUid + ': data set not found in browser!');
						}
						var url = oDataSet.url;
						if (doReSync === false && oDataSet.incremental === true) {
							url = oDataSet.incrementalUrl;
						}
						return fetch(url);
					})
					.then(function (oResponse) {
						if (oResponse.status === 404) {
							return _pwa.data.remove(oDataSet.uid);
						}
						if (!oResponse.ok) {
							throw 'Failed to update offline data ' + sDataSetUid + ' (' + oDataSet.object_alias + ')';
						}

						return oResponse
							.json()
							.then(function (oDataUpdate) {
								if (doReSync === false && oDataUpdate.incremental === true) {
									// merges containing arrays
									_deepMerge(oDataSet, oDataUpdate);
								}
								else {
									// overrides properties
									_merge(oDataSet, oDataUpdate);
								}
								return _dataTable
									.update(oDataSet.uid, oDataSet)
									.then(function () {
										return Promise.resolve(oDataSet);
									})
									.then(function (oDataSet) {
										return oDataSet !== undefined ? _pwa.data.cleanupRowsAddedOffline(oDataSet) : Promise.resolve(oDataSet);
									})
							})
					})
					.catch(function (e) {
						console.error(e);
					})
			},

			/**
			 * Deletes a data set from the client memory completely.
			 * Returns a promise resolving to the number of affected data sets (0 or 1)
			 * @param {string} sDataSetUid
			 * @return Promise
			 */
			remove: function (sDataSetUid) {
				return _dataTable.delete(sDataSetUid);
			},

			/**
			 * Attempts to update offline data to include changes made by an offline action
			 * 
			 * @param {{
					id: String,
					object: String,
					action: String,
					request: <Object>,
					triggered: String,
					status: String,
					tries: Int,
					synced: String,
					action_name: String,
					object_name: String,
					effects: Array
				}} oQItem
			 */
			applyAction: function (oQItem, sOfflineDataEffect) {
				var aRows = _pwa.actionQueue.getRequestDataRows(oQItem);
				if (aRows.length === 0) {
					return Promise.resolve();
				}

				switch (sOfflineDataEffect) {
					case 'none':
					case null:
					case undefined:
						return Promise.resolve();
					case 'copy':
					case 'create':
						return _pwa.data
							.get(oQItem.object)
							.then(function (oDataSet) {
								if (oDataSet === undefined) {
									return Promise.resolve();
								}
								if (oDataSet.rows_added_offline === undefined) {
									oDataSet.rows_added_offline = [];
								}
								aRows.forEach(function (oRow) {
									oRow = _merge({}, oRow);
									if (oDataSet.uid_column_name) {
										oRow[oDataSet.uid_column_name] = (oDataSet.rows_added_offline.length + 1) * (-1);
									}
									if (oRow._actionQueueIds === undefined) {
										oRow._actionQueueIds = [oQItem.id];
									} else {
										oRow._actionQueueIds.push(oQItem.id);
									}
									oDataSet.rows_added_offline.push(oRow);
								});
								return _dataTable.update(oDataSet.uid, oDataSet);
							})
					case 'update':
						return _pwa.data
							.get(oQItem.object)
							.then(function (oDataSet) {
								var aActionRows = _pwa.actionQueue.getRequestDataRows(oQItem);
								var iChanges = 0;
								if (oDataSet === undefined || aActionRows.length === 0) {
									return Promise.resolve();
								}
								if (!oDataSet.uid_column_name) {
									return Promise.resolve();
								}
								aActionRows.forEach(function (oActRow) {
									var aSyncedMatches = (oDataSet.rows || [])
										.filter(function (oRow) {
											return oRow[oDataSet.uid_column_name] == oActRow[oDataSet.uid_column_name];
										})
									var aUnsyncedMatches = (oDataSet.rows_added_offline || [])
										.filter(function (oRow) {
											return oRow[oDataSet.uid_column_name] == oActRow[oDataSet.uid_column_name];
										});

									aSyncedMatches.concat(aUnsyncedMatches)
										.forEach(function (oOfflineRow, i) {
											for (var k in oActRow) {
												if (!k.includes('__')) {
													oOfflineRow[k] = oActRow[k];
												}
											}
											iChanges++;
											console.log('Updated row ', oActRow);
										});
								});
								if (iChanges > 0) {
									return _dataTable.update(oDataSet.uid, oDataSet);
								}
								return Promise.resolve();
							})
				}
			},

			/**
			 * @return {object[]}
			 */
			mergeRows: function (aOldRows, aNewRows, sUidAlias) {
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
			syncImages: function (aUrls, sCacheName = 'image-cache') {
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
							if (!aUrls[i]) continue;
							var request = new Request(aUrls[i]);
							requests.push(
								fetch(request.clone())
									.then(response => {
										// Check if we received a valid response
										if (!response || response.status !== 200 || response.type !== 'basic') {
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
			getTable: function () {
				return _dataTable;
			},

			/**
			 * @return {promise}
			 */
			syncAffectedByActions: async function () {
				var aDataSets = await _dataTable.toArray();
				var aPromises = [];
				aDataSets.forEach(async function (oDataSet) {
					var aActionsSynced = await _pwa.actionQueue.get('synced', oDataSet.object_alias);
					var sUidCol = oDataSet.uid_column_name;
					var aUids = [];
					if (aActionsSynced.length === 0) {
						return;
					}
					if (!sUidCol) {
						return;
					}
					aActionsSynced.forEach(function (oAction) {
						if (!(oAction.request && oAction.request.data && oAction.request.data.data && oAction.request.data.data.rows)) {
							return;
						}
						oAction.request.data.data.rows.forEach(function (row) {
							aUids.push(row[sUidCol]);
						})
					});
					aPromises.push(
						_pwa.data.sync(oDataSet.uid)
							.catch(function (error) {
								console.error(error);
							})
							.then(function () {
								aActionsSynced.forEach(function (oAction) {
									oAction.effects.forEach(function (oEffect) {
										if (oEffect.event_params && oEffect.event_params.length > 0) {
											try {
												$(document).trigger("actionperformed", oEffect.event_params);
											} catch (e) {
												console.log('Skipping offline action sync effects: ', e);
											}
										}
									});
								});
								return Promise.resolve();
							})
					)
				});

				// after preloads are updated, delete all actions with status 'synced' from the IndexedDB
				var syncedIds = await _pwa.actionQueue.getIds('synced');
				syncedIds.forEach(function (id) {
					aPromises.push(_pwa.actionQueue.delete(id));
				});

				return Promise.all(aPromises);
			},

		}, // EOF data

		errors: {
			/**
			 * @return {object}
			 */
			sync: function () {
				if (_error) {
					return Promise.resolve({});
				}

				return fetch('api/pwa/errors?deviceId=' + _pwa.getDeviceId(), {
					method: 'GET'
				})
					.then(function (response) {
						if (response.ok) {
							return response.json();
						} else {
							return {};
						}
					})
					.catch(function (error) {
						console.error('Cannot read sync errors from server:', error);
						return {};
					})
			}
		} // EOF errors
	} // EOF _pwa
	return _pwa;
})));