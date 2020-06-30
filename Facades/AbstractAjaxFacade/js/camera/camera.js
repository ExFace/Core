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
			videoOverlay: null,
			takePhotoButton: null,
			hideCameraButton: null,
			switchCameraButton: null,
			hintText: null,
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
				navigator.mediaDevices
				.enumerateDevices()
				.then(function (devices) {
					devices.forEach(function (device) {
						if (device.kind === 'video') {
							device.kind = 'videoinput';
						}
						if (device.kind === 'videoinput') {
							camera._variables.devices.push(device.deviceId);
							console.log('videocam: ', device.label, device.deviceId);
						}
					});
					console.log('Devices', camera._variables.devices);
					resolve(camera._variables.devices.length);
				})
				.catch(function (err) {
					console.log(err.name + ': ' + err.message);
					reject("No devices found");
				});
			});
		},
		
		/**
		 * @private
		 * @return void
		 */
		_initCameraUI: function() {  
			camera._variables.devices = [];
			camera._variables.currentCameraIdx = 0;
			camera._getDevices().then(function(count) {				    
				if (count <= 1) {	
					camera._variables.switchCameraButton.disabled = true;
					camera._variables.switchCameraButton.style.color = 'rgba(100, 100, 100, 0.5)';
				} else {
					camera._variables.switchCameraButton.disabled = false;
					camera._variables.switchCameraButton.style.color = 'rgba(0, 0, 0, 1)';
				}
				//start CameraStream				
				camera._initCameraStream(camera._variables.currentCameraIdx);
				if (camera._options.showHints === true && camera._options.hints.length !== 0) {
					camera._variables.hintTimer = camera._cycleHints();
					camera._variables.hintText.parentNode.style.display = "block";
				}
			}, function(error) {
				throw new Error(error);
			})			
			return;
		},
		
		/**
		 * @private
		 * @param int deviceIdx
		 * @return void
		 */
		_initCameraStream: function (deviceIdx) {
			// stop any active streams in the window
			camera._endStreams();
			  
			// we ask for a square resolution, it will cropped on top (landscape)
			// or cropped at the sides (landscape)
			var size = 1280;
			console.log('Starting on Device', camera._variables.devices[deviceIdx]);
			var constraints = {
				video: { deviceId: camera._variables.devices[deviceIdx] }
			};
			
			navigator.mediaDevices
			.getUserMedia(constraints)
			.then(handleSuccess)
			.catch(handleError);
			
			function handleSuccess(stream) {
				window.stream = stream; // make stream available to browser console
				camera._variables.video.srcObject = stream;
				const track = window.stream.getVideoTracks()[0];
				const settings = track.getSettings();
				str = JSON.stringify(settings, null, 4);
				//console.log('settings ' + str);
				camera._variables.currentCameraIdx = deviceIdx;
				camera._options.onStreamStart(camera._variables.devices[deviceIdx]);
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
		_closeCamera: function() {
			camera._endStreams();
			camera._variables.parent.style.display = "none";
			return;
		},
		
		/**
		 * @private
		 * @return void
		 */
		_endStreams: function() {
			if (window.stream) {
				window.stream.getTracks().forEach(function (track) {
					console.log('Stopping', track);
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
		 * @param int hintIdx
		 * @return void
		 */
		_setHint: function(hintIdx) {
			camera._variables.hintText.value = camera._options.hints[hintIdx];
			camera._variables.currentHintIdx = hintIdx;
			return;
		},
		
		init: function(parentId, options) {
			var parent = document.getElementById(parentId);
			
			if(parent === false) {
				throw new Error("Parent element not found");
			}
			
			var defaults = {
					showCycleCamera: true,
					showTakePhoto: true,
					showCloseCamera: true,
					showHints: true,
					onOpen: function() {},
					onClose: function() {},
					onCycle: function(cameraIdx) {},
					onStreamStart: function(deviceId) {},
					onStreamEnd: function() {},
					hints: []
			}
			
			camera._options = camera._mergeOptions(defaults, options);
			
			var switchCameraButtonId = 'switchCameraButton_' + parentId;
			var hideCameraButtonId = 'hideCameraButton_' + parentId;
			var takePhotoButtonId = 'takePhotoButton_' + parentId;
			var hintTextId = 'hintTextArea_' + parentId;
			
			var html = '<div id="Cameracontainer_'+parentId+'">'+
                '<div id="vid_container_'+parentId+'" class="vid_container">'+
                	'<div class="hintTextDiv"><input id = "'+hintTextId+'" class="hintText" disabled></input></div>'+
                    '<video id="video_'+parentId+'" class="video" autoplay playsinline></video>'+
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
				var cameraIdx;
				var amountCameras = camera._variables.devices.length;
				if(camera._variables.currentCameraIdx + 1 >= amountCameras) {
					cameraIdx = 0;
				} else {
					cameraIdx = camera._variables.currentCameraIdx + 1;
				}
				onCycle = function(cameraIdx) {},
				camera._initCameraStream(cameraIdx);
			});
			
			window.addEventListener(
				'orientationchange',
				function () {
					// iOS doesn't have screen.orientation, so fallback to window.orientation.
					// screen.orientation will
					if (screen.orientation) angle = screen.orientation.angle;
					else angle = window.orientation;
					
					var guiControls = document.getElementById('gui_controls'+parentId).classList;
					var vidContainer = document.getElementById('vid_container'+parentId).classList;
					
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
			camera._variables.parent.style.display = "inline-block";
			camera._initCameraUI();
			camera._options.onOpen();
		},
		
		close: function() {
			camera._options.onClose();
			camera._closeCamera();
			if (camera._variables.hintTimer) {
				clearInterval(camera._variables.hintTimer);
			}
		},
		
		getCurrentDeviceId: function() {
			return camera._variables.devices[_currentCameraIdx];
		},
	};
	return camera;
})));