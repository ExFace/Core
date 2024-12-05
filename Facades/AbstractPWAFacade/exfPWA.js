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

	var _indexedDbInitError = null;

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
			'networkStat': 'time',
			'connection': 'time, status'
		});
		dexie.version(4).stores({
			'offlineData': 'uid, object_alias',
			'offlineModel': 'url',
			'actionQueue': '&id, object, action',
			'deviceId': 'id',
			'networkStat': 'time',
			'connection': 'time, state'
		});

		dexie.open().catch(function (e) {
			_indexedDbInitError = e;
			console.error("PWA faild to initialized. Falling back to online-only mode. " + e.stack);
		});
		return dexie;
	}();

	var _deviceId;
	var _queueTopics = ['offlineTask'];

	if (_indexedDbInitError === null) {
		var _dataTable = _db.table('offlineData');
		var _modelTable = _db.table('offlineModel');
		var _actionsTable = _db.table('actionQueue');
		var _deviceIdTable = _db.table('deviceId');
		var _networkStatTable = _db.table('networkStat');
		var _connectionTable = _db.table('connection');
	}

	(function () {
		_deviceIdTable
			.toArray()
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
			// There were cases when dexie.open() worked, but reading threw an error, which blocked everything else.
			// Since reading the device ID is the first operation, turn off PWA if this fails
			.catch(function (e) {
				_indexedDbInitError = e;
				console.error("PWA faild to initialized. Falling back to online-only mode. " + e.stack);
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

	/**
	 * Pure state container for network status management
	 * Handles the core network state and provides basic state operations
	 * This object maintains the single source of truth for network state
	 * @private
	 */
	var _oNetStat = {
		// Internal state flags
		_bForcedOffline: false,  // Manually forced offline mode
		_bAutoOffline: false,    // Automatic offline mode based on conditions
		_bSlowNetwork: false,    // Network speed status indicator

		/**
		 * Returns the browser's native online/offline state
		 * Falls back to true if navigator.onLine is not supported
		 * @private
		 * @returns {boolean} True if browser reports online, false otherwise
		 */
		isBrowserOnline: function () {
			return navigator.onLine !== undefined ? navigator.onLine : true;
		},

		/**
		 * Core state update logic 
		 * Simplified version - just updates state flags and returns boolean if any change occurred
		 * 
		 * @private
		 * @param {boolean} forcedOffline - Force offline mode flag
		 * @param {boolean} autoOffline - Auto offline mode flag
		 * @param {boolean} slowNetwork - Slow network indicator
		 * @returns {boolean} True if any state changed, false otherwise
		 */
		_updateState: function (forcedOffline, autoOffline, slowNetwork) {
			// Check if any state will change
			const hasChanges = (
				forcedOffline !== this._bForcedOffline ||
				autoOffline !== this._bAutoOffline ||
				slowNetwork !== this._bSlowNetwork
			);

			// Update internal state flags
			this._bForcedOffline = forcedOffline || false;
			this._bAutoOffline = autoOffline || false;
			this._bSlowNetwork = slowNetwork || false;

			return hasChanges;
		},

		/**
		 * Serializes the current state for storage
		 * @returns {Object} Serialized state object ready for storage
		 */
		serialize: function () {
			return {
				bForcedOffline: this._bForcedOffline,
				bAutoOffline: this._bAutoOffline,
				bSlowNetwork: this._bSlowNetwork,
				bBrowserOnline: this.isBrowserOnline()
			};
		},

		/**
		 * Restores state from a previously serialized object
		 * @param {Object} storedState - Previously serialized state object
		 * @returns {Object} Returns this for method chaining
		 */
		deserialize: function (storedState) {
			if (storedState) {
				this._bForcedOffline = storedState.bForcedOffline;
				this._bAutoOffline = storedState.bAutoOffline;
				this._bSlowNetwork = storedState.bSlowNetwork;
			}
			return this;
		},

		/**
		 * Get human readable state description
		 * @returns {string} Current state as a readable string
		 */
		toString: function () {
			if (!this.isBrowserOnline()) return "Offline";
			if (this._bForcedOffline) return "Offline, Forced";
			if (this._bAutoOffline && this._bSlowNetwork) return "Offline, Low Speed";
			return "Online";
		},

		/**
		 * Check if application should be online
		 * @returns {boolean} True if the application should be online
		 */
		isOnline: function () {
			return !this.isOfflineVirtually() && this.isBrowserOnline();
		},

		/**
		 * Check if app is virtually offline (forced or auto-offline due to slow network)
		 * @returns {boolean} True if the app is virtually offline
		 */
		isOfflineVirtually: function () {
			return this._bForcedOffline ||
				(this._bAutoOffline && this._bSlowNetwork);
		},

		/**
		 * Check if offline mode is manually forced
		 * @returns {boolean} True if offline mode is forced
		 */
		isOfflineForced: function () {
			return this._bForcedOffline;
		},

		/**
		 * Check if network is considered slow
		 * @returns {boolean} True if network is slow
		 */
		isNetworkSlow() {
			return this._bSlowNetwork;
		},

		/**
		 * Check if auto-offline feature is enabled
		 * @returns {boolean} True if auto-offline is enabled
		 */
		hasAutoffline: function () {
			return this._bAutoOffline;
		}
	};


	// Init the PWA
	var _pwa = {
		/**
		 * Network state management module
		 * Handles network state transitions and persistence
		 * Modified to work without state caching for simplicity
		 * Note: May have higher IndexedDB write frequency
		 */
		network: {

			// Flag to prevent concurrent state updates
			/* _pendingStateUpdate is a flag and it helps in two ways:
				1-For the Same tab :
					Stops multiple updates happening at once
					Manages updates from network calls
				2- Working with multiple tabs:
					Prevents data conflicts when multiple tabs try to update the same data
					Keeps data in sync across tabs
				Think of it like a "busy" sign - when one process is updating data, others must wait until it's done.
			*/
			_pendingStateUpdate: false,
			// TODO: Improve network state management using IndexedDB queue
			/*
			Why: 
			- Currently ignoring state updates when busy, causing data loss
			- No sync between tabs, leading to inconsistent states
			- State updates can be lost on browser crash/close

			Possible Solution:
			1. exfPWA.js: 
				- Update dexie version to add 'stateUpdateQueue' table:
					'stateUpdateQueue': '++id, timestamp, state'
				- Remove _pendingStateUpdate flag
				- Update setState() to use queue from DB
				- Process updates in FIFO order from DB
				- Add BroadcastChannel support for cross-tab sync

			2. openui5.facade.js:
				- Update network state change handlers to work with new queue system
			*/

			/**
			* Handles toggling of forced offline state
			* Preserves other state flags while updating forced offline status
			* 
			* @param {boolean} newState - The new forced offline state to set
			* @returns {Promise} Resolves when state is updated
			*/
			handleForceOfflineToggle: async function (newState) {
				try {
					const currentState = this.getState();
					await this.setState(
						newState,                    // new forced offline state
						currentState._bAutoOffline,  // preserve current auto offline state
						currentState._bSlowNetwork   // preserve current network speed state
					);
				} catch (error) {
					console.error('Error toggling force offline:', error);
					throw error;
				}
			},

			/**
			 * Initializes and manages the regular network condition monitoring system.
			 * 
			 * This is the default polling mechanism that runs when network conditions are normal.
			 * It performs periodic checks of network speed and can transition to fast polling
			 * when slow network conditions are detected.
			 * 
			 * Key Features:
			 * - Runs at 5-second intervals (lower frequency to conserve resources)
			 * - Automatically cleans up existing pollers before starting
			 * - Respects forced offline mode
			 * - Only performs speed checks when auto-offline is enabled
			 * - Manages transitions between polling states
			 * 
			 * State Management:
			 * - Tracks network state changes
			 * - Updates UI when network conditions change
			 * - Manages polling frequency transitions
			 * 
			 * Error Handling:
			 * - Catches and logs all errors without crashing
			 * - Maintains poller operation even after errors
			 * 
			 * @return {void}
			 */
			initPoorNetworkPoller: function () {
				// Clean up any existing polling interval to prevent memory leaks
				if (this._networkPoller) {
					console.debug('Cleaning up existing network poller');
					clearInterval(this._networkPoller);
				}

				// Initialize new polling interval
				this._networkPoller = setInterval(async () => {
					try {
						// Get current network state
						const state = await this.getState();

						console.debug('Regular Network Poll:', {
							timestamp: new Date().toISOString(),
							currentState: {
								forcedOffline: state._bForcedOffline,
								autoOffline: state._bAutoOffline,
								slowNetwork: state._bSlowNetwork
							}
						});

						// Respect forced offline mode - stop polling if active
						if (state._bForcedOffline) {
							console.debug('Forced offline mode detected, suspending network polling');
							clearInterval(this._networkPoller);
							return;
						}

						// Only proceed with speed check if auto-offline is enabled
						if (state._bAutoOffline) {
							// Perform network speed evaluation
							const isNetworkSlow = await this.checkNetworkSlow();

							// Log state transition if network status changed
							if (isNetworkSlow !== state._bSlowNetwork) {
								console.debug('Network Status Change Detected:', {
									previousStatus: state._bSlowNetwork ? 'SLOW' : 'FAST',
									newStatus: isNetworkSlow ? 'SLOW' : 'FAST',
									timestamp: new Date().toISOString()
								});

								// Update application state with new network status
								await this.setState(
									state._bForcedOffline,
									state._bAutoOffline,
									isNetworkSlow
								);
							}

							// Switch to rapid polling if network becomes slow
							if (isNetworkSlow) {
								console.debug('Initiating fast polling due to slow network conditions');
								clearInterval(this._networkPoller);
								this.initFastNetworkPoller();
							}
						}
					} catch (error) {
						console.error('Network Polling Error:', {
							message: error.message,
							stack: error.stack,
							timestamp: new Date().toISOString()
						});
					}
				}, 5000); // 5-second interval for regular polling
			},

			/**
			 * Initializes and manages the rapid network condition monitoring system.
			 * 
			 * This polling mechanism is activated when the network is detected as slow,
			 * allowing for quicker detection of network condition improvements.
			 * 
			 * Key Features:
			 * - Runs at 2-second intervals (higher frequency for responsiveness)
			 * - Monitors for network improvement
			 * - Transitions back to normal polling when conditions improve
			 * - Maintains continuous state updates
			 * 
			 * State Transitions:
			 * 1. Network Improves:
			 *    - Updates state
			 *    - Switches back to normal polling
			 * 2. Network Remains Slow:
			 *    - Maintains fast polling
			 *    - Updates state regularly
			 * 
			 * Safety Features:
			 * - Respects forced offline mode
			 * - Handles auto-offline toggle changes
			 * - Includes comprehensive error handling
			 * 
			 * @return {void}
			 */
			initFastNetworkPoller: function () {
				// Clean up existing poller to prevent duplicate intervals
				if (this._networkPoller) {
					console.debug('Cleaning up existing fast network poller');
					clearInterval(this._networkPoller);
				}

				// Initialize rapid polling interval
				this._networkPoller = setInterval(async () => {
					try {
						// Get current state
						const state = await this.getState();

						console.debug('Fast Network Poll:', {
							timestamp: new Date().toISOString(),
							currentState: {
								forcedOffline: state._bForcedOffline,
								autoOffline: state._bAutoOffline,
								slowNetwork: state._bSlowNetwork
							}
						});

						// Check for forced offline mode
						if (state._bForcedOffline) {
							console.debug('Forced offline mode detected, suspending fast polling');
							clearInterval(this._networkPoller);
							return;
						}

						// Evaluate current network conditions
						const isNetworkSlow = await this.checkNetworkSlow();

						// Check if we should return to normal polling
						if (!isNetworkSlow || !state._bAutoOffline) {
							console.debug('Network Conditions Update:', {
								event: 'Returning to normal polling',
								reason: !isNetworkSlow ? 'Network improved' : 'Auto-offline disabled',
								networkSlow: isNetworkSlow,
								autoOffline: state._bAutoOffline,
								timestamp: new Date().toISOString()
							});

							// Update state before switching polling modes
							await this.setState(state._bForcedOffline, state._bAutoOffline, isNetworkSlow);

							// Switch back to normal polling
							clearInterval(this._networkPoller);
							this.initPoorNetworkPoller();
						} else {
							// Network still slow, maintain state updates
							await this.setState(state._bForcedOffline, state._bAutoOffline, isNetworkSlow);
						}
					} catch (error) {
						console.error('Fast Network Polling Error:', {
							message: error.message,
							stack: error.stack,
							timestamp: new Date().toISOString()
						});
					}
				}, 2000); // 2-second interval for rapid polling
			},

			/**
			 * Retrieves and initializes state from persistent storage
			 * 
			 * This method:
			 * 1. Fetches latest state record from IndexedDB
			 * 2. Restores network state from stored data
			 * 3. Ensures consistency between memory and database
			 * 
			 * @returns {Promise<Object>} Promise resolving to current state
			 */
			checkState: async function () {
				try {
					// Get latest state record from IndexedDB
					const oLastRecord = await _connectionTable
						.orderBy('time')
						.last();

					// Restore state if record exists
					if (oLastRecord?.state) {
						_oNetStat.deserialize(oLastRecord.state);
					}

					return Promise.resolve(_oNetStat);
				} catch (oError) {
					console.warn('Failed to retrieve network status:', oError);
					return Promise.resolve(_oNetStat);
				}
			},

			/**
			 * Returns the current network state
			 * @returns {Object} Current state object
			 */
			getState: function () {
				return _oNetStat;
			},


			/**
			 * Updates network state and triggers network changed event if needed
			 * Simplified version - removes change tracking complexity
			 * 
			 * @param {boolean} forcedOffline - Force offline mode flag
			 * @param {boolean} autoOffline - Auto offline mode flag
			 * @param {boolean} slowNetwork - Slow network indicator
			 * @returns {Promise} Resolves when state is updated
			 */
			setState: async function (forcedOffline, autoOffline, slowNetwork) {
				// Prevent concurrent updates
				oSelf = this;
				if (oSelf._pendingStateUpdate) {
					return Promise.resolve();
				}

				oSelf._pendingStateUpdate = true;

				try {
					// Update state and check if anything changed
					const hasChanges = _oNetStat._updateState(forcedOffline, autoOffline, slowNetwork);

					// If there were changes, trigger event
					if (hasChanges) {

						//starts promise process and continue. when then block runs, state sets false. Code doesnt wait  to flow.
						// means, without table update, network changed event can bi triggered -  this can not be acceptable

						// Persist updated state
						// _connectionTable.put({
						// 	time: _date.now(),
						// 	state: _oNetStat.serialize()
						// }).then(function () {
						// 	oSelf._pendingStateUpdate = false;
						// });

						// Persist updated state
						/*
						With await:
							Waits until the Promise operation completes
							_pendingStateUpdate becomes false when operation is done
							Code flow continues only after table update is complete
							Networkchanged event is definitely triggered after table update
						*/
						await _connectionTable.put({
							time: _date.now(),
							state: _oNetStat.serialize()
						});


						// Notify listeners about state changes
						$(document).trigger('networkchanged', {
							currentState: _oNetStat,
						});
					}
					oSelf._pendingStateUpdate = false;
					return Promise.resolve();

				} catch (error) {
					oSelf._pendingStateUpdate = false;
					console.error('Error updating network state:', error);
					return Promise.reject(error);
				}
			},

			/**
			 * Get all network speed measurements 
			 */
			getAllStats: function () {
				if (!exfPWA.isAvailable()) return Promise.resolve([]);

				return _networkStatTable.toArray()
					.catch(function (error) {
						console.warn('Failed to get network stats:', error);
						return [];
					});
			},

			/**
			 * Save new speed measurement
			 */
			saveStat: function (time, speed, mime_type, size) {
				if (!exfPWA.isAvailable()) return Promise.resolve();

				return _networkStatTable.put({
					time: time,
					speed: speed,
					mime_type: mime_type,
					size: size
				}).catch(function (error) {
					console.warn('Failed to save network stat:', error);
				});
			},

			/**
			 * Delete old measurements
			 * 
			 * @param {number} timestamp - Timestamp before which to delete stats
			 * @returns {Promise<number>} Number of deleted records
			 */
			deleteStatsBefore: function (timestamp) {
				if (!exfPWA.isAvailable()) return Promise.resolve(0);

				return _networkStatTable
					.where('time')
					.below(timestamp)
					.delete()
					.catch(function (error) {
						console.warn('Failed to delete old stats:', error);
						return 0;
					});
			},

			/**
			* Evaluates if the current network conditions are considered "slow" based on multiple criteria.
			* 
			* The function uses a two-tier approach for network speed detection:
			* 1. First Tier: Browser's Network Information API
			*    - Checks connection type (2G, 3G, 4G, etc.)
			*    - Checks reported downlink speed
			*    - More accurate but not supported by all browsers
			* 
			* 2. Second Tier: Speed Measurements from Recent Requests
			*    - Fallback mechanism when Browser API is not available
			*    - Uses rolling average of last 10 request speeds
			*    - Less accurate but more widely supported
			* 
			* Network is considered slow if any of these conditions are met:
			* - Connection type is 2G, slow-2G, or 3G
			* - Downlink speed is <= 0.5 Mbps
			* - Average measured speed from last 10 requests is <= 0.5 Mbps
			* 
			* @returns {Promise<boolean>} True if network is determined to be slow, false otherwise
			*/
			checkNetworkSlow: async function () {
				var bSlow;

				// TIER 1: Browser's Network Information API Check
				if (navigator?.connection) {
					// Consider connection slow if it's 2G, slow-2G, or 3G
					// These connection types typically can't support modern web applications effectively
					bSlow = ['2g', 'slow-2g', '3g'].includes(navigator.connection.effectiveType) ||
						(navigator.connection.downlink > 0 && navigator.connection.downlink <= 0.5);

					// If Browser API indicates slow connection, no need to check measurements
					if (bSlow) {
						console.debug('Network Status Report:', {
							status: 'SLOW',
							source: 'Browser API',
							connectionType: navigator.connection.effectiveType,
							downlinkSpeed: navigator.connection.downlink + ' Mbps',
							timestamp: new Date().toISOString()
						});
						return bSlow;
					}
				}

				// TIER 2: Recent Speed Measurements Analysis
				/**
				 * Analyzes recent network speed measurements to determine if the network is slow
				 * 
				 * This function follows a two-tier approach:
				 * 1. Retrieves recent network speed measurements
				 * 2. Calculates average speed and compares against threshold
				 * 
				 * @returns {Promise<boolean>} Promise resolving to true if network is slow, false otherwise
				 */
				try {
					// Fetch all recorded network statistics
					const aStats = await this.getAllStats();
					if (!aStats.length) {
						console.log('No speed measurements available, assuming network is fast');
						return false;
					}

					// Calculate average speed from most recent measurements
					// We use the last 10 measurements to get a reliable recent average
					const aRecentStats = aStats.slice(-10);
					const fAvgSpeed = aRecentStats.reduce((fSum, oStat) =>
						fSum + (Number(oStat.speed) || 0), 0) / aRecentStats.length;

					// Consider network slow if average speed is below threshold
					bSlow = fAvgSpeed <= 0.5; // 0.5 Mbps threshold

					console.log('Network Speed Analysis:', {
						averageSpeed: fAvgSpeed.toFixed(2) + ' Mbps',
						measurementCount: aRecentStats.length,
						oldestMeasurement: new Date(aRecentStats[0]?.time).toISOString(),
						latestMeasurement: new Date(aRecentStats[aRecentStats.length - 1]?.time).toISOString(),
						conclusion: bSlow ? 'SLOW' : 'FAST',
						source: 'Speed Measurements'
					});

				} catch (error) {
					console.error('Speed Measurement Analysis Failed:', {
						error: error.message,
						stack: error.stack,
						timestamp: new Date().toISOString()
					});
					bSlow = false; // Default to fast network on error
				}

				return bSlow;
			},

			/**
		 * Initializes network state monitoring system.
		 * Sets up event listeners and manages network state transitions.
		 * 
		 * The initialization process includes:
		 * - Setting up browser online/offline event listeners
		 * - Configuring custom networkchanged event handler
		 * - Initializing network quality polling system
		 * - Maintaining state consistency between memory and IndexedDB
		 * - Providing reliable state recovery after page reloads
		 */
			init: function () {

				/**
				 * Online Event Handler
				 * Triggered when browser detects network connection
				 * 
				 * State Management:
				 * 1. Updates connection table in IndexedDB
				 * 2. Updates in-memory network state (oNetStat)
				 * 3. Triggers custom event for UI updates
				 */
				window.addEventListener('online', async () => {
					console.log('Browser detected online state');

					try {
						// First persist state in connection table for consistency
						await _connectionTable.put({
							time: _date.now(),
							state: _oNetStat.serialize()  // Include current state flags
						});

						// Update internal state and trigger custom event
						this.checkState().then((oState) => {
							$(document).trigger('networkchanged', {
								currentState: oState,
								changes: {
									browserOnline: {
										from: false, // Previous state
										to: true    // New state
									}
								}
							});
						});
					} catch (oError) {
						console.error('Failed to handle online state transition:', oError);
					}
				});

				/**
				 * Offline Event Handler
				 * Triggered when browser loses network connection
				 * 
				 * State Management:
				 * 1. Updates connection table in IndexedDB
				 * 2. Updates in-memory network state (oNetStat)
				 * 3. Triggers custom event for UI updates
				 */
				window.addEventListener('offline', async () => {
					console.log('Browser detected offline state');

					try {
						// Persist state change in connection table
						await _connectionTable.put({
							time: _date.now(),
							state: _oNetStat.serialize()  // Include current state flags
						});

						// Update internal state and trigger custom event
						this.checkState().then((oState) => {
							$(document).trigger('networkchanged', {
								currentState: oState,
								changes: {
									browserOnline: {
										from: true,  // Previous state
										to: false    // New state
									}
								}
							});
						});
					} catch (oError) {
						console.error('Failed to handle offline state transition:', oError);
					}
				});

				/**
				 * Custom Network Change Event Handler
				 * Manages network polling strategy based on current state
				 * 
				 * This handler is responsible for:
				 * - Adjusting polling frequency based on network conditions
				 * - Switching between fast and normal polling modes
				 * - Handling auto-offline functionality
				 * - Ensuring state consistency across the application
				 */
				$(window).on('networkchanged', async function (oEvent, oData) {
					try {
						// Check if auto-offline feature is enabled
						if (_oNetStat.hasAutoffline()) {
							// Determine appropriate polling strategy
							const bIsOfflineVirtually = _oNetStat.isOfflineVirtually();

							if (bIsOfflineVirtually) {
								// Use fast polling when network is poor or virtually offline
								// Fast polling checks network more frequently (every 2 seconds)
								exfPWA.network.initFastNetworkPoller();
							} else {
								// Use normal polling for stable network conditions
								// Normal polling runs at longer intervals (every 5 seconds)
								exfPWA.network.initPoorNetworkPoller();
							}
						}
					} catch (oError) {
						console.error('Failed to handle network change:', oError);
					}
				});
			}
		},

		/**
		 * @return {bool}
		 */
		isAvailable: function () {
			return _indexedDbInitError === null;
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
					if (exfPWA.isAvailable() === false) {
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
			if (exfPWA.isAvailable() === false) {
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
				if (exfPWA.isAvailable() === false) {
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
				if (exfPWA.isAvailable() === false) {
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
				if (exfPWA.isAvailable() === false) {
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
				if (exfPWA.isAvailable() === false) {
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

				// if (!_pwa.network.getState().isOnline()) {
				// 	return Promise.resolve();
				// }

				// return _pwa.actionQueue
				// 	.getIds('offline')
				// 	.then(function (ids) {
				// 		return _pwa.actionQueue.syncIds(ids)
				// 	})
				// 	.then(function () {
				// 		return _pwa.data.syncAll();
				// 	});
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
				if (exfPWA.isAvailable() === false) {
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
			 * @return {promise}
			 */
			get: function (oQuery) {
				if (exfPWA.isAvailable() === false) {
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

				// Network check
				if (!_pwa.network.getState().isOnline() || _pwa.network.getState().isOfflineVirtually()) {
					return Promise.resolve();
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
				if (exfPWA.isAvailable() === false) {
					return Promise.resolve({});
				}

				if (_pwa.network.getState().isOnline() === false) {
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


	/**
	* Initialize network monitoring system
	* 
	* This initialization:
	* - Checks if auto-offline feature is enabled
	* - Starts poor network poller if enabled
	* - Does not poll if auto-offline is disabled
	* 
	* We start with poor polling by default because:
	* 1. Poor poller already checks network conditions and switches to fast polling if needed  
	* 2. Keeps the initialization logic simple and clear
	* 3. Follows the fail-safe principle - starts with less intensive monitoring
	*/
	_pwa.network.checkState().then(function (oNetStat) {
		if (oNetStat.hasAutoffline()) {
			// Start with regular polling - it will automatically 
			// switch to fast polling if network conditions degrade
			exfPWA.network.initPoorNetworkPoller();
		} else {
			// No polling needed if auto-offline is disabled
			console.debug('Auto-offline disabled, skipping network polling');
		}
	}).catch(function (oError) {
		// Log initialization errors but don't crash
		console.error('Network state initialization failed:', oError);
	});

	return _pwa;
}))); 