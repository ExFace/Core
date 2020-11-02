;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.moment) :
    typeof define === 'function' && define.amd ? define(factory(global.moment)) :
    global.exfTools = factory(global.moment)
}(this, (function (moment) { 'use strict';
	//ICU format to moment format
	(function(m){
		m.fn.formatICU = function(format){
			var that = this;

			return this.format(_ICUFormatToMoment (format));
		};
	}(moment));
	
	function _ICUFormatToMoment (format) {
		var formatMap = {
			'yyyy': 'YYYY',
			'yy': 'YY',
			'y': 'Y',			
			'Q': 'Q',
			'MMMM': 'MMMM',
			'MMM': 'MMM',
			'MM': 'MM',
			'M': 'M',
			'ww': 'ww',
			'w': 'w',
			'dd': 'DD',
			'd': 'D',
			'D': 'DDD',
			'EEEEEE':'dd',
			'EEEE': 'dddd',
			'EEE': 'ddd',
			'EE': 'ddd',
			'E': 'ddd',
			'eeeeee': 'dd',
			'eeee': 'dddd',
			'eee': 'ddd',
			'ee': 'E',
			'e': 'E',
			'a': 'a',
			'hh': 'hh',
			'h': 'h',
			'HH': 'HH',
			'H': 'H',
			'kk': 'kk',
			'k': 'k',
			'mm': 'mm',
			'm': 'm',
			'ss': 'ss',
			's': 's',
			'SSSS': 'SSSS',
			'SSS': 'SSS',
			'SS': 'SS',
			'S': 'S',
			'xxx': 'Z',	
			'xx': 'ZZ'	
		};
			
		var RegExpString = '';
		for (var key in formatMap) {
			if (!formatMap.hasOwnProperty(key)) continue;
			RegExpString += key + "|";
		}
	
		var formatEx = new RegExp("(" + RegExpString + ")", "g");
		
		return format.replace(formatEx, function(ICUStr){
			if (formatMap[ICUStr] !== undefined) {
				return formatMap[ICUStr];
			}
			return '';
		});
	};
	
	var _mDateParseDefaultParams = {
			lang: {
				now: ["today","now"],
				yesterday: ["yesterday"],
				tomorrow: ["tomorrow"],
				day: ["d", "day", "days"],
				week: ["w", "week", "weeks"],
				month:["m", "month", "months"],
				year: ["y", "year", "years"]
				
			}
	};
	
	function _validateDate (yyyy, MM, dd) {
		var _m = moment([yyyy, MM-1, dd]); 
		return _m.isValid();		
	};
	
	function _validateTime (hh, mm, ss) {
		var validHours = false;
		var validMinutes = false;
		var validSeconds = false;
		if (hh >= 0 && hh < 24) {
			validHours = true;
		}
		if (mm >= 0 && mm < 60) {
			validMinutes = true;
		}
		if (ss >=0 && ss < 60) {
			validSeconds = true;
		}
		if (validHours === true && validMinutes === true && validSeconds == true) {
			return true;
		} else {
			return false;
		}
	}
	
	function _buildRegexString (ParseParams) {
		var _string = '';
		var langObject = _mDateParseDefaultParams.lang;
		if (ParseParams !== undefined) {
			if (ParseParams.lang) {
				langObject = ParseParams.lang;
			}
		}
		for (var key in langObject) {
			if (!langObject.hasOwnProperty(key)) continue;
			_string += langObject[key].join('|') + "|";
		}
		return _string;
	};
	
	function _findKey (_exp, ParseParams) {
		var langObject = _mDateParseDefaultParams.lang;
		if (ParseParams !== undefined) {
			if (ParseParams.lang) {
				langObject = ParseParams.lang;
			}
		}
		for (var key in langObject) {
			if (!langObject.hasOwnProperty(key)) continue;
			var _result = langObject[key].findIndex(item => _exp.toLowerCase() === item.toLowerCase());
			if (_result !== -1) {
				return key;
			}
		}
		return null;
	};
	
	function _dataRowsCompare(row1, row2) {
		var rows1 = Array.isArray(row1) === true ? row1 : [row1]; 
		var rows2 = Array.isArray(row2) === true ? row2 : [row2]; 
		rows1.forEach(function(r1, idx){
			var r2 = rows2[idx];
			for (var i in r1) {
				if (r1[i] !== r2[i]) {
					return false;
				}
			} 
		});
		return true;
	}
	
	return {		
		/**
		 * Working with date strings and objects
		 * 
		 * 
		 * 
		 */
		date: {
			/**
			 * Parses a string date into a JS Date object.
			 * 
			 * Accepts as input:
		     * - empty values,
		     * - numbers (seconds)
		     * - parsable string dates (ISO string, human-readable string)
		     * - relative dates (+/-1d, etc.)
		     *
		     * Examples:
		     * - "31.12.2019" -> 2019-12-31
		     * - "31.12" -> 2019-12-31
		     * - "now" -> 2019-12-31
		     * - "-2w" -> 2019-12-17
		     * 
			 * @param {string|NULL} [sDate]
			 * @param {string} [dateFormat]
			 * @param {Object} [ParseParams]
			 * 
			 * @returns {Date}
			 */
			parse: function(sDate, dateFormat, ParseParams) {
				// date ist ein String und wird zu einem date-Objekt geparst
				
				// Variablen initialisieren
				var match = null;
				var dateParsed = false;
				var dateValid = false;
				var time = undefined;
				var output = null;
				
				if (sDate === '' || sDate === null) {
					return output;
				}
				
				if (dateFormat !== undefined) {
					output = moment(sDate, _ICUFormatToMoment(dateFormat), true);
					if (output.isValid()) {
						return output.toDate();
					}
				}

				// hh:mm:ss , Thh:mm:ss
				if (!dateParsed && (match = /[T ](\d{2}:\d{2}:\d{2})/.exec(sDate)) != null) {
					time = match[1];
				} else if (!dateParsed && (match = / (\d{2}:\d{2})/.exec(sDate)) != null) {
				// hh:mm
					time = match[1];
				}
				
				// dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy
				if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})/.exec(sDate)) != null) {
					var yyyy = Number(match[3]);
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d
				if (!dateParsed && (match = /(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					var yyyy = Number(match[1]);
					var MM = Number(match[2]);
					var dd = Number(match[3]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy
				if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2})/.exec(sDate)) != null) {
					var yyyy = 2000 + Number(match[3]);
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d
				if (!dateParsed && (match = /(\d{2})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					var yyyy = 2000 + Number(match[1]);
					var MM = Number(match[2]);
					var dd = Number(match[3]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// dd.MM, dd-MM, dd/MM, d.M, d-M, d/M
				if (!dateParsed && (match = /(\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					var yyyy = moment().year;
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// ddMMyyyy
				if (!dateParsed && (match = /^(\d{2})(\d{2})(\d{4})$/.exec(sDate)) != null) {
					var yyyy = Number(match[3]);
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// ddMMyy
				if (!dateParsed && (match = /^(\d{2})(\d{2})(\d{2})$/.exec(sDate)) != null) {
					var yyyy = 2000 + Number(match[3]);
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				// ddMM
				if (!dateParsed && (match = /^(\d{2})(\d{2})$/.exec(sDate)) != null) {
					var yyyy = moment().year;
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				
				// Ausgabe des geparsten Wertes
				if (dateParsed && dateValid) {
					
					output = new Date(yyyy + '-' + MM + '-' + dd + (time !== undefined ? ' ' + time : ''));
					return output;
				}
				// check for +/-?, digit?, expession in _mDateParseDefaultParams.lang?
				var regexString = _buildRegexString(ParseParams);
				var regExp = new RegExp("^([+\-]?\\d{0,3})(" + regexString + ")$", "i");
				match = regExp.exec(sDate);
				output = null;
				if (!dateParsed && (match !== null)) {
					output = moment();
					var key = null;
					var exp = match[2];
					var number = Number(match[1]);
					if (number !== 0 && exp === '') {
						key ="day";
					}
					if (number === 0 && exp === '') {
						key = "now";
					}
					if (number === 0 && exp !== '') {
						number = 1;
					}
					if (key === null) {
						key = _findKey(exp, ParseParams);
					}
					switch (key) {
						case "now": break;
						case "yesterday":
							output.subtract(1, 'days');
							break;
						case "tomorrow":
							output.add(1, 'days');
							break;
						case "day":							
							output.add(number, 'days');							
							break;
						case "week":
							output.add(number, 'weeks');
							break;
						case "month":
							output.add(number, 'months');							
							break;
						case "year":
							output.add(number, 'years');
							break;
						default: output = null;
					}
					dateParsed = true;
				}
				
				// (+/-)? ... (H/h/M/m/S/s)?
		        if (!dateParsed && (match = /^([+\-]?\d{1,3})([HhMmSs]?)$/.exec(sDate)) != null) {
		            output = moment();
		            switch (match[2].toUpperCase()) {
		                case "H":
		                case "":
		                	output.add(Number(match[1]), 'hours');
		                    break;
		                case "M":
		                	output.add(Number(match[1]), 'minutes');
		                    break;
		                case "S":
		                	output.add(Number(match[1]), 'seconds');
		                    break;
		            }
		            dateParsed = true;
		        }
				
				// Ausgabe des geparsten Wertes
				if (dateParsed && output !== null && output.isValid()) {
					return output.toDate();
				}
			
			return output;
			},
			
			/**
			 * Formats the given normalized date string or JS Date object according to the given ICU format
			 * 
			 * @param {string|Date} sDate
			 * @param {string} [sICUFormat]
			 * 
			 * @return {string}
			 */
			format: function(sDate, sICUFormat) {
				if (sDate !== null && sDate !== undefined && sDate !== '') {
					if (sICUFormat === undefined) {
						return moment(sDate).format('L');
					} else {
						return moment(sDate).formatICU(sICUFormat);
					}
				}
				return sDate;
			},
			
			validate: function (sDate) {				
				return true;						
			}
		},
		
		/**
		 * Working with time strings
		 * 
		 * 
		 * 
		 */
		time: {
			/**
			 * 
			 * @returns {string}
			 */
			parse: function(sTime, timeFormat) {
				// sTime ist ein String und wird zu einem date-Objekt geparst
		        
		        // Variablen initialisieren
		        var match = null;
		        var timeParsed = false;
		        var timeValid = false;
		        var output = null;
		        
		        if (sTime === '' || sTime === null) {
					return output;
				}
		        
		        if (timeFormat !== undefined) {
					output = moment(sTime, _ICUFormatToMoment(timeFormat), true);
					if (output.isValid()) {
						if (timeFormat.indexOf('a') !== '-1') {
							return output.format('hh:mm:ss a');
						} else {
							return output.format('HH:mm:ss');
						}
					}
				}
		        
		     // HH:mm , HH:mm:ss, HH:mm am/pm, HH:mm:ss am/pm
		        if (!timeParsed && (match = /(\d{1,2}):?(\d{1,2}):?(\d{1,2})?\040?(pm|am)?$/i.exec(sTime)) != null) {
		        	var hh, mm, ss, am_pm;
		            hh = Number(match[1]);
		            mm = Number(match[2]);
		            ss = Number(match[3]);
		            if (match[4]) {
		            	am_pm = match[4].toUpperCase();
		            }
		            if (isNaN(ss)) {
		            	ss = 0;
		            }
		            timeParsed = true;
		            timeValid = _validateTime (hh, mm, ss) ;
		        }
		        
		        // Ausgabe des geparsten Wertes
		        if (timeParsed && timeValid) {
		        	return hh + ':' + mm + ':' + ss + (am_pm !== undefined ? ' ' + am_pm : '');
		        }
		        
		        // (+/-)? ... (H/h/M/m/S/s)?
		        if (!timeParsed && (match = /^([+\-]?\d{1,3})([HhMmSs]?)$/.exec(sTime)) != null) {
		            output = moment();
		            switch (match[2].toUpperCase()) {
		                case "H":
		                case "":
		                	output.add(Number(match[1]), 'hours');
		                    break;
		                case "M":
		                	output.add(Number(match[1]), 'minutes');
		                    break;
		                case "S":
		                	output.add(Number(match[1]), 'seconds');
		                    break;
		            }
		            timeParsed = true;
		            timeValid = true;
		        }
		        
		        // Ausgabe des geparsten Wertes
		        if (timeParsed && timeValid) {
		        	var hh = output.hour();
		        	var mm = output.minute();
		        	var ss = output.second();
		        	return output.format('HH:mm:ss');
		        	//return  hh + ':' + mm + ':' + ss
		        }
		        
		        return output;
			},
			
			format: function(sTime, sICUFormat) {
				if (sTime !== null && sTime !== undefined && sTime !== '') {
					if (sICUFormat === undefined) {
						return moment('1970-01-01 ' + sTime).format('LTS');
					} else {
						return moment('1970-01-01 ' + sTime).formatICU(sICUFormat);
					}
				}
				return sTime;
			},
			
			formatObject: function(dateTime, sICUFormat) {
				if (dateTime !== null && dateTime !== undefined && dateTime !== '') {
					if (sICUFormat === undefined) {
						return moment(dateTime).format('LTS');
					} else {
						return moment(dateTime).formatICU(sICUFormat);
					}
				}
				return '';
			},
			
			validate: function (sTime) {
				return true;
			}
		},
		
		/**
		 * Utilities for JS data sheets
		 * 
		 * 
		 * 
		 * 
		 */
		data: {
			compareRows: function(row1, row2) {
				return _dataRowsCompare(row1, row2);
			}
		},
		
		/**
		 * Clipboard interaction
		 * 
		 * 
		 * 
		 */
		clipboard: {
			_fallbackCreateTextarea: function(value) {
				var oTextArea = document.createElement("textarea");
			  
				// Avoid scrolling to bottom
			 	oTextArea.style.top = "0";
			 	oTextArea.style.left = "0";
			 	oTextArea.style.position = "fixed";
			 	
			 	return oTextArea;
			},
			
			_fallbackCopyTextToClipboard: function(text) {
				var bSuccess;
				var oTextArea = this._fallbackCreateTextarea();

				oTextArea.value = text;
				document.body.appendChild(oTextArea);
				oTextArea.focus();
				oTextArea.select();

				bSuccess = document.execCommand('copy');
				if (bSuccess === false) {
					throw 'Copy not supported!';
				}

				document.body.removeChild(oTextArea);
			},
			
			_fallbackPasteTextFromClipboard: function() {
				var sValue, bSuccess;
				var oTextArea = this._fallbackCreateTextarea();
				
				document.body.appendChild(oTextArea);
				oTextArea.focus();
				
				bSuccess = document.execCommand("paste");
				if (bSuccess === false) {
					throw 'Paste not supported!';
				} else {
					sValue = oTextArea.textContent;
				}

				document.body.removeChild(oTextArea);
				return sValue;
			},
			
			copyText: function(text) {
			  if (!navigator.clipboard) {
			    this._fallbackCopyTextToClipboard(text);
			    return;
			  }
			  navigator.clipboard.writeText(text).then(function() {
			    //console.log('Async: Copying to clipboard was successful!');
			  }, function(err) {
			    throw err;
			  });
			},
			
			pasteText: async function(text) {
				if (true /*!navigator.clipboard*/) {
				    return this._fallbackPasteTextFromClipboard();
				}
				return await navigator.clipboard.readText();
			}
		}
	}
})));