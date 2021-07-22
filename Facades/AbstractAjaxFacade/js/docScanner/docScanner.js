/*
 * docScanner.js - a document scanner, loading a picture of a document, transform it and save it as a pdf
 */
;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
    typeof define === 'function' && define.amd ? define(factory()) :
    global.docScanner = factory()
}(this, (function () {
	var docScanner = {
		_variables: {
			file: null,
			originalImg: null,
			originalImgSmall: null,
			parentDivId: null,
			pdfFile: null,
			points: null,
			ratio: null,
			transformedImg: null,
			transformedImgSmall: null			
		},
		
		init: function(parentDivId) {			
			docScanner._variables.parentDivId = parentDivId;
			docScanner._switchView("select");			
		},			

		_loadImage: function(file) {
			return new Promise((resolve,reject)=>{
				const url = URL.createObjectURL(file);
				let img = new Image();
				img.onload = ()=>{
					resolve(img);
				};
				img.src = url;
			});
		},
		
		_canvasClick: function(e) {
			var points = docScanner._variables.points;
			var x = e.layerX ? e.layerX : e.pageX - e.target.offsetLeft;
			var y = e.layerY ? e.layerY : e.pageY - e.target.offsetTop;
			for(var i=0; i<points.length; i++) {
				if (Math.pow(points[i].x - x , 2) + Math.pow(points[i].y - y , 2) < 100 ) {
				points[i].selected = true;
				} else {
					if(points[i].selected) {
						points[i].selected = false;
					}
				}
			}
			docScanner._variables.points = points;
		},
		
		_dragCircle: function(e) {
			var points = docScanner._variables.points;
			var pointChanged = false;
			for(var i=0; i<points.length; i++) { 
				if (points[i].selected) {
					var pointChanged = true;
					points[i].x =e.layerX ? e.layerX : e.pageX - e.target.offsetLeft;
					points[i].y =e.layerY ? e.layerY : e.pageY - e.target.offsetTop;
				}
			}
			if (pointChanged === true) {
				docScanner._variables.points = points;
				docScanner._draw();
			}
		},
		
		_stopDragging: function(e) {
			var points = docScanner._variables.points;
			for(var i=0; i<points.length; i++) {
				points[i].selected = false;
			}
			docScanner._variables.points = points;
		},
		
		_draw: function() {
			canvas = $("#inputImage")[0];
			docScanner._drawImageScaled(docScanner._variables.originalImg, canvas);
			docScanner._drawPoints(docScanner._variables.points, canvas);
		},
		
		_drawPoints: function(points, canvas) {
			let context = canvas.getContext('2d');
			for(var i=0; i<points.length; i++) {
				let size = 20;
				var circle = points[i];
				context.globalAlpha = 0.85;
				context.beginPath();
				context.arc(circle.x, circle.y, size-(5*(i-1)), 0, Math.PI*2);
				context.fillStyle = "yellow";
				context.strokeStyle = "yellow";
				context.lineWidth = 3;
				context.fill();
				context.stroke();
				context.beginPath();
				context.moveTo(circle.x, circle.y);
				context.lineTo( points[i-1>=0?i-1:3].x,  points[i-1>=0?i-1:3].y);
				context.stroke();
			}
		},

		_drawImageScaled: function(img, canvasElem) {
			var canvas = canvasElem;
			var ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			var canWidth = $("#" + canvas.id).width()
			var canHeight = $("#" + canvas.id).height()
			canvas.width = canWidth;
			canvas.height = canHeight;
			var imgWidth = img.naturalWidth;
			var imgHeight = img.naturalHeight;
			var hRatio =  canWidth / imgWidth;
			var vRatio =  canHeight / imgHeight;
			var ratio  = Math.min ( hRatio, vRatio );
			docScanner._variables.ratio = ratio;
			var scaledWidth = Math.floor(imgWidth * ratio);
			var scaledHeight = Math.floor(imgHeight * ratio); 
			ctx.drawImage(img,
			0,0, //start at 0,0 (top left of the image)
			imgWidth, imgHeight, //get the full image
			0,0, //place it in the top left of the canvas
			scaledWidth, scaledHeight); //scale it to the size of the canvas);
		},
		
		reset: function() {
			if (docScanner._variables.file !== null) {
				docScanner.processImage(docScanner._variables.file);
			}
			return;
		},

		processImage: async function(file) {
			docScanner._variables.file = file;
			docScanner._switchView("clip");
			let canvas = $("#inputImage")[0];
			const img = await docScanner._loadImage(file);
			docScanner._variables.originalImg = img;
			docScanner._drawImageScaled(img, canvas);	

			let image = cv.imread(canvas);
			docScanner._variables.originalImgSmall = image;
			let edges = new cv.Mat();
			var edged = cv.Canny(image,edges,100,200);
			//cv.imshow($("#inputImage")[0],edges);
			let contours = new cv.MatVector();
			let hierarchy = new cv.Mat();
			cv.findContours(edges,contours,hierarchy,cv.RETR_LIST,cv.CHAIN_APPROX_SIMPLE);
			let cnts = []
			
			//find contours that have 4 edges
			for (let i=0;i<contours.size();i++) {
				const tmp = contours.get(i);
				const peri = cv.arcLength(tmp,true);
				let approx = new cv.Mat();
				let result = {
					area:cv.contourArea(tmp),
					points:[]
				};
				cv.approxPolyDP(tmp,approx,0.02*peri,true);
				const pointsData = approx.data32S;
				for (let j=0;j<pointsData.length/2;j++) {
					result.points.push({x:pointsData[2*j],y:pointsData[2*j+1]});
				}
				if (result.points.length===4) {
					cnts.push(result);
				}
			}
			//sort contours by area big->small 
			cnts.sort((a,b) => b.area-a.area);
			let points = cnts[0].points;
			//sort points by coordinates top left is first, then counter clockwise
			points.sort((a,b)=>(a.x+a.y)-(b.x+b.y));
			var tmpPoint = points[1];
			points[1] = points[2];
			points[2] = points[3];
			points[3] = tmpPoint;

			if (img.width > img.height) {
				tmpPoints = [];
				tmpPoints.push(points[3]);
				tmpPoints.push(points[2]);
				tmpPoints.push(points[1]);
				tmpPoints.push(points[0]);
				points = tmpPoints;
			} 
			
			if (points[1].y - points[0].y < 200) {
				points = [
					{x: 10, y: 10, selected: false},
					{x: 10, y: canvas.height - 10, selected: false},
					{x: canvas.width - 10, y: canvas.height - 10, selected: false},
					{x: canvas.width - 10 , y: 10, selected: false}
				];
			}
			
			docScanner._variables.points = points;
			canvas.onmousedown = docScanner._canvasClick;
			canvas.onmouseup = docScanner._stopDragging;
			canvas.onmouseout = docScanner._stopDragging;
			canvas.onmousemove = docScanner._dragCircle;
			docScanner._addTouchEvents(canvas)
			docScanner._drawPoints(points, canvas);
		},
		
		_addTouchEvents: function(canvas) {
			canvas.addEventListener("touchstart", function (e) {
				mousePos = docScanner._getTouchPos(canvas, e);
				var touch = e.touches[0];
				var mouseEvent = new MouseEvent("mousedown", {
					clientX: touch.clientX,
					clientY: touch.clientY
				});
			canvas.dispatchEvent(mouseEvent);
			}, false);
			canvas.addEventListener("touchend", function (e) {
				var mouseEvent = new MouseEvent("mouseup", {});
				canvas.dispatchEvent(mouseEvent);
			}, false);
			canvas.addEventListener("touchmove", function (e) {
				var touch = e.touches[0];
				var mouseEvent = new MouseEvent("mousemove", {
					clientX: touch.clientX,
					clientY: touch.clientY
				});
				canvas.dispatchEvent(mouseEvent);
			}, false);
			
		},
		
		_getTouchPos: function(canvasDom, touchEvent) {
			var rect = canvasDom.getBoundingClientRect();
			return {
				x: touchEvent.touches[0].clientX - rect.left,
				y: touchEvent.touches[0].clientY - rect.top
			};
		},

		transformImage: function() {
			var points = docScanner._variables.points;
			var ratio = docScanner._variables.ratio;
			var tl = points[0],bl=points[1],br=points[2],tr=points[3]; //stands for top-left,top-right ....
			// transform coordinates from point back to original image size
			tl.x = tl.x * (1/ratio);
			tl.y = tl.y * (1/ratio);
			tr.x = tr.x * (1/ratio);
			tr.y = tr.y * (1/ratio);
			bl.x = bl.x * (1/ratio);
			bl.y = bl.y * (1/ratio);
			br.x = br.x * (1/ratio);
			br.y = br.y * (1/ratio);
			const width = Math.floor(Math.max(
				Math.sqrt((br.x-bl.x)**2 + (br.y-bl.y)**2),
				Math.sqrt((tr.x-tl.x)**2 + (tr.y-tl.y)**2),
			));
			const height = Math.floor(Math.max(
				Math.sqrt((tr.x-br.x)**2 + (tr.y-br.y)**2),
				Math.sqrt((tl.x-bl.x)**2 + (tl.y-bl.y)**2),
			));
			
			//do image transformation
			const from = cv.matFromArray(4,1,cv.CV_32FC2,[tl.x,tl.y,tr.x,tr.y,br.x,br.y,bl.x,bl.y]);
			const to = cv.matFromArray(4,1,cv.CV_32FC2,[0,0,width-1,0,width-1,height-1,0,height-1]);
			const M = cv.getPerspectiveTransform(from,to);
			var out = new cv.Mat();
			var size = new cv.Size();
			size.width = width;
			size.height = height;
			var img = docScanner._variables.originalImg;
			cv.warpPerspective(cv.imread(docScanner._variables.originalImg),out,M,size);
			
			docScanner._switchView("save");
			canvas = $("#transformedImageSmall")[0]
			//TODO landscape pictures that get turned to potrait are cut at the bottom, yet not sure why
			//var newCanWidth = width/height*$("#transformedImageSmall").height()
			//$("#transformedImageSmall").width(newCanWidth)
			//var canHeight = $("#transformedImageSmall").height(height)
			//canvas.width = newCanWidth;
			//canvas.height = $("#transformedImageSmall").height();			
			//show small version of transformed image
			cv.imshow("transformedImageSmall",out);
			
			//add original transformed image to hidden canvas
			$("#" + docScanner._variables.parentDivId).append('<canvas id="transformedImage" hidden></canvas>');
			$("#transformedImage").height(height);
			$("#transformedImage")[0].height = height;
			$("#transformedImage").width(width);
			$("#transformedImage")[0].width = width;
			cv.imshow("transformedImage",out);
		},

		_switchView: function(name) {
			$("#" + docScanner._variables.parentDivId).empty();
			switch (name) {
			 case "select":
				$("#" + docScanner._variables.parentDivId).append('<div class="view-clip docScanner"><canvas id="selectImage" style="height: 100%; width: 100%;"></canvas></div>');
				var canvas = $("#selectImage")[0];
				var ctx = canvas.getContext('2d');
				ctx.textBaseline = 'middle';
				ctx.textAlign = "center";
				ctx.font = '1rem Arial';
				ctx.fillStyle = "rgba(0, 0, 0, 0.2)";
				ctx.fillText('Bild hinzufügen', canvas.width/2, canvas.height/2);
				break;
			 case "clip":
				$("#" + docScanner._variables.parentDivId).append('<div class="view-clip docScanner"><canvas id="inputImage" style="height: 100%; width: 100%;"></canvas></div>');
				break;
			 case "save":
				$("#" + docScanner._variables.parentDivId).append('<div class="view-save docScanner"><canvas id="transformedImageSmall" style="height: 100%; width: 100%;"></canvas></div>');
				break;
			}
		},

		downloadPdf: function() {
			var canvas = $("#transformedImage")[0];
			var imgData = canvas.toDataURL("image/jpeg", 0.5);
			var orient = 'p'; //define orientation
			if (canvas.width > canvas.height) {
				orient = 'l'; //if canvas is wider than high, change orientation to landscape
			}
			var pdf = new jsPDF({
			unit: "px",
			format: [canvas.width, canvas.height],
			orientation: orient,
			hotfixes: ["px_scaling"],
			compress: true});
			pdf.addImage(imgData, 'JPEG', 0, 0, canvas.width, canvas.height, undefined, 'SLOW');
			pdf.save("download.pdf");
		}
	};
	return docScanner;
})));