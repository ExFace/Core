;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.moment) :
    typeof define === 'function' && define.amd ? define(factory(global.moment)) :
    global.exfTools = factory(global.moment)
}(this, (function (moment) { 'use strict';
	// moment.js + PHP formatter
	(function (m) {
		/*
		 * PHP => moment.js
		 * Will take a php date format and convert it into a JS format for moment
		 * http://www.php.net/manual/en/function.date.php
		 * http://momentjs.com/docs/#/displaying/format/
		 */
		var formatMap = {
				d: 'DD',
				D: 'ddd',
				j: 'D',
				l: 'dddd',
				N: 'E',
				S: function () {
					return '[' + this.format('Do').replace(/\d*/g, '') + ']';
				},
				w: 'd',
				z: function () {
					return this.format('DDD') - 1;
				},
				W: 'W',
				F: 'MMMM',
				m: 'MM',
				M: 'MMM',
				n: 'M',
				t: function () {
					return this.daysInMonth();
				},
				L: function () {
					return this.isLeapYear() ? 1 : 0;
				},
				o: 'GGGG',
				Y: 'YYYY',
				y: 'YY',
				a: 'a',
				A: 'A',
				B: function () {
					var thisUTC = this.clone().utc(),
					// Shamelessly stolen from http://javascript.about.com/library/blswatch.htm
						swatch = ((thisUTC.hours() + 1) % 24) + (thisUTC.minutes() / 60) + (thisUTC.seconds() / 3600);
					return Math.floor(swatch * 1000 / 24);
				},
				g: 'h',
				G: 'H',
				h: 'hh',
				H: 'HH',
				i: 'mm',
				s: 'ss',
				u: '[u]', // not sure if moment has this
				e: '[e]', // moment does not have this
				I: function () {
					return this.isDST() ? 1 : 0;
				},
				O: 'ZZ',
				P: 'Z',
				T: '[T]', // deprecated in moment
				Z: function () {
					return parseInt(this.format('ZZ'), 10) * 36;
				},
				c: 'YYYY-MM-DD[T]HH:mm:ssZ',
				r: 'ddd, DD MMM YYYY HH:mm:ss ZZ',
				U: 'X'
			},
			formatEx = /[dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrU]/g;

		moment.fn.formatPHP = function (format) {
			var that = this;

			return this.format(format.replace(formatEx, function (phpStr) {
				return typeof formatMap[phpStr] === 'function' ? formatMap[phpStr].call(that) : formatMap[phpStr];
			}));
		};
	}(moment));
	
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
		var _m = moment([yyyy, MM, dd]); 
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
	
	return {
		date: {
			/**
			 * @return Date
			 */
			parse: function(sDate, ParseParams) {
				// date ist ein String und wird zu einem date-Objekt geparst
				console.log('sDate', sDate);
				
				// Variablen initialisieren
				var match = null;
				var dateParsed = false;
				var dateValid = false;
				var time = undefined;
				var output = null;
				
				if (sDate === '' || sDate === null) {
					return output;
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
					var key = null;;
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
				
				// Ausgabe des geparsten Wertes
				if (dateParsed && output !== null && output.isValid()) {
					return output.toDate();
				}
			
			return output;
			},
			
			/**
			 * @return string
			 */
			format: function(sDate, sPhpFormat = null) {
				var output = null;
				if (sPhpFormat === null) {
					sPhpFormat = 'd.m.Y';
				}
				if (sDate !== null && sDate !== undefined) {
					output = moment(sDate).formatPHP(sPhpFormat);
				}
				return output;
			},
			
			validate: function (sDate) {
				//return true;
				if (sDate !== null && sDate !== undefined) {
					return moment(sDate).isValid();;
				}
				return true;
			}
		},
		time: {
			/**
			 * 
			 * @return string
			 */
			parse: function(sTime) {
				// sTime ist ein String und wird zu einem date-Objekt geparst
		        
		        // Variablen initialisieren
		        var match = null;
		        var timeParsed = false;
		        var timeValid = false;
		        var output = null;
		        
		     // HH:mm , HH:mm:ss
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
		            return output.format('HH:mm:ss');
		        } else {
		            return null;
		        }
			},
			
			format: function(sTime, sPhpFormat) {
				if (sPhpFormat === undefined) {
					sPhpFormat = 'H:i:s';
				}
				var output = null;
				if (sTime !== null && sTime !== undefined) {
					output = moment('1970-01-01 ' + sTime).formatPHP(sPhpFormat);
				}
				return output;
			},
			
			validate: function (sTime) {
				return true;
				//return moment(sDate).isValid();
			}
		}		
	}
})));