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
	
	function _buildRegexString (ParseParams = null) {
		var _string = '';
		var langObject = _mDateParseDefaultParams.lang;
		if (ParseParams !== null) {
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
	
	function _findKey (_exp, ParseParams = null) {
		var langObject = _mDateParseDefaultParams.lang;
		if (ParseParams !== null) {
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
			
			parse: function(sDate, ParseParams = null) {
				console.log("sDate: ",sDate);
				// date ist ein String und wird zu einem date-Objekt geparst
				
				// Variablen initialisieren
				var match = null;
				var dateParsed = false;
				var dateValid = false;
				var time;
				var output;

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
					var yyyy = (new Date()).getFullYear();
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
					var yyyy = (new Date()).getFullYear();
					var MM = Number(match[2]);
					var dd = Number(match[1]);
					dateParsed = true;
					dateValid = _validateDate(yyyy, MM, dd);
				}
				
				// Ausgabe des geparsten Wertes
				if (dateParsed && dateValid) {
					
					//output = moment([yyyy, MM, dd, ]);
					//console.log('parsedDate: ', output.formatPHP("d.m.Y"));
					//output = output.toDate();
					output = new Date(yyyy + '-' + MM + '-' + dd + (time !== undefined ? ' ' + time : ''));
					console.log('parsedDate: ', output);
					return output;
				}
				// check for +/-?, digit?, expession in _mDateParseDefaultParams.lang?
				var _regexString = _buildRegexString(ParseParams);
				var _regExp = new RegExp("^([+\-]?\\d{0,3})(" + _regexString + ")$", "i");
				match = _regExp.exec(sDate);
				output = null;
				if (!dateParsed && (match !== null)) {
					output = moment();
					var _exp = match[2];
					var _number = Number(match[1]);
					if (_number !== 0 && _exp === '') {
						var _key ="day";
					} else {
						var _key = _findKey(_exp, ParseParams);
					}				
					switch (_key) {
						case "now": break;
						case "yesterday":
							output.subtract(1, 'days');
							break;
						case "tomorrow":
							output.add(1, 'days');
							break;
						case "day":
							if (_number === 0) {
								output.add(1, 'days');
							} else {
								output.add(_number, 'days')
							}
							break;
						case "week":
							if (_number === 0) {
								output.add(1, 'weeks');
							} else {
								output.add(_number, 'weeks')
							}
							break;
						case "month":
							if (_number === 0) {
								output.add(1, 'months');
							} else {
								output.add(_number, 'months')
							}
							break;
						case "year":
							if (_number === 0) {
								output.add(1, 'years');
							} else {
								output.add(_number, 'years')
							}
							break;
						default: output = null;
					}
					dateParsed = true;
				}
				
				// Ausgabe des geparsten Wertes
				if (dateParsed && output !== null && output.isValid()) {
					//console.log('parsedDate: ', moment(output).formatPHP("d.m.Y"));
					console.log(output.toDate());
					return output.toDate();
				}
			
			return output;
			},
			format: function(sDate, sPhpFormat = null) {
				if (sPhpFormat === null) {
					sPhpFormat = 'd.m.Y';
				}
				var output = moment(sDate).formatPHP(sPhpFormat);
				return output;
			},
			validate: function (sDate) {
				//return true;
				return moment(sDate).isValid();
			}
		}
	}
})));