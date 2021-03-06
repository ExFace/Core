/*
 * camera.js - a camera template in the design of the native android camera
 */
;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
    typeof define === 'function' && define.amd ? define(factory()) :
    global.camera = factory()
}(this, (function () {
	var camera = {			
		_variables: {
			video: null,
			videoId: null,
			videoOverlay: null,
			takePhotoButton: null,
			hideCameraButton: null,
			switchCameraButton: null,
			hintText: null,
			errorText: null,
			currentCameraIdx: 0,
			devices: [],
			parent: null,
			currentHintIdx: 0,
			hintTimer: null
		},
		
		_options: {},		
		
		/**
		 * @private
		 * @return promise
		 */
		_getDevices: function() {				
			return new Promise(function (resolve, reject) {
				var mediaDevices = navigator.mediaDevices;
				if (mediaDevices === undefined) {
					reject("You need a secure 'https' connection to use the cameras of this device!");
				} else {
					mediaDevices.enumerateDevices()
					.then(function (devices) {
						devices.forEach(function (device) {
							if (device.kind === 'video') {
								device.kind = 'videoinput';
							}
							if (device.kind === 'videoinput') {
								camera._variables.devices.push(device.deviceId);
							}
						});
						resolve(camera._variables.devices.length);
					})
					.catch(function (err) {
						console.error(err.name + ': ' + err.message);
						reject("No cameras found on this device!");
					});
				}
			});
		},
		
		/**
		 * @private
		 * @return void
		 */
		_initCameraUI: function() {  
			camera._variables.devices = [];
			camera._variables.currentCameraIdx = 0;
			//intial prompt to ask for permissions to use cameras
			navigator.mediaDevices.getUserMedia({audio: false, video: true})
			.then(handleSuccess)
			.catch(handleError);
			
			function handleSuccess(stream) {
				stream.getTracks().forEach(function (track) {
					track.stop();
				});
				camera._getDevices().then(function(count) {
					if (count <= 1) {	
						camera._disableSwitchCameraButton();
					} else {
						camera._enableSwitchCameraButton();
					}
					//start CameraStream
					camera._initCameraStream(null);				
				}, function(error) {
					camera._disableSwitchCameraButton();
					camera._showErrorMessage(error);
					console.error(error);
				})			
				return;
			}
			
			function handleError(error) {
				camera._disableSwitchCameraButton();
				camera._showErrorMessage('Can not access device cameras! Grant permission to use cameras for this app!');
				console.error(error);
				return;
			}
		},
		
		/**
		 * @private
		 * @param int deviceIdx
		 * @return void
		 */
		_initCameraStream: function (deviceIdx = null) {
			// stop any active streams in the window
			camera._endStreams();
			var constraints;
			
			//console.log('Starting on Device', camera._variables.devices[deviceIdx]);
			if (deviceIdx === null) {
				constraints = {
					audio: false,
					video: {
						facingMode: 'environment'
					}
				};				
			} else {
				constraints = {
					video: { deviceId: camera._variables.devices[deviceIdx] }
				};
			}
			
			navigator.mediaDevices
			.getUserMedia(constraints)
			.then(handleSuccess)
			.catch(handleError);
			
			function handleSuccess(stream) {
				window.stream = stream; // make stream available to browser console
				camera._variables.video.srcObject = stream;
				const track = window.stream.getVideoTracks()[0];
				const settings = track.getSettings();
				//str = JSON.stringify(settings, null, 4);
				//console.log('settings ' + str);
				var deviceId = settings.deviceId;
				var cameraIdx = camera._variables.devices.indexOf(deviceId);
				if (cameraIdx === -1) {
					camera._disableSwitchCameraButton();
					camera._endStreams();
					camera._showErrorMessage('No camera stream found');
					return;
				}
				if (camera._options.showHints === true && camera._options.hints.length !== 0) {
					camera._variables.hintText.style.display = "block";
					camera._variables.hintTimer = camera._cycleHints();
				}
				camera._variables.currentCameraIdx = cameraIdx;
				camera._options.onStreamStart(camera._variables.devices[cameraIdx], camera._variables.videoId);
			}
			
			function handleError(error) {
				console.error('getUserMedia() error: ', error);
			}
			return;
		},

		/**
		 * @private
		 * @return function
		 */
		_createClickFeedbackUI: function() {
			// in order to give feedback that we actually pressed a button.
			// we trigger a almost black overlay
			return function () {
				var overlay = camera._variables.videoOverlay;
				var overlayVisibility = false;
				var timeOut = 80;
				
				function setFalseAgain() {
					overlayVisibility = false;
					overlay.style.display = 'none';
				}
				if (overlayVisibility == false) {
					overlayVisibility = true;
					overlay.style.display = 'block';
					setTimeout(setFalseAgain, timeOut);
				}
			};
		},
		
		/**
	     * @private
	     * @param Object oDefaults
	     * @param Object oOptions
	     * @return Object
	     */
		_mergeOptions: function(oDefaults, oOptions){
			var oExtended = {};
			var prop;
			for (prop in oDefaults){
				if (Object.prototype.hasOwnProperty.call(oDefaults, prop)){
					oExtended[prop] = oDefaults[prop];
				}
			}			
			for (prop in oOptions){
				if (Object.prototype.hasOwnProperty.call(oOptions, prop)){
					oExtended[prop] = oOptions[prop];
				}
			}			
			return oExtended;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_endStreams: function() {
			if (window.stream) {
				window.stream.getTracks().forEach(function (track) {
					//console.log('Stopping', track);
					track.stop();
				});
			}
			camera._options.onStreamEnd();
			return;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_cycleHints: function() {
			if (camera._variables.hintTimer) {
				return camera._variables.hintTimer;
			}
			var amountHints = camera._options.hints.length;
			if (amountHints === 0) {
				return;
			}
			camera._setHint(0);
			function run(amountHints) {
				hintIdx = camera._variables.currentHintIdx;
				if (hintIdx + 1 >= amountHints) {
					hintIdx = 0;
				} else {
					hintIdx = hintIdx + 1;
				}
				camera._setHint(hintIdx);
			}
			return setInterval(run, 10000, amountHints);
		},
		
		/**
		 * @private
		 * @return void
		 */
		_cycleHintsStop: function () {
			if (camera._variables.hintTimer) {
				clearInterval(camera._variables.hintTimer);
				camera._variables.hintTimer = null;
			}
			camera._variables.hintText.style.display = 'none';
			return;
		},
		
		/**
		 * @private
		 * @param int hintIdx
		 * @return void
		 */
		_setHint: function(hintIdx) {
			camera._variables.hintText.innerHTML = camera._options.hints[hintIdx];
			camera._variables.currentHintIdx = hintIdx;
			return;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_cycleCamera: function () {
			var cameraIdx;
			var amountCameras = camera._variables.devices.length;
			if(camera._variables.currentCameraIdx + 1 >= amountCameras) {
				cameraIdx = 0;
			} else {
				cameraIdx = camera._variables.currentCameraIdx + 1;
			}
			onCycle = function(cameraIdx) {},
			camera._initCameraStream(cameraIdx);
			return;
		},
		
		/**
		 * @private
		 * @param string message
		 * @return void
		 */
		_showErrorMessage: function(message) {
			camera._cycleHintsStop();
			camera._variables.hintText.style.display = 'none';
			camera._variables.errorText.style.display = 'block';
			camera._variables.errorText.innerHTML = message;
			return;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_disableSwitchCameraButton: function() {
			camera._variables.switchCameraButton.disabled = true;
			camera._variables.switchCameraButton.style.color = 'rgba(100, 100, 100, 0.7)';
			return;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_enableSwitchCameraButton: function() {
			camera._variables.switchCameraButton.disabled = false;
			camera._variables.switchCameraButton.style.color = 'rgba(255, 255, 255, 1)';
			return;			
		},
		
		init: function(parentId, options) {
			var parent = document.getElementById(parentId);
			if (parent === null) {
				console.error("Parent element with id '" + parentId + "' not found for camera element!");
				return;
			}
			
			var defaults = {
					showCycleCamera: true,
					showTakePhoto: true,
					showCloseCamera: true,
					showHints: true,
					onOpen: function() {},
					onClose: function() {},
					onCycle: function(cameraIdx) {},
					onStreamStart: function(deviceId, videoId) {},
					onStreamEnd: function() {},
					hints: []
			}
			
			camera._options = camera._mergeOptions(defaults, options);
			
			var switchCameraButtonId = 'switchCameraButton_' + parentId;
			var hideCameraButtonId = 'hideCameraButton_' + parentId;
			var takePhotoButtonId = 'takePhotoButton_' + parentId;
			var hintTextId = 'hintText_' + parentId;
			var errorTextId = 'errorText_' + parentId;
			var videoId = 'video_' + parentId;
			camera._variables.videoId = videoId;
			
			var html = '<div id="Cameracontainer_'+parentId+'">'+
                '<div id="vid_container_'+parentId+'" class="vid_container">'+
                	'<div class="hintText" id = "'+hintTextId+'"></div>'+
                	'<div class="errorText" id = "'+errorTextId+'"></div>'+
                    '<video id="'+videoId+'" class="video" autoplay playsinline></video>'+
                    '<div id="video_overlay_'+parentId+'" class="video_overlay"></div>'+
                '</div>'+
                '<div id="gui_controls_'+parentId+'" class="gui_controls">'+	                	
                    '<button id="'+switchCameraButtonId+'" class="switchCameraButton cameraButton" name="switch Camera" type="button"><i class="fa fa-refresh" aria-hidden="true" style="font-size: 36px;"></i></button>'+
                    '<button id="'+takePhotoButtonId+'" class="takePhotoButton cameraButton" name="take Photo" type="button"><i class="fa fa-camera" aria-hidden="true" style="font-size: 52px;"></i></button>'+
                    '<button id="'+hideCameraButtonId+'" class="hideCameraButton cameraButton" name="hide Camera" type="button"><i class="fa fa-times" aria-hidden="true" style="font-size: 36px;"></i></button>'+
                '</div>'+
            '</div>';
			
			parent.innerHTML += html;
			camera._variables.parent = parent;
			
			
			var takeSnapshotUI = camera._createClickFeedbackUI();
			camera._variables.video = document.getElementById('video_'+parentId);
			camera._variables.videoOverlay = document.getElementById('video_overlay_'+parentId);
			camera._variables.takePhotoButton = document.getElementById(takePhotoButtonId);
			camera._variables.hideCameraButton = document.getElementById(hideCameraButtonId);
			camera._variables.switchCameraButton = document.getElementById(switchCameraButtonId);
			camera._variables.hintText = document.getElementById(hintTextId);
			camera._variables.errorText = document.getElementById(errorTextId);
			
			if (camera._options.showCycleCamera === false) {
				camera._variables.switchCameraButton.style.display = "none";
			}
			if (camera._options.showTakePhoto === false) {
				camera._variables.takePhotoButton.style.display = "none";
			}
			if (camera._options.showCloseCamera === false) {
				camera._variables.hideCameraButton.style.display = "none";
			}
			  
			camera._variables.takePhotoButton.addEventListener('click', function () {
				takeSnapshotUI();
			});
			  
			camera._variables.hideCameraButton.addEventListener('click', function () {
				camera.close();				
			});
			  
			camera._variables.switchCameraButton.addEventListener('click', function () {
				camera._cycleCamera();
			});
			
			window.addEventListener(
				'orientationchange',
				function () {
					// iOS doesn't have screen.orientation, so fallback to window.orientation.
					// screen.orientation will
					if (screen.orientation) angle = screen.orientation.angle;
					else angle = window.orientation;
					
					var guiControls = document.getElementById('gui_controls_'+parentId).classList;
					var vidContainer = document.getElementById('vid_container_'+parentId).classList;
					
					if (angle == 270 || angle == -90) {
						guiControls.add('left');
						vidContainer.add('left');
					} else {
						if (guiControls.contains('left')) guiControls.remove('left');
						if (vidContainer.contains('left')) vidContainer.remove('left');
					}					
					//0   portrait-primary
					//180 portrait-secondary device is down under
					//90  landscape-primary  buttons at the right
					//270 landscape-secondary buttons at the left
					},
				false,
			);
		},
		
		open: function() {
			if (camera._variables.parent === null) {
				console.warn('Camera not initialized. Call camera.init before camera.open!');
				return;
			}
			camera._variables.parent.style.display = 'inline-block';
			camera._initCameraUI();
			camera._options.onOpen();
		},
		
		close: function() {
			camera._options.onClose();
			camera._endStreams();
			camera._cycleHintsStop();
			camera._variables.errorText.style.display = 'none';
			camera._variables.parent.style.display = 'none';
		},
	};
	return camera;
})));