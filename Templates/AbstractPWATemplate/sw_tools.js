/**
 * ServiceWorker toolbox for caching POST requests and other non-standard tasks.
 * 
 * Available as swTools under to global scope if imported.
 * 
 * Dependencies:
 * - EcmaScript 6
 * - Dexie (IndexedDB wrapper)
 * 
 * @author Andrej Kabachnik
 */
if (swTools == undefined) {
	var swTools = {
		/**
		 * Serializes a Request into a plain JS object.
		 * 
		 * Source: https://github.com/mozilla/serviceworker-cookbook/blob/master/request-deferrer/service-worker.js
		 * 
		 * @param request
		 * @returns Promise
		 */ 
		serializeRequest: function (request) {
			  var headers = {};
			  // `for(... of ...)` is ES6 notation but current browsers supporting SW, support this
			  // notation as well and this is the only way of retrieving all the headers.
			  for (var entry of request.headers.entries()) {
			    headers[entry[0]] = entry[1];
			  }
			  var serialized = {
			    url: request.url,
			    headers: headers,
			    method: request.method,
			    mode: request.mode,
			    credentials: request.credentials,
			    cache: request.cache,
			    redirect: request.redirect,
			    referrer: request.referrer
			  };
			
			  // Only if method is not `GET` or `HEAD` is the request allowed to have body.
			  if (request.method !== 'GET' && request.method !== 'HEAD') {
			    return request.clone().text().then(function(body) {
			      serialized.body = body;
			      return Promise.resolve(serialized);
			    });
			  }
			  return Promise.resolve(serialized);
		},

		/**
		 * Creates a Request from it's serialized version.
		 * 
		 * @param data
		 * @returns Promise
		 */ 
		deserializeRequest: function (data) {
			return Promise.resolve(new Request(data.url, data));
		},
		
		/**
		 * Serializes a Response into a plain JS object
		 * 
		 * @param response
		 * @returns Promise
		 */ 
		serializeResponse: function (response) {
			  var headers = {};
			  // `for(... of ...)` is ES6 notation but current browsers supporting SW, support this
			  // notation as well and this is the only way of retrieving all the headers.
			  for (var entry of response.headers.entries()) {
			    headers[entry[0]] = entry[1];
			  }
			  var serialized = {
			    headers: headers,
			    status: response.status,
			    statusText: response.statusText
			  };
			
			  return response.clone().text().then(function(body) {
			      serialized.body = body;
			      return Promise.resolve(serialized);
			  });
		},

		/**
		 * Creates a Response from it's serialized version
		 * 
		 * @param data
		 * @returns Promise
		 */ 
		deserializeResponse: function (data) {
			return Promise.resolve(new Response(data.body, data));
		},
		
		/**
		 * Cache API
		 */
		cache: {
			
			/**
			 * Saves the given request-response-pair in the cache.
			 * 
			 * @param request
			 * @param response
			 * 
			 * @return Promise
			 */
			put: function(request, response) {
				var key, data;
				swTools
				.serializeRequest(request.clone())
				.then(function(serializedRequest){
					key = serializedRequest;
					return swTools
					.serializeResponse(response.clone());
				}).then(function(serializedResponse) {
					data = serializedResponse;
					var entry = {
						key: JSON.stringify(key),
						response: data,
						timestamp: Date.now()
					};
					swTools._dexie.cache
					.add(entry)
					.catch(function(error){
						swTools._dexie.cache.update(entry.key, entry);
					});
				});
			},
			
			/**
			 * Returns the cached response for the given request or undefined for a cache miss.
			 * 
			 * @param request
			 * 
			 * @return Promise
			 */
			match: function(request) {
				return swTools
				.serializeRequest(request.clone())
				.then(function(serializedRequest) {
					var key = JSON.stringify(serializedRequest);
					return swTools._dexie.cache.get(key);
				}).then(function(data){
					if (data) {
						return swTools.deserializeResponse(data.response);
					} else {
						return new Response('', {status: 503, statusText: 'Service Unavailable'});
					}
				});
			}
		},
		
		_dexie: function(){
			var db = new Dexie("sw-tools");
	        db.version(1).stores({
	            cache: 'key,response,timestamp'
	        });
	        return db;
		}()
	}
}