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
		 * @param {{forcedOffline: boolean, autoOffline: boolean, slowNetwork: boolean}} oFlags
		 * @returns {{forcedOffline: boolean, autoOffline: boolean, slowNetwork: boolean}} - only the changes
		 */
		_updateState: function (oFlags) {
			var oChagnes = {};
			if (oFlags.forcedOffline !== undefined && oFlags.forcedOffline !== this._bForcedOffline) {
				oChagnes.forcedOffline = this._bForcedOffline = oFlags.forcedOffline;
			}
			if (oFlags.autoOffline !== undefined && oFlags.autoOffline !== this._bAutoOffline) {
				oChagnes.autoOffline = this._bAutoOffline = oFlags.autoOffline;
			}
			if (oFlags.slowNetwork !== undefined && oFlags.slowNetwork !== this._bSlowNetwork) {
				oChagnes.slowNetwork = this._bSlowNetwork = oFlags.slowNetwork;
			}
			return oChagnes;
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



	var _oGlobalConfig = {
		network: {
			polling: {
				fastIntervalSeconds: 30,   // Interval for aggressive network monitoring
				slowIntervalSeconds: 30,   // Interval for normal network monitoring 
			},
			stateUpdate: {
				aStateUpdateQueue: [],     // Array to store pending state updates
				iStateUpdateRetries: 0,    // Counter for queue processing attempts  
				iMaxStateUpdateRetries: 5  // Maximum number of retry attempts
			}
		}
	};



	// Init the PWA
	var _pwa = {

		/**
   * Returns global configuration settings
   * @returns {Object} Global configuration object
   */
		getConfig: function () {
			return _oGlobalConfig;
		},


		/**
		 * Network state management module
		 * Handles network state transitions and persistence
		 * Modified to work without state caching for simplicity
		 * Note: May have higher IndexedDB write frequency
		 */
		network: {

			/**
			 * Intensive Network Quality Monitor  @function initFastNetworkPoller
			 * 
			 * Implements an aggressive polling mechanism for monitoring network quality
			 * during periods of network instability or when higher precision is needed.
			 * 			 
			 * 
			 * Operational Characteristics:
			 * - Polls every 30 seconds with enhanced monitoring
			 * - Performs comprehensive network quality checks
			 * - Optimized for detecting network improvements
			 * - Provides rapid response to network condition changes
			 * 
			 * Activation Triggers:
			 * - Poor network conditions detected
			 * - High-priority network operations
			 * - System requiring increased network awareness
			 * 
			 * State Management:
			 * - Clears existing polling intervals
			 * - Maintains detailed network state tracking
			 * - Updates system state based on network conditions
			 * 
			 * Transitions:
			 * - Reverts to standard polling when network improves
			 * - Continues intensive monitoring while conditions remain poor
			 * 
			 * Resource Considerations:
			 * - Higher resource usage than standard polling
			 * - Automatically optimizes polling when conditions improve
			 * - Balances monitoring precision with system performance
			 */
			initFastNetworkPoller: function () {
				if (this._networkPoller) {
					clearInterval(this._networkPoller);
				}

				const poll = async () => {
					try {
						const state = this.getState();
						const isNetworkSlow = await this.checkNetworkSlow();

						console.debug('Fast Network Poll:', {
							networkSlow: isNetworkSlow,
							currentState: state,
							interval: _oGlobalConfig.network.polling.fastIntervalSeconds + 's'
						});


						// Check if we should return to normal polling
						if (!isNetworkSlow || !state._bAutoOffline) {
							console.debug('Network Conditions Update:', {
								event: 'Returning to normal polling',
								reason: !isNetworkSlow ? 'Network improved' : 'Auto-offline disabled',
								networkSlow: isNetworkSlow,
								autoOffline: state._bAutoOffline,
								timestamp: _date.now()
							});

							// Update state before switching polling modes
							this.setState({ slowNetwork: isNetworkSlow });


						} else {
							// Network still slow, maintain state updates
							this.setState({ slowNetwork: isNetworkSlow });
						}

						if (!isNetworkSlow) {
							clearInterval(this._networkPoller);
							this.initPoorNetworkPoller();
						}
					} catch (error) {
						console.error('Fast Network Poll Error:', error);
					}
				};

				// Execute initial poll immediately
				poll();

				// Set up interval using global config
				this._networkPoller = setInterval(poll,
					_oGlobalConfig.network.polling.fastIntervalSeconds * 1000);
			},


			/**
			 * Standard Network Quality Monitor @function initPoorNetworkPoller
			 * 
			 * Implements the standard polling mechanism for monitoring network quality under normal conditions.
			 * This is the default monitoring mode when network conditions are stable.
			 *  
			 * Operational Characteristics:
			 * - Polls every 30 seconds during normal network conditions
			 * - Performs basic network quality assessments
			 * - Resource-efficient for long-term monitoring
			 * - Automatically transitions to fast polling if network quality degrades
			 * 
			 * State Management:
			 * - Clears any existing polling interval before starting
			 * - Updates network state based on quality checks
			 * - Maintains state consistency with IndexedDB
			 * 
			 * Transitions:
			 * - Switches to fast polling (initFastNetworkPoller) if network becomes slow
			 * - Continues standard polling if network remains stable
			 * 
			 * Error Handling:
			 * - Logs polling errors for debugging
			 * - Continues operation even if individual polls fail
			 */
			initPoorNetworkPoller: function () {
				if (this._networkPoller) {
					clearInterval(this._networkPoller);
				}

				const poll = async () => {
					try {
						const state = this.getState();
						const isNetworkSlow = await this.checkNetworkSlow();

						console.debug('Poor Network Poll:', {
							networkSlow: isNetworkSlow,
							currentState: state,
							interval: _oGlobalConfig.network.polling.slowIntervalSeconds + 's'
						});

						// Only proceed with speed check if auto-offline is enabled
						if (state._bAutoOffline) {
							// Perform network speed evaluation
							const isNetworkSlow = await this.checkNetworkSlow();

							// Log state transition if network status changed
							if (isNetworkSlow !== state._bSlowNetwork) {

								// Update application state with new network status
								this.setState({
									slowNetwork: isNetworkSlow
								});
							}
						}

						if (isNetworkSlow) {
							clearInterval(this._networkPoller);
							this.initFastNetworkPoller();
						}
					} catch (error) {
						console.error('Poor Network Poll Error:', error);
					}
				};

				// Execute initial poll immediately
				poll();

				// Set up interval using global config
				this._networkPoller = setInterval(poll,
					_oGlobalConfig.network.polling.slowIntervalSeconds * 1000);
			},


			// /**
			//  * Analyzes network performance using historical data
			//  * Uses the same logic as Network Performance History display
			//  * for consistency across the application
			//  * 
			//  * @private
			//  * @returns {Promise<boolean>} Promise resolving to true if network is slow
			//  */
			// analyzeNetworkPerformance: function () {
			// 	// Performance thresholds (matching Network Performance History)
			// 	const VERY_SLOW_THRESHOLD = 2.0;
			// 	const SLOW_THRESHOLD = 1.0;
			// 	const MEDIUM_THRESHOLD = 0.5;

			// 	// Time window configuration
			// 	const INTERVAL_MS = 3 * 60 * 1000;
			// 	const WINDOW_MS = 10 * 60 * 1000;

			// 	return this.getAllStats()  // Mevcut asenkron çağrıyı kullan
			// 		.then(aStats => {
			// 			if (!aStats || aStats.length === 0) {
			// 				console.debug('Network Analysis: No data available');
			// 				return false;
			// 			}

			// 			// Filter relevant records
			// 			const iStartTime = Date.now() - WINDOW_MS;
			// 			const oIntervals = {};

			// 			// Group by intervals
			// 			aStats
			// 				.filter(oStat =>
			// 					oStat.time >= iStartTime &&
			// 					oStat.relative_url &&
			// 					oStat.relative_url.includes('/context') &&
			// 					oStat.network_duration !== undefined
			// 				)
			// 				.forEach(oStat => {
			// 					const iIntervalStart = Math.floor(oStat.time / INTERVAL_MS) * INTERVAL_MS;

			// 					if (!oIntervals[iIntervalStart]) {
			// 						oIntervals[iIntervalStart] = {
			// 							totalDuration: 0,
			// 							count: 0
			// 						};
			// 					}

			// 					oIntervals[iIntervalStart].totalDuration += oStat.network_duration;
			// 					oIntervals[iIntervalStart].count++;
			// 				});

			// 			// Calculate averages for each interval
			// 			const aIntervalAnalysis = Object.entries(oIntervals)
			// 				.map(([sTime, oData]) => ({
			// 					time: parseInt(sTime),
			// 					averageDuration: oData.totalDuration / oData.count,
			// 					requestCount: oData.count
			// 				}))
			// 				.sort((a, b) => b.time - a.time)
			// 				.filter(oInterval => oInterval.requestCount >= 3)
			// 				.slice(0, 3);

			// 			// Log analysis results
			// 			console.debug('Network Performance Analysis:', {
			// 				intervals: aIntervalAnalysis.map(oInterval => ({
			// 					time: new Date(oInterval.time).toISOString(),
			// 					duration: oInterval.averageDuration.toFixed(3) + 's',
			// 					requests: oInterval.requestCount
			// 				})),
			// 				timestamp: _date.now()
			// 			});

			// 			// Calculate final result
			// 			if (aIntervalAnalysis.length > 0) {
			// 				const fAverageDuration = aIntervalAnalysis.reduce(
			// 					(fSum, oInterval) => fSum + oInterval.averageDuration, 0
			// 				) / aIntervalAnalysis.length;

			// 				return fAverageDuration > SLOW_THRESHOLD;
			// 			}

			// 			return false;
			// 		})
			// 		.catch(oError => {
			// 			console.error('Network Analysis Failed:', {
			// 				error: oError,
			// 				timestamp: _date.now()
			// 			});
			// 			return false;
			// 		});
			// },

			// /**
			//  * Starts periodic network performance monitoring
			//  * @private
			//  */
			// startPerformanceMonitoring: function () {
			// 	// Initial check
			// 	this.analyzeNetworkPerformance()
			// 		.then(bIsNetworkSlow => {
			// 			if (this.getState().isNetworkSlow() !== bIsNetworkSlow) {
			// 				this.setState({ slowNetwork: bIsNetworkSlow });
			// 			}
			// 		});

			// 	// Periodic monitoring
			// 	setInterval(() => {
			// 		if (this.getState().hasAutoffline() && !this.getState().isOfflineForced()) {
			// 			this.analyzeNetworkPerformance()
			// 				.then(bIsNetworkSlow => {
			// 					if (this.getState().isNetworkSlow() !== bIsNetworkSlow) {
			// 						this.setState({ slowNetwork: bIsNetworkSlow });
			// 					}
			// 				});
			// 		}
			// 	}, 0.5 * 60 * 1000);  // 3 minutes
			// },

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
			 * Uses queue mechanism for handling concurrent state updates
			 * 
			 * @param {{bForcedOffline: boolean, bAutoOffline: boolean, bSlowNetwork: boolean}} oFlags
			 * Flags indicating the current network state
			 * @returns {Promise} Resolves when state is updated
			 */
			setState: async function (oFlags) {
				var oSelf = this;

				// Add the current state update request to the queue
				_oGlobalConfig.network.stateUpdate.aStateUpdateQueue.push(oFlags);

				// If another state update is already being processed, 
				// just log the queued update and return immediately
				if (_oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length > 1) {
					console.debug('State update in progress, queued update:', {
						flags: oFlags,
						queueLength: _oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length
					});
					return Promise.resolve();
				}

				// Process queued updates while there are items in the queue
				// and the retry limit has not been reached.
				while (_oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length > 0 &&
					_oGlobalConfig.network.stateUpdate.iStateUpdateRetries <
					_oGlobalConfig.network.stateUpdate.iMaxStateUpdateRetries) {
					try {
						// Take the first queued state update for processing
						const oCurrentFlags = _oGlobalConfig.network.stateUpdate.aStateUpdateQueue[0];
						console.debug('Processing state update:', {
							flags: oCurrentFlags,
							queueLength: _oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length,
							retries: _oGlobalConfig.network.stateUpdate.iStateUpdateRetries
						});

						// Update the internal network state based on the flags provided
						const oChanges = _oNetStat._updateState(oCurrentFlags);

						// If there are any changes in the state:
						if (Object.keys(oChanges).length > 0) {
							// Log state change for debugging
							console.debug('Network State Changed:', {
								currentState: _oNetStat.serialize(),
								changes: oChanges,
								timestamp: _date.now()
							});

							// Trigger a global event to notify listeners about the state changes
							$(document).trigger('networkchanged', {
								currentState: _oNetStat,
								changes: oChanges
							});

							// Save the updated state to the "connection" table in IndexedDB

							/**
							 * Problem: Force offline toggle was sometimes not properly changing the application state
							 * This was happening because IndexedDB updates were not being awaited properly, which could
							 * lead to race conditions where the state was not fully persisted before continuing.
							 * 
							 * Solution:
							 * 1. Added proper await for IndexedDB operations
							 * 2. Enhanced logging for better debugging
							 * 3. Added small delay on error to prevent rapid retries
							 * 4. Added state change logging to track transitions
							 * 
							 * These changes ensure state updates are fully completed before moving on,
							 * making the toggle behavior more consistent.
							 *  
							 */
							await _connectionTable.put({
								time: _date.now(),
								state: _oNetStat.serialize()
							});

							// Clean up old states in IndexedDB (older than 10 minutes)
							const oTenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
							oSelf.deleteStatesBefore(oTenMinutesAgo);
						}

						// Remove the successfully processed state update  
						_oGlobalConfig.network.stateUpdate.aStateUpdateQueue.shift();
						// Reset the retry counter after a successful update
						_oGlobalConfig.network.stateUpdate.iStateUpdateRetries = 0;

					} catch (oError) {
						// Increment retry counter on error
						_oGlobalConfig.network.stateUpdate.iStateUpdateRetries++;

						console.error('Error processing state update:', {
							error: oError,
							currentRetries: _oGlobalConfig.network.stateUpdate.iStateUpdateRetries,
							maxRetries: _oGlobalConfig.network.stateUpdate.iMaxStateUpdateRetries,
							queueLength: _oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length,
							timestamp: _date.now()
						});

						// If retry limit reached, clear queue and reset counter
						if (_oGlobalConfig.network.stateUpdate.iStateUpdateRetries >=
							_oGlobalConfig.network.stateUpdate.iMaxStateUpdateRetries) {
							console.warn('Max state update retries reached, clearing queue:', {
								queueLength: _oGlobalConfig.network.stateUpdate.aStateUpdateQueue.length,
								retries: _oGlobalConfig.network.stateUpdate.iStateUpdateRetries,
								timestamp: _date.now()
							});

							_oGlobalConfig.network.stateUpdate.aStateUpdateQueue = [];
							_oGlobalConfig.network.stateUpdate.iStateUpdateRetries = 0;
							return Promise.reject(oError);
						}

						new Promise(resolve);
					}
				}

				return Promise.resolve();
			},

			/**
			 * Retrieves all network speed measurements from storage.
			 * 
			 * @returns {Promise<Array>} Promise resolving to array of measurements:
			 *   - time: Timestamp of measurement
			 *   - speed: Network speed in Mbps
			 *   - mime_type: Content type
			 *   - size: Data size in bytes
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
			 * Retrieves all network state changes including auto-offline, browser-online, 
			 * forced-offline and slow-network states.
			 * 
			 * @returns {Promise<Array>} Network state history
			 */
			getAllStates: function () {
				if (!exfPWA.isAvailable()) return Promise.resolve([]);

				return _connectionTable.toArray()
					.then(connections => connections.map(conn => ({
						time: conn.time,
						state: typeof conn.state === 'string' ?
							JSON.parse(conn.state) : (conn.state || {})
					})))
					.catch(error => {
						console.warn('Failed to get network states:', error);
						return [];
					});
			},

			/**
			 * Save new speed measurement
			 * Automatically triggers cleanup of old stats after successful save
			 * 
			 * @param {Date} oTime - Timestamp of the measurement
			 * @param {number} fSpeed - Speed in Mbps
			 * @param {string} sMimeType - Content type of the request
			 * @param {number} iSize - Size of the transferred data in bytes
			 * 
			 * New Parameters
			 * @param {number} dNetworkDuration - Network-only duration in seconds
			 * @param {number} dTotalDuration - Total duration including server time in seconds
			 * @param {number} dServerTime - Server processing time in milliseconds
			 * @param {string} sMethod - HTTP method used
			 * @param {string} sUrl - Relative URL of the request
			 * @param {number} iRequestSize - Size of request in bits
			 * @param {number} iResponseSize - Size of response in bits
			 * @return {Promise} 
			 */
			saveStat: function (oTime, fSpeed, sMimeType, iSize, dNetworkDuration, dTotalDuration, dServerTime, sMethod, sUrl, iRequestSize, iResponseSize) {
				if (!exfPWA.isAvailable()) {
					return Promise.resolve();
				}

				return _networkStatTable.put({
					time: oTime,
					speed: fSpeed,
					mime_type: sMimeType,
					size: iSize,
					network_duration: dNetworkDuration,    // Network-only time
					total_duration: dTotalDuration,        // Total time including server
					server_time: dServerTime,              // Server processing time
					method: sMethod,
					relative_url: sUrl,
					request_size: iRequestSize,
					response_size: iResponseSize
				}).then(() => {
					// After successful save, clean up old stats
					const oTenMinutesAgo = new Date(Date.now() - 10 * 60 * 1000);
					this.deleteStatsBefore(oTenMinutesAgo)
						.then((iDeletedCount) => {
							if (iDeletedCount > 0) {
								console.debug('Cleaned up old stats:', {
									deletedRecords: iDeletedCount,
									timestamp: _date.now()
								});
							}
						})
						.catch((oError) => {
							console.warn('Failed to cleanup network stats:', oError);
						});

					// Return successful completion of the main operation
					return Promise.resolve();
				}).catch(function (oError) {
					console.warn('Failed to save or cleanup network stats:', oError);
				});
				//
			},

			/**
			 * Delete network speed measurements older than the specified timestamp
			 * 
			 * @param {Date|number} oTimestamp - Delete records before this time
			 * @returns {Promise<number>} Number of deleted records
			 */
			deleteStatsBefore: function (oTimestamp) {
				if (!exfPWA.isAvailable()) {
					return Promise.resolve(0);
				}

				return _networkStatTable
					.where('time')
					.below(oTimestamp)
					.delete()
					.then((iDeletedCount) => {
						if (iDeletedCount > 0) {
							console.debug('Network stats cleanup:', {
								deletedRecords: iDeletedCount,
								cutoffTime: new Date(oTimestamp).toISOString(),
								timestamp: _date.now()
							});
						}
						return iDeletedCount;
					})
					.catch(function (oError) {
						console.warn('Failed to delete old stats:', oError);
						return 0;
					});
			},

			/**
 * Deletes network state records that are older than the specified timestamp
 * This function handles the cleanup of old connection states from IndexedDB
 * 
 * @param {Date|number} oTimestamp - Delete records before this time
 * @returns {Promise<number>} Number of deleted records
 */
			deleteStatesBefore: function (oTimestamp) {
				// Early return if IndexedDB is not available
				if (!exfPWA.isAvailable()) {
					return Promise.resolve(0);
				}

				// Convert timestamp to the consistent format used in the database
				// This ensures correct comparison with stored timestamps
				const formattedTimestamp = _date.normalize(new Date(oTimestamp));

				// Execute the delete operation on the connection table
				return _connectionTable
					.where('time')
					.below(formattedTimestamp)
					.delete()
					.then((iDeletedCount) => {
						// Log successful cleanup operation if any records were deleted
						if (iDeletedCount > 0) {
							console.debug('Network states cleanup:', {
								deletedRecords: iDeletedCount,
								cutoffTime: formattedTimestamp,
								timestamp: _date.now()
							});
						}
						return iDeletedCount;
					})
					.catch((oError) => {
						// Handle and log any errors during the cleanup process
						console.warn('Failed to delete old states:', oError);
						// Return 0 deleted records on error
						return 0;
					});
			},


			/**
			 * Network Performance Analyzer
			 * 
			 * Evaluates current network conditions by analyzing recent API call performance,
			 * specifically focusing on context endpoint response times to determine if the
			 * network should be considered "slow".
			 * 
			 * @function checkNetworkSlow
			 * @async
			 * @returns {Promise<boolean>} Returns true if network is determined to be slow
			 * 
			 * Analysis Parameters:
			 * - Time Window: 3 minutes of recent activity
			 * - Threshold: 1 second for individual request duration
			 * - Slow Network Criteria: >50% of requests exceeding threshold
			 * 
			 * Methodology:
			 * 1. Retrieves recent network statistics from storage
			 * 2. Filters for context API calls within time window
			 * 3. Evaluates network duration against threshold
			 * 4. Calculates percentage of slow requests
			 * 
			 * Key Features:
			 * - Focuses on context API calls as performance indicators
			 * - Uses rolling time window for current state assessment
			 * - Implements percentage-based evaluation system
			 * - Provides detailed performance logging 
			 * - Called by both polling mechanisms (fast/poor)
			 * - Supports auto-offline functionality
			 * - Influences network state transitions
			 */
			checkNetworkSlow: async function () {
				try {
					// Fetch complete network statistics from storage
					const aStats = await this.getAllStats();
					if (!aStats.length) {
						console.debug('Network Assessment: No measurements available');
						return false;
					}

					// Configuration constants for network assessment
					const iSlowThresholdSeconds = 1;        // Network duration threshold for "slow" request
					const iMonitoringWindowMs = 3 * 60 * 1000; // 3 minutes monitoring window
					const fSlowPercentageThreshold = 50.0;    // Percentage threshold for slow network condition

					// Calculate time boundary for recent calls
					const iWindowStartTime = Date.now() - iMonitoringWindowMs;

					// Filter for recent context API calls only
					const aContextCalls = aStats.filter(oStat => {
						return oStat.time >= iWindowStartTime &&
							oStat.relative_url &&
							oStat.relative_url.includes('/context');
					});

					// Exit early if no relevant data available
					if (aContextCalls.length === 0) {
						console.debug('Network Assessment: No recent context calls found');
						return false;
					}

					// Identify calls that exceeded our duration threshold
					const aSlowCalls = aContextCalls.filter(oStat =>
						oStat.network_duration > iSlowThresholdSeconds
					);

					// Calculate percentage of slow calls
					const fSlowPercentage = (aSlowCalls.length / aContextCalls.length) * 100;

					// Network is considered slow if percentage exceeds threshold
					const bIsNetworkSlow = fSlowPercentage >= fSlowPercentageThreshold;

					// Detailed logging for monitoring and debugging
					console.debug('Network Performance Analysis:', {
						totalContextCalls: aContextCalls.length,
						slowCalls: aSlowCalls.length,
						slowPercentage: fSlowPercentage.toFixed(1) + '%',
						averageNetworkDuration: (
							aContextCalls.reduce((fSum, oStat) => fSum + oStat.network_duration, 0) /
							aContextCalls.length
						).toFixed(3) + 's',
						status: bIsNetworkSlow ? 'SLOW' : 'FAST',
						monitoringPeriod: {
							start: new Date(aContextCalls[0]?.time).toISOString(),
							end: new Date(aContextCalls[aContextCalls.length - 1]?.time).toISOString()
						}
					});

					return bIsNetworkSlow;

				} catch (oError) {
					console.error('Network Assessment Failed:', {
						error: oError.message,
						stack: oError.stack,
						timestamp: this._date.now()
					});
					// Default to fast network on error to prevent false positives
					return false;
				}
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
				 * 2. Triggers custom event for UI updates
				 */
				window.addEventListener('online', async () => {
					console.log('Browser detected online state');
					// Trigger the change event without modifying _oNetStat because
					// only the browser online status changed and that is always determined
					// live and not saved in _oNetStat
					$(document).trigger('networkchanged', {
						currentState: _oNetStat,
						changes: {
							browserOnline: true
						}
					});

					// First persist state in connection table for consistency
					// Even if no flags changed, a connection log entry will help
					// tracking when we went online
					await _connectionTable.put({
						time: _date.now(),
						state: _oNetStat.serialize()  // Include current state flags
					}).catch(oError => {
						console.error('Failed to handle online state transition:', oError);
					});
				});

				/**
				 * Offline Event Handler
				 * Triggered when browser loses network connection
				 * 
				 * State Management:
				 * 1. Updates connection table in IndexedDB
				 * 2. Triggers custom event for UI updates
				 */

				window.addEventListener('offline', async () => {
					console.log('Browser detected offline state');
					$(document).trigger('networkchanged', {
						currentState: _oNetStat,
						changes: {
							browserOnline: false
						}
					});

					// Persist state change in connection table
					await _connectionTable.put({
						time: _date.now(),
						state: _oNetStat.serialize()  // Include current state flags
					}).catch(oError => {
						console.error('Failed to handle offline state transition:', oError);
					});
				});


				/**
				 * Network State Change Event Handler
				 * Monitors network state transitions and triggers auto-offline mode when conditions deteriorate.
				 * Only performs analysis when auto-offline is enabled and not in forced offline mode.
				*/
				$(window).on('networkchanged', async function (oEvent, oData) {

					try {
						// Keep reference to network module for proper scoping
						const oNetwork = exfPWA.network;
						const oCurrentState = oData.currentState; //oNetwork.getState();

						// Only analyze network if auto-offline is enabled and not forced offline
						if (oCurrentState.hasAutoffline() && !oCurrentState.isOfflineForced()) {
							const bIsNetworkSlow = await oNetwork.checkNetworkSlow();

							// Check if slow network status changed 
							if (oCurrentState.isNetworkSlow() !== bIsNetworkSlow) {
								await oNetwork.setState({
									slowNetwork: bIsNetworkSlow,
									autoOffline: true // Make sure auto-offline is enabled
								});

								// Log transition
								console.debug('Network Status Transition:', {
									previousState: oCurrentState.toString(),
									newState: oNetwork.getState().toString(),
									isSlowNetwork: bIsNetworkSlow,
									isVirtuallyOffline: oNetwork.getState().isOfflineVirtually(),
									timestamp: _date.now()
								});

								// If network became slow, trigger offline mode
								if (bIsNetworkSlow) {
									console.debug('Transitioning to offline mode due to slow network');
									$(document).trigger('networkchanged', {
										currentState: oNetwork.getState(),
										changes: {
											autoOffline: true,
											slowNetwork: true
										}
									});
								}
							}
						}
					} catch (oError) {
						console.error('Failed to handle network change:', {
							error: oError.message,
							stack: oError.stack,
							timestamp: _date.now()
						});
					}
				});

				// Initialize network polling system
				if (this.getState().hasAutoffline()) {
					this.initPoorNetworkPoller();
					console.debug('Network monitoring started with normal polling');
				}
				else {
					this.initFastNetworkPoller();
				}
			},
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
 * Initialize Network Monitoring System
 * Restores network state from persistent storage and initiates performance monitoring.
 * Starts auto-offline monitoring if enabled, logs initialization status for debugging.
 * Falls back gracefully on initialization errors to ensure system stability.
 */
	_pwa.network.checkState().then(function (oNetStat) {
		if (oNetStat.hasAutoffline()) {
			// Start performance monitoring if auto-offline is enabled
			exfPWA.network.checkNetworkSlow();
		} else {
			// Log that monitoring is disabled
			console.debug('Network Performance Monitoring:', {
				status: 'disabled',
				reason: 'auto-offline not enabled',
				timestamp: _date.now()
			});
		}
	}).catch(function (oError) {
		// Log initialization error but don't crash
		console.error('Network State Initialization Failed:', {
			error: oError.message,
			stack: oError.stack,
			timestamp: _date.now(),
			impact: 'performance monitoring not started'
		});
	});

	return _pwa;
})));
// v1
// v2
//  v 3    

