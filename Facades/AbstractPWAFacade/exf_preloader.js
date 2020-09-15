const exfPreloader = {};
(function(){
	
	var _preloader = this;
		
	var _db = function() {
		var dexie = new Dexie('exf-preload');
		dexie.version(1).stores({
			'preloads': 'id, object',
			'actionQueue': 'id, object, action'
		});
		dexie.open();
		return dexie;
	}();
	
	var _preloadTable = _db.table('preloads');
	var _actionsTable = _db.table('actionQueue');
	
	this.addPreload = function(sAlias, aDataCols, aImageCols, sPageAlias, sWidgetId){		
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
		.catch(error => {
			exfLauncher.contextBar.getComponent().showErrorDialog('See console for details.', 'Preload sync failed!');
		});
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
				exfLauncher.contextBar.getComponent().showAjaxErrorDialog(jqXHR);
				return textStatus;
			}
		);
	};
	
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
	
	this.reset = function() {
		var clear = _preloadTable.toArray()
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
		return clear;
	};
	
	this.showStorage = async function() {
		console.log('Storage clicked');
		//check if service worker supported
		/*if (!('serviceWorker' in navigator)) {
			exfLauncher.contextBar.getComponent().showErrorDialog('Service worker not available.', 'Storage quota failed!');
			return;
		}*/		
		var dialog = new sap.m.Dialog({title: "Storage quota", icon: "sap-icon://unwired"});
		var button = new sap.m.Button({
			icon: 'sap-icon://font-awesome/close',
            text: "Close",
            press: function() {dialog.close();},
        });
		dialog.addButton(button);
		list = new sap.m.List({});
		//check if possible to acces storage (means https connection)
		if (navigator.storage) {
			var promise = navigator.storage.estimate()
			.then(function(estimate) {				
				list = new sap.m.List({
					items: [
						new sap.m.GroupHeaderListItem({
							title: 'Overview',
							upperCase: false
						}),
						new sap.m.DisplayListItem({
							label: "Total Space",
							value: Number.parseFloat(estimate.quota/1024/1024).toFixed(2) + ' MB'
						}),
						new sap.m.DisplayListItem({
							label: "Used Space",
							value: Number.parseFloat(estimate.usage/1024/1024).toFixed(2) + ' MB'
						}),
						new sap.m.DisplayListItem({
							label: "Percentage Used",
							value: Number.parseFloat(100/estimate.quota*estimate.usage).toFixed(2) + ' %'
						})
					]
				});
				if (estimate.usageDetails) {
					list.addItem(new sap.m.GroupHeaderListItem({
							title: 'Details',
							upperCase: false
					}));
					Object.keys(estimate.usageDetails).forEach(function(key) {
						list.addItem(new sap.m.DisplayListItem({
								label: key,
								value: Number.parseFloat(estimate.usageDetails[key]/1024/1024).toFixed(2) + ' MB'
							})
						);
					});
				}				
			})
			.catch(function(error) {
				console.error(error);
				list.addItem(new sap.m.GroupHeaderListItem({
					title: 'Storage quota failed! See console for details.',
					upperCase: false
				}))
			});
			//wait for the promise to resolve
			await promise;
		} else {
			list.addItem(new sap.m.GroupHeaderListItem({
				title: 'Overview showing used storage space not possible!',
				upperCase: false
			}))
		}
		promise = _preloadTable.toArray()
		.then(function(dbContent){
			list.addItem(new sap.m.GroupHeaderListItem({
				title: 'Synced content',
				upperCase: false
			}));
			console.log('Content', dbContent);
			var oTable = new sap.m.Table({
				fixedLayout: false,
				columns: [
		            new sap.m.Column({
		                header: new sap.m.Label({
		                    text: 'ID'
		                })
		            }),
		            new sap.m.Column({
		                header: new sap.m.Label({
		                    text: 'Object'
		                })
		            }),
		            new sap.m.Column({
		                header: new sap.m.Label({
		                    text: 'WidgetID'
		                })
		            }),
		            new sap.m.Column({
		                header: new sap.m.Label({
		                    text: 'Datasets'
		                })
		            }),
		            ,
		            new sap.m.Column({
		                header: new sap.m.Label({
		                    text: 'Last synced'
		                })
		            })
		        ]
			});
			dbContent.forEach(function(element) {
				oRow = new sap.m.ColumnListItem();
				oRow.addCell(new sap.m.Text({text: element.id}));
				oRow.addCell(new sap.m.Text({text: element.object}));
				oRow.addCell(new sap.m.Text({text: element.widget}));
				if (element.response && element.response.rows) {
					oRow.addCell(new sap.m.Text({text: element.response.rows.length}));
					oRow.addCell(new sap.m.Text({text: new Date(element.lastSync).toLocaleString()}));
				} else {
					oRow.addCell(new sap.m.Text({text: '0'}));

					oRow.addCell(new sap.m.Text({text: 'not synced'}));
				}
				oTable.addItem(oRow);						
			});
			dialog.addContent(list);
			dialog.addContent(oTable);	
		})
		.catch(function(error) {
			console.error(error);
			list.addItem(new sap.m.GroupHeaderListItem({
				title: 'Overview showing db content not possbile! See console for details.',
				upperCase: false
			}))
			dialog.addContent(list);				
		})
		//wait for the promise to resolve
		await promise;
		dialog.open();
		return;
	};
	
	this.addAction = function(offlineAction, objectAlias) {
		var date = (+ new Date());
		var data = {
			id: date,
			object: objectAlias,
			action: offlineAction.data.action,
			request: offlineAction,
			triggered: new Date(date).toLocaleString(),
			status: 'offline',
			tries: 0,
			synced: 'not snyced'
		};
		if (offlineAction.headers) {
			data.headers = offlineAction.headers
		}
		return _actionsTable.put(data)
		.then(function(){
			/*navigator.serviceWorker.ready
			.then(registration => registration.sync.register('OfflineActionSync'))
			.then(() => console.log("Registered background sync"))
			.catch(err => console.error("Error registering background sync", err))*/
		});
	};
	
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
	
	this.syncActionAll = function(selectedIds) {
		var promises = [];
		selectedIds.forEach(function(id){
			promises.push(_preloader.syncAction(id));
		});
		return Promise.all(promises);
	};
	
	this.syncAction = function(id) {
		return _actionsTable.get(id)
		.then(function(element){
			console.log('Sync action');
			var params = element.request.data;
			params = _preloader.encodeJson(params);
			var request = fetch(element.request.url, {
				method: element.request.type,
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: params
			})
			.then(response => {
				if (response.ok) {
					return response.json()
					.then(function(data){
						console.log('Success:', response);
						var date = (+ new Date());
						return _actionsTable.update(element.id, {
							status: 'success',
							tries: element.tries + 1,
							response: data,
							synced: new Date(date).toLocaleString()
						})
						.then(function (updated){					
							if (updated) {
								console.log ("Action was updated");
							} else {
								console.log ("Nothing was updated - there was no action with id: ", element.id);
							}
						});
					});
				}
				if (response.statusText === 'timeout' || response.status === 0 || response.status >= 500) {
					console.log('Timeout sync action');
					return _actionsTable.update(element.id, {
						tries: element.tries + 1,
						response: response.statusText
					});
				}
				return response.json()
				.then(function(data){
					console.log('Error Server response');
					return _actionsTable.update(element.id, {
						status: 'error',
						tries: element.tries + 1,
						response: data
					});
				});
			})
			.catch((error) => {
			  console.error('Error:', error);
			});
			return request;
		});
	};
	
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
	
	this.deleteActionAll = function(selectedIds) {
		var promises = [];
		selectedIds.forEach(function(id){
			promises.push(_preloader.deleteAction(id));
		});
		return Promise.all(promises);
	};
	
	this.deleteAction = function(id) {
		return _actionsTable.delete(id)
	}
	
	this.updateQueueCount = function() {
		return exfPreloader.getActionQueueData('offline')
		.then(function(data) {
			var count = data.length;
			exfLauncher.getShell().getModel().setProperty("/_network/queueCnt", count);
			console.log('Queue count updated');
		})
	}
	
	this.getActionsTable = function() {
		return _actionsTable;
	}
	
	this.getPreloadTable = function() {
		return _preloadTable;
	}
}).apply(exfPreloader);