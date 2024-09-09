;(function (global, factory) {
    typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory(global.moment) :
    typeof define === 'function' && define.amd ? define(factory(global.moment)) :
    global.exfTools = factory(global.moment)
}(this, (function (moment) { 'use strict';
	//ICU format to moment format
	if (moment !== undefined) {
		(function(m){
			m.fn.formatICU = function(format){
				return this.format(_ICUFormatToMoment (format));
			};
		}(moment));
	}
	
	/**
	 * Translates the ICU format syntax into moment.js syntax
	 * @param {string} [sFormatICU]
     * @return {string}
	 */
	function _ICUFormatToMoment (sFormatICU) {
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
		var sRegExp = '';
		var oRegExp = '';
		var sFormatMoment = '';
		// Find escaped sequences in the ICU format and replace them by a neutral `%%`.
		var aEscaped = sFormatICU.match(/\'[^\']*\'/g) || [];
		sFormatICU = sFormatICU.replace(/\'[^\']*\'/g, '%%');
		
		// Replace symbols using a regular expression generated from the symbol map
		for (var key in formatMap) {
			if (!formatMap.hasOwnProperty(key)) continue;
			sRegExp += key + "|";
		}
		oRegExp = new RegExp("(" + sRegExp + ")", "g");
		sFormatMoment = sFormatICU.replace(oRegExp, function(ICUStr){
			if (formatMap[ICUStr] !== undefined) {
				return formatMap[ICUStr];
			}
			return '';
		});
		
		// Replace each of the escape-placeholders with its original value
		// Translate the ICU escape syntax (single quotes) into moment escape (square braces)
		aEscaped.forEach(function(sEscapedICU){
			var sEscapedMoment = '';
			if (sEscapedICU === "''") {
				sEscapedMoment = "'";
			} else {
				sEscapedMoment = sEscapedICU.replace(/\'/, '[').replace(/\'/, ']');
			}
			sFormatMoment = sFormatMoment.replace(/%%/, sEscapedMoment);
		});
		
		return sFormatMoment;
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
	
	function _normalizeDateInterval (sInterval, ParseParams) {
		var langObject = _mDateParseDefaultParams.lang;
		if (ParseParams !== undefined) {
			if (ParseParams.lang) {
				langObject = ParseParams.lang;
			}
		}
		for (var key in langObject) {
			if (!langObject.hasOwnProperty(key)) continue;
			var _result = langObject[key].findIndex(item => sInterval.toLowerCase() === item.toLowerCase());
			if (_result !== -1) {
				return key;
			}
		}
		return null;
	};
	
	/**
	 * Returns an array like ['-1d', '-1', 'd'] for the expresison '-1d'
	 * 
	 * @param {string} sExpr
	 * @param {object} [oParserParams]
	 * @returns {array}
	 */
	function _matchDateRelative (sExpr, oParserParams) {
		return (new RegExp("^([+\-]?\\d{0,3})(" + _buildRegexString(oParserParams) + ")$", "i")).exec(sExpr);
	};
	
	/**
	 * Returns an array like ['-1h', '-1', 'h'] for the expresison '-1h'
	 *
	 * @param {string} sExpr
	 * @returns {array}
	 */
	function _matchTimeRelative (sExpr) {
		return /^([+\-]?\d{1,3})([HhMmSs]?)$/.exec(sExpr);
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
	
	var exfTools = {		
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
		     * Returns NULL for invalid date values
		     *
		     * Examples:
		     * - "31.12.2019" -> 2019-12-31
		     * - "31.12" -> 2019-12-31
		     * - "now" -> 2019-12-31
		     * - "-2w" -> 2019-12-17
		     * - "asdf" -> null
		     * 
			 * @param {string|NULL} [sDate]
			 * @param {string} [sDateFormat]
			 * @param {Object} [oParserParams]
			 * 
			 * @returns {Date|NULL}
			 */
			parse: function(sDate, sDateFormat, oParserParams) {
				// date ist ein String und wird zu einem date-Objekt geparst
				
				// Variablen initialisieren
				var aMatches = null;
				var bParsed = false;
				var bValid = false;
				var sTime = undefined;
				var oMoment = null;
				var iYYYY, iMM, iDD;
				var sHH, sMM, sSS;
				var aTime;
				var sIntervalType, iIntervals;
				
				if (sDate === '' || sDate === null) {
					return null;
				}
				
				if (sDateFormat !== undefined) {
					oMoment = moment(sDate, _ICUFormatToMoment(sDateFormat), true);
					if (oMoment.isValid()) {
						return oMoment.toDate();
					}
				}

				// hh:mm:ss , Thh:mm:ss
				if (!bParsed && (aMatches = /(\d{2}:\d{2}:\d{2})/.exec(sDate)) != null) {
					sTime = aMatches[1];
				} else if (!bParsed && (aMatches = / (\d{2}:\d{2})/.exec(sDate)) != null) {
				// hh:mm
					sTime = aMatches[1];
				}
				if (sTime != undefined) {					
					aTime = sTime.split(':');
					sHH = aTime[0];
					sMM = aTime[1];
					sSS = aTime[2] !== undefined ? aTime[2] : '00';
					sTime = 'T' + sHH + ':' + sMM + ':' + sSS;
				} else {
					sTime = 'T12:00:00';
				}
				
				// dd.MM.yyyy, dd-MM-yyyy, dd/MM/yyyy, d.M.yyyy, d-M-yyyy, d/M/yyyy
				if (!bParsed && (aMatches = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})/.exec(sDate)) != null) {
					iYYYY = Number(aMatches[3]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d
				if (!bParsed && (aMatches = /(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = Number(aMatches[1]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[3]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy
				if (!bParsed && (aMatches = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2})/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(aMatches[3]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d
				if (!bParsed && (aMatches = /(\d{2})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(aMatches[1]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[3]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// dd.MM, dd-MM, dd/MM, d.M, d-M, d/M
				if (!bParsed && (aMatches = /(\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = moment().year();
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMMyyyy
				if (!bParsed && (aMatches = /^(\d{2})(\d{2})(\d{4})$/.exec(sDate)) != null) {
					iYYYY = Number(aMatches[3]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMMyy
				if (!bParsed && (aMatches = /^(\d{2})(\d{2})(\d{2})$/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(aMatches[3]);
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMM
				if (!bParsed && (aMatches = /^(\d{2})(\d{2})$/.exec(sDate)) != null) {
					iYYYY = moment().year();
					iMM = Number(aMatches[2]);
					iDD = Number(aMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				
				// Ausgabe des geparsten Wertes
				if (bParsed && bValid) {
					var sMM = iMM.toString();
					var sDD = iDD.toString();
					var sYYYY = iYYYY.toString();
					sMM = sMM.padStart(2, '0');
					sDD = sDD.padStart(2, '0');
					sYYYY = sYYYY.padStart(4, '0');
					return new Date(sYYYY + '-' + sMM + '-' + sDD + sTime);
				}
				
				// check for +/-?, digit?, expession in _mDateParseDefaultParams.lang?
				aMatches = _matchDateRelative(sDate, oParserParams);
				oMoment = null;
				if (! bParsed && (aMatches !== null)) {
					oMoment = moment();
					sIntervalType = aMatches[2];
					iIntervals = Number(aMatches[1]);
					if (iIntervals !== 0 && sIntervalType === '') {
						sIntervalType ="day";
					}
					if (iIntervals === 0 && sIntervalType === '') {
						sIntervalType = "now";
					}
					if (iIntervals === 0 && sIntervalType !== '') {
						iIntervals = 1;
					}
					
					sIntervalType = _normalizeDateInterval(sIntervalType, oParserParams);
						
					switch (sIntervalType) {
						case "now": break;
						case "yesterday":
							oMoment.subtract(1, 'days');
							break;
						case "tomorrow":
							oMoment.add(1, 'days');
							break;
						case "day":							
							oMoment.add(iIntervals, 'days');							
							break;
						case "week":
							oMoment.add(iIntervals, 'weeks');
							break;
						case "month":
							oMoment.add(iIntervals, 'months');							
							break;
						case "year":
							oMoment.add(iIntervals, 'years');
							break;
						default: oMoment = null;
					}
					bParsed = true;
				}
				
				// (+/-)? ... (H/h/M/m/S/s)?
		        if (! bParsed && (aMatches = _matchTimeRelative(sDate)) != null) {
		            oMoment = moment();
		            switch (aMatches[2].toUpperCase()) {
		                case "H":
		                case "":
		                	oMoment.add(Number(aMatches[1]), 'hours');
		                    break;
		                case "M":
		                	oMoment.add(Number(aMatches[1]), 'minutes');
		                    break;
		                case "S":
		                	oMoment.add(Number(aMatches[1]), 'seconds');
		                    break;
		            }
		            bParsed = true;
		        }
				
				// Ausgabe des geparsten Wertes
				if (bParsed && oMoment !== null && oMoment.isValid()) {
					return oMoment.toDate();
				}
			
			return null;
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
				return sDate === null || sDate === '' || sDate === undefined || this.parse(sDate) !== null;						
			},
			
			/**
			 * Compares two given date strings with the given comparator and granularity.
			 * Supported comparators are '==', '<=', '<', '>=', '>'.
			 * Supported granularities are 'year', 'month', 'day', 'hour', 'minute', 'second', 'millisecond'.
			 *
			 * @param {string} sDate1
			 * @param {string} sDate2
			 * @param {string} sComparator
			 * @param {string} sGranularity
			 * 
			 * @return {bool}			 *
			 */
			compareDates: function (sDate1, sDate2, sComparator, sGranularity) {
				var supportedComparators = ['==', '<=', '<', '>=', '>'];
				var supportedGranularity = ['year', 'month', 'day', 'hour', 'minute', 'second', 'millisecond'];
				var oParsedDate1, oParsedDate2, oMomentDate1, oMomentDate2;
				if (supportedComparators.includes(sComparator) !== true) {
					console.error("Comparator '" + sComparator + "' is not supported in date compare, supported comparators are '==', '<=', '<', '>=', '>' !")
					return false;
				}
				if (supportedGranularity.includes(sGranularity) !== true) {
					console.error("Granularity '" + sGranularity + "' is not supported in date compare, supported granularities are 'year', 'month', 'day', 'hour', 'minute', 'seconds', 'millisecond' !")
					return false;
				}
				oParsedDate1 = this.parse(sDate1);
				if (oParsedDate1 === null) {
					console.error("Date '" + sDate1 + "' is not a valid date, comparison not possible!");
					return false;
				}
				oParsedDate2 = this.parse(sDate2);
				if (oParsedDate2 === null) {
					console.error("Date '" + sDate2 + "' is not a valid date, comparison not possible!");
					return false;
				}
				oMomentDate1 = moment(oParsedDate1);
				oMomentDate2 = moment(oParsedDate2);
				switch (sComparator) {
					case '==': return oMomentDate1.isSame(oMomentDate2, sGranularity);
					case '<': return oMomentDate1.isBefore(oMomentDate2, sGranularity);
					case '<=': return oMomentDate1.isSameOrBefore(oMomentDate2, sGranularity);
					case '>': return oMomentDate1.isAfter(oMomentDate2, sGranularity);
					case '>=': return oMomentDate1.isSameOrAfter(oMomentDate2, sGranularity);
					default: return false;
				}
			},
			
			/**
			 * Adds an interval to a JS date object: e.g. +1d, -1w.
			 * 
			 * Intervals follow the syntax of relative dates.
			 * 
			 * @param {Date} oDate
			 * @param {string} sInterval
			 * @returns {Date}
			 */
			add(oDate, sInterval) {
				var aMatches = _matchDateRelative(sInterval || '1day');
				var iNumber = Number(aMatches[1]);
				var sIntervalType = aMatches[2];
				var sIntervalKey;
				switch (sIntervalType) {
					case "week": sIntervalKey = 'weeks'; break;
					case "month": sIntervalKey = 'months'; break;
					case "year": sIntervalKey = 'years'; break;
					case "day":		
					default: sIntervalKey = 'days'; break;
				}
				return moment(oDate).add(iNumber, sIntervalKey).toDate();
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
			 * Parses a string date into a JS Date object.
			 * 
			 * Accepts as input:
		     * - empty values,
		     * - parsable string tmes
		     * - relative times (+/-1h, etc.)
		     * 
		     * Returns NULL for invalid time values
		     *
		     * Examples:
		     * - "11:00" -> 11:00:00
		     * - "1100" -> 11:00:00
			 * - "11" -> 11:00:00
			 * - "1" -> 01:00:00
		     * - "+1" -> current time + 1 hour
		     * - "asdf" -> null
		     * 
		     * @param {string|NULL} [sTime]
			 * @param {string} [sTimeFormat]
			 * 
			 * @returns {string}
			 */
			parse: function(sTime, sTimeFormat) {
				// sTime ist ein String und wird zu einem date-Objekt geparst
		        
		        // Variablen initialisieren
		        var aMatches = null;
		        var bTimeParsed = false;
		        var bTimeValid = false;
		        var oMoment = null;
		        var iMsPos, iMs;
		        var iHH, iMM, iSS, sAmPm;

		        if (sTime === '' || sTime === null || sTime == undefined) {
					return null;
				}
		        
		        if (sTimeFormat !== undefined) {
					oMoment = moment(sTime, _ICUFormatToMoment(sTimeFormat), true);
					if (oMoment.isValid()) {
						if (sTimeFormat.indexOf('a') !== -1) {
							return oMoment.format('hh:mm:ss a');
						} else {
							return oMoment.format('HH:mm:ss');
						}
					}
				}
				
				iMsPos = sTime.lastIndexOf('.');
				if (iMsPos !== -1) {
					iMs = parseInt(sTime.substring(iMsPos + 1));
					if (! isNaN(iMs)) {
						sTime = sTime.substring(0, iMsPos);
					}
				}
		        
				// HH, h
				if (sTime.length <= 2 && sTime.match(/^\d+$/gm) && parseInt(sTime) < 24) {
					return sTime.padStart(2, '0') + ':00:00';
				}
		     	// HH:mm , HH:mm:ss, HH:mm am/pm, HH:mm:ss am/pm, HHmmss, HHmm
		        if (!bTimeParsed && (aMatches = /(\d{1,2}):?(\d{1,2}):?(\d{1,2})?\040?(pm|am)?$/i.exec(sTime)) != null) {
		        	iHH = Number(aMatches[1]);
		            iMM = Number(aMatches[2]);
		            iSS = Number(aMatches[3]);
		            if (aMatches[4]) {
		            	sAmPm = aMatches[4].toUpperCase();
		            }
		            if (isNaN(iSS)) {
		            	iSS = 0;
		            }
		            bTimeParsed = true;
		            bTimeValid = _validateTime(iHH, iMM, iSS) ;
		        }
		        
		        // Ausgabe des geparsten Wertes
		        if (bTimeParsed && bTimeValid) {
					// ().slice() padds the number with leading zeros
		        	return ('00' + iHH).slice(-2) + ':' + ('00' + iMM).slice(-2) + ':' + ('00' + iSS).slice(-2) + (sAmPm !== undefined ? ' ' + sAmPm : '');
		        }
		        
		        // (+/-)? ... (H/h/M/m/S/s)?
		        if (!bTimeParsed && (aMatches = /^([+\-]?\d{1,3})([HhMmSs]?)$/.exec(sTime)) != null) {
		            oMoment = moment();
		            switch (aMatches[2].toUpperCase()) {
		                case "H":
		                case "":
		                	oMoment.add(Number(aMatches[1]), 'hours');
		                    break;
		                case "M":
		                	oMoment.add(Number(aMatches[1]), 'minutes');
		                    break;
		                case "S":
		                	oMoment.add(Number(aMatches[1]), 'seconds');
		                    break;
		            }
					
		            bTimeParsed = true;
		            bTimeValid = true;
		        }
		        
		        // Ausgabe des geparsten Wertes
		        if (bTimeParsed && bTimeValid) {
		        	return oMoment.format('HH:mm:ss');
		        }
		        
		        return null;
			},
			
			format: function(sTime, sICUFormat) {
				if (sTime === null || sTime === undefined || sTime === '') {
					return sTime;	
				}
				if (sICUFormat === undefined) {
					return moment(new Date('1970-01-01 ' + sTime)).format('LTS');
				} else {
					return moment(new Date('1970-01-01 ' + sTime)).formatICU(sICUFormat);
				}
			},
			
			formatObject: function(oDateTime, sICUFormat) {
				if (oDateTime === null || oDateTime === undefined || oDateTime === '') {
					return oDateTime;	
				}
				if (sICUFormat === undefined) {
					return moment(oDateTime).format('LTS');
				} else {
					return moment(oDateTime).formatICU(sICUFormat);
				}
			},
			
			validate: function (sTime) {
				return sTime === null || sTime === '' || sTime === undefined || this.parse(sTime) !== null;
			}
		},
		
		/**
		 * Utilities for JS data sheets
		 * 
		 * 
		 * 
		 */
		data: {
			/**
			 * Returns TRUE if row1 is the same as row2. Compares only the UID values if a UID column is specified
			 * 
			 * @param {object} row1 
			 * @param {object} row2 
			 * @param {string} sUidCol 
			 * @returns {boolean}
			 */
			compareRows: function(row1, row2, sUidCol) {
				if (sUidCol !== undefined && row1[sUidCol] !== undefined && row2[sUidCol] !== undefined) {
					return row1[sUidCol] === row2[sUidCol];
				}
				return _dataRowsCompare(row1, row2);
			},
			/**
			 * Returns the index of a given row in an array of rows. Compares only UID values if UID column is specified.
			 * 
			 * @param {array} aRows 
			 * @param {object} oRowToFind 
			 * @param {string} sUidCol 
			 * @returns {int}
			 */
			indexOfRow: function(aRows, oRowToFind, sUidCol) {
				return aRows.findIndex(function(oRow){
					return exfTools.data.compareRows(oRow, oRowToFind, sUidCol);
				});
			},
			compareValues: function(mLeft, mRight, sComparator, sMultiValDelim) {
				var bResult;
				sMultiValDelim = sMultiValDelim ? sMultiValDelim : ',';
				mLeft = exfTools.data.comparableValue(mLeft);
				mRight = exfTools.data.comparableValue(mRight);
				
				if (sComparator === '<' || sComparator === '<=' || sComparator === '>' || sComparator === '>=') {
					if (isNaN(mLeft) === false) {
						mLeft = parseFloat(mLeft);
					}
					if (isNaN(mRight) === false) {
						mRight = parseFloat(mRight);
					}
				}
				switch (sComparator) {
	                case '=': 		// ComparatorDataType::IS
	                case '!=': 		// ComparatorDataType::IS_NOT
	                    bResult = function(){
							var sR = (mRight || '').toString(); 
							var sL = (mLeft || '').toString(); 
							if (sR === '' && sL !== '') {
								return false;
							}
							if (sR !== '' && sL === '') {
								return false;
							}
							return (new RegExp(sR, 'i')).test(sL); 
						}();
						if (sComparator === '!=') {
							bResult = ! bResult;
						}
	                    break;
	                case '==': 		// ComparatorDataType::EQUALS
	                case '!==': 	// ComparatorDataType::EQUALS_NOT
	                	bResult = (mLeft === null ? '' : mLeft.toString()) == (mRight === null ? '' : mRight.toString());
	                    if (sComparator === '!==') {
							bResult = ! bResult;
						}
	                    break;
	                case '<': 		// ComparatorDataType::LESS_THAN
	                	bResult = mLeft < mRight;
	                	break;
	                case '<=': 		// ComparatorDataType::LESS_THAN_OR_EQUALS
	                	bResult = mLeft <= mRight;
	                	break;
	                case '>': 		// ComparatorDataType::GREATER_THAN
	                	bResult = mLeft > mRight;
	                	break;
	                case '>=': 		// ComparatorDataType::GREATER_THAN_OR_EQUALS
	                	bResult = mLeft >= mRight;
	                	break;
					case '[':		// ComparatorDataType::IN
	                case '![':		// ComparatorDataType::NOT_IN
	                    bResult = function() {
			                var rightValues = ((mRight || '').toString()).split(sMultiValDelim);
			                var sLeftVal = (mLeft || '').toString().toLowerCase();
			                for (var i = 0; i < rightValues.length; i++) {
			                    if (sLeftVal === rightValues[i].trim().toLowerCase()) {
			                        return true;
			                    }
			                }
			                return false;
			            }();
						if (sComparator === '![') {
							bResult = ! bResult;
						}
	                    break;
					case '][': 		// ComparatorDataType::LIST_INTERSECTS
	                case '!][':		// ComparatorDataType::LIST_NOT_INTERSECTS
	                    bResult = function() {
			                var rightValues = ((mRight || '').toString()).split(sMultiValDelim);
							var leftValues = ((mLeft || '').toString()).split(sMultiValDelim);
			                for (var i = 0; i < rightValues.length; i++) {
								for (var j = 0; j < leftValues.length; j++) {
				                    if (rightValues[i].trim().toLowerCase() === leftValues[j].trim().toLowerCase()) {
				                        return true;
				                    }
								}
			                }
			                return false;
			            }();
						if (sComparator === '!][') {
							bResult = ! bResult;
						}
	                    break;
	                case '[[': 		// ComparatorDataType::LIST_SUBSET
	                case '![[': 	// ComparatorDataType::LIST_NOT_SUBSET
	                    bResult = function() {
			                var rightValues = ((mRight || '').toString()).split(sMultiValDelim);
							var leftValues = ((mLeft || '').toString()).split(sMultiValDelim);
			                for (var i = 0; i < rightValues.length; i++) {
								for (var j = 0; j < leftValues.length; j++) {
				                    if (rightValues[i].trim().toLowerCase() !== leftValues[j].trim().toLowerCase()) {
				                        return false;
				                    }
								}
			                }
			                return true;
			            }();
						if (sComparator === '![[') {
							bResult = ! bResult;
						}
	                    break;
	                case '[==': 	// ComparatorDataType::LIST_EACH_EQUALS
	                case '[!==': 	// ComparatorDataType::LIST_EACH_EQUALS_NOT
	                case '[<': 		// ComparatorDataType::LIST_EACH_LESS_THAN
	                case '[<=': 	// ComparatorDataType::LIST_EACH_LESS_THAN_OR_EQUALS
	                case '[>': 		// ComparatorDataType::LIST_EACH_GREATER_THAN
	                case '[>=': 	// ComparatorDataType::LIST_EACH_GREATER_THAN_OR_EQUALS
	                case '[=': 		// ComparatorDataType::LIST_EACH_IS
	                case '[!=': 	// ComparatorDataType::LIST_EACH_IS_NOT
						if (mLeft === '' || mLeft === null || mLeft === undefined) {
							bResult = exfTools.data.compareValues(mLeft, mRight, sComparator.substring(1), sMultiValDelim);
						} else {
							bResult = function() {
			                    var aLeftValues = ((mLeft || '').toString()).split(sMultiValDelim);
			                    var iLeftCnt = aLeftValues.length;
			                    var sScalarComp = sComparator.substring(1);
			                    for (var i = 0; i < iLeftCnt; i++) {
									if (false === exfTools.data.compareValues(aLeftValues[i], mRight, sScalarComp, sMultiValDelim)) {
										return false;
									}
								}
								return true;
							}();
						}
	                    break;
	                case ']==': 	// ComparatorDataType::LIST_ANY_EQUALS
	                case ']!==': 	// ComparatorDataType::LIST_ANY_EQUALS_NOT
	                case ']<': 		// ComparatorDataType::LIST_ANY_LESS_THAN
	                case ']<=': 	// ComparatorDataType::LIST_ANY_LESS_THAN_OR_EQUALS
	                case ']>': 		// ComparatorDataType::LIST_ANY_GREATER_THAN
	                case ']>=': 	// ComparatorDataType::LIST_ANY_GREATER_THAN_OR_EQUALS
	                case ']=': 		// ComparatorDataType::LIST_ANY_IS
	                case ']!=': 	// ComparatorDataType::LIST_ANY_IS_NOT
						if (mLeft === '' || mLeft === null || mLeft === undefined) {
							bResult = exfTools.data.compareValues(mLeft, mRight, sComparator.substring(1), sMultiValDelim);
						} else {
							bResult = function() {
			                    var aLeftValues = ((mLeft || '').toString()).split(sMultiValDelim);
			                    var iLeftCnt = aLeftValues.length;
			                    var sScalarComp = sComparator.substring(1);
			                    for (var i = 0; i < iLeftCnt; i++) {
									if (true === exfTools.data.compareValues(aLeftValues[i], mRight, sScalarComp, sMultiValDelim)) {
										return true;
									}
								}
								return false;
							}();
						}
	                    break;
	                default:
	                  	throw 'Unknown comparator "' + sComparator + '"!';
	            }
	            return bResult;
			},
			
			/**
			 * Prepares the given value for comparision via compareValues
			 *
			 * @param {mixed} [mVal]
			 * @return {String|number|NULL}
			 */
			comparableValue: function(mVal) {
				var bValIsString = exfTools.string.isString(mVal);
				
				// Convert undefined to null to reduce all sorts of checks
				if (mVal === undefined) return null;
				
				// Make sure, numeric 0 is transformed to string as otherwise the possible || operators
				// will transform it to an empty string because 0 is a falsly value.
				if (mVal === 0) return '0';
				
				// Make sure boolean values and strings representing booleans are converted to the strings 
				// '0' and '1' so that comparing values 0 and false and 1 and true return true and not false.
				// If not explicitly normalized (false).toString() will yield '' and not '0'.
				if (mVal === true || (bValIsString && mVal.toLowerCase() === 'true')) return '1';
				if (mVal === false || (bValIsString && mVal.toLowerCase() === 'false')) return '0';
				
				// Handle `NULL` strings used in metamodel
				if (bValIsString && mVal.toUpperCase() === 'NULL') return null;
				
				return mVal;
			},
			
			/**
			 * Filter data rows using a condition group
			 * 
			 * @param {Array.<Object>} [aRows] - e.g. [{UID: 22, NAME: "Something"}, {UID: 23, NAME: "Another"}]
			 * @param {{
				operator: string, 
				ignore_empty_values: boolean,
				conditions: Array.<{columnName: string, value: any, comparator: string}>
				nested_groups: Array
				}} [oConditionGroup] - e.g. {columnName: "UID", value: 22, comparator: "=="}
			 * 
			 * @returns {Array.<Object>}
			 * 
			 */
			filterRows: function(aRows, oConditionGroup) {
				var aConditions = oConditionGroup.conditions || [];
				var aNestedGroups = oConditionGroup.nested_groups || [];
				var sOperator = oConditionGroup.operator || 'AND';
				var aRowsFiltered = [];
				var oSelf = this;
				var bIgnoreEmptyRightVals = oConditionGroup.ignore_empty_values;
				if (bIgnoreEmptyRightVals === undefined) {
					bIgnoreEmptyRightVals = false;
				}
				aRows.forEach(function(oRow){
					var oCondition;
					var sColName;
					var bRowResult = null;
					var bConditionResult = null;
					
					for (var iC = 0; iC < aConditions.length; iC++) {
						oCondition = aConditions[iC];
						if (bIgnoreEmptyRightVals === true && (oCondition.value === '' || oCondition.value === null || oCondition.value === undefined)) {
							continue;
						}
						sColName = oCondition.columnName || oCondition.expression;
				        bConditionResult = oSelf.compareValues(
							oRow[sColName] === undefined ? null : oRow[sColName], 
							oCondition.value,
							(oCondition.comparator || '=')
						);
				        if (sOperator === 'AND') {
							if (bConditionResult === false) {
								bRowResult = false;
								break;
							} else {
								bRowResult = true;
							}
						} else if (sOperator === 'OR') {
							if (bConditionResult === true) {
								bRowResult = true;
								break;
							} else {
								bRowResult = false;
							}
						} else {
							throw 'Unknown logical operator ' + sOperator + ' used!';
						}
					}
					
					if (bRowResult === false && sOperator === 'AND') {
						return;
					}
					if (bRowResult === true && sOperator === 'OR') {
						aRowsFiltered.push(oRow);
						return;
					}
					
					for (var iCG = 0; iCG < aNestedGroups.length; iCG++) {
				        bConditionResult = oSelf.filterRows([oRow], aNestedGroups[iCG]).length === 1;
				        if (sOperator === 'AND') {
							if (bConditionResult === false) {
								bRowResult = false;
								break;
							} else {
								bRowResult = true;
							}
						} else if (sOperator === 'OR') {
							if (bConditionResult === true) {
								bRowResult = true;
								break;
							} else {
								bRowResult = false;
							}
						} else {
							throw 'Unknown logical operator ' + sOperator + ' used!';
						}
					}
					
					if (bRowResult === true || bRowResult === null) {
						aRowsFiltered.push(oRow);
					}
					
			    });
			    
			    return aRowsFiltered;
			},
			
			/**
			 * Sorts rows using an array of sorter objects
			 * 
			 * @param {Array.<Object>} aRows
			 * @param {Array.<{columnName: string, direction: string}>} aSorters
			 */
			sortRows: function(aRows, aSorters) {
				if (! aSorters || aSorters === []) {
					return aRows;
				}
				aRows.sort(function(a, b) {
					for (let i = 0; i < aSorters.length; i++) {
					    const oSorter = aSorters[i];
					    const sCol = oSorter.columnName;
					    const sDir = oSorter.direction.toLowerCase() === 'asc' ? 1 : -1;
					
					    if (a[sCol] < b[sCol]) {
				      		return -1 * sDir;
					    } else if (a[sCol] > b[sCol]) {
			      			return 1 * sDir;
					    }
				  	}
				 	return 0;
				});
				return aRows;
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
			
			pasteText: function(text) {
				return this._fallbackPasteTextFromClipboard();
			}
				/*async function(text) {
				if (! navigator.clipboard) {
				    return this._fallbackPasteTextFromClipboard();
				}
				return await navigator.clipboard.readText();
			}*/
		},
		
		/**
		 * String tools
		 * 
		 * 
		 * 
		 */
		string: {
			
			/**
			 * Replaces line breaks with the given HTML tag - like PHP nl2br()
			 *
			 * @param {string} [str]
			 * @param {string} [breakTag] - default: '<br>'
			 * 
			 * @returns {string}
			 */
			nl2br: function(str, breakTag) {
				breakTag = breakTag || '<br>';
	  			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
			},
			
			/**
			 * Checks if the given value is a string
			 * @param {mixed} [val]
			 * 
			 * @returns {bool}
			 */
			isString: function(val) {
				return (typeof val === 'string' || val instanceof String);
			},
			
			/**
			 * Replaces most dangerous characters with HTML entities:  `&` => `&amp;`, `<` => `&lt;`, etc.
			 */
			htmlEscape: function(text, bEscapeQuotes) {
				var map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
  				var oRegEx = bEscapeQuotes ? /[&<>"']/g : /[&<>]/g;
				bEscapeQuotes !== undefined ? bEscapeQuotes : true;
				if (exfTools.string.isString(text)) {
					return text.replace(oRegEx, function(m) { return map[m]; });
				}
				return text;
			},
			
			htmlUnescape: function(text) {
				var map = {
					'&amp;': '&',
					'&lt;': '<',
					'&gt;': '>',
					'&quot;': '"',
					'&#039;': "'"
				};
  
				if (exfTools.string.isString(text)) {
					return text.replace(/(&amp;|&lt;|&gt;|&quot;|&#039;)/g, function(m) { return map[m]; });
				}
				return text;
			}
		},
		number: {
			/**
			 * Transform `2048` to `2 KB`
			 *
			 * @param {float} [fBytes]
			 * @param {int} [iDecimals]
			 * @return {string}
			 */
			formatBytes: function(fBytes, iDecimals = 2) {
			    if (!+fBytes) return '0';
			
			    const k = 1024;
			    const dm = iDecimals < 0 ? 0 : iDecimals;
			    const aSizes = ['', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
			
			    const i = Math.floor(Math.log(fBytes) / Math.log(k));
			
			    return `${parseFloat((fBytes / Math.pow(k, i)).toFixed(dm))} ${aSizes[i]}`;
			}
		}
	}
	
	return exfTools;
})));