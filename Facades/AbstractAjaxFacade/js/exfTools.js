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
				var oMatches = null;
				var bParsed = false;
				var bValid = false;
				var sTime = undefined;
				var oMoment = null;
				var iYYYY, iMM, iDD;
				var sHH, sMM, sSS;
				var aTime;
				var sDiffKey, sDiffExp, iDiffNumber;
				
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
				if (!bParsed && (oMatches = /(\d{2}:\d{2}:\d{2})/.exec(sDate)) != null) {
					sTime = oMatches[1];
				} else if (!bParsed && (oMatches = / (\d{2}:\d{2})/.exec(sDate)) != null) {
				// hh:mm
					sTime = oMatches[1];
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
				if (!bParsed && (oMatches = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})/.exec(sDate)) != null) {
					iYYYY = Number(oMatches[3]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// yyyy.MM.dd, yyyy-MM-dd, yyyy/MM/dd, yyyy.M.d, yyyy-M-d, yyyy/M/d
				if (!bParsed && (oMatches = /(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = Number(oMatches[1]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[3]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// dd.MM.yy, dd-MM-yy, dd/MM/yy, d.M.yy, d-M-yy, d/M/yy
				if (!bParsed && (oMatches = /(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{2})/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(oMatches[3]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// yy.MM.dd, yy-MM-dd, yy/MM/dd, yy.M.d, yy-M-d, yy/M/d
				if (!bParsed && (oMatches = /(\d{2})[.\-/](\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(oMatches[1]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[3]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// dd.MM, dd-MM, dd/MM, d.M, d-M, d/M
				if (!bParsed && (oMatches = /(\d{1,2})[.\-/](\d{1,2})/.exec(sDate)) != null) {
					iYYYY = moment().year;
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMMyyyy
				if (!bParsed && (oMatches = /^(\d{2})(\d{2})(\d{4})$/.exec(sDate)) != null) {
					iYYYY = Number(oMatches[3]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMMyy
				if (!bParsed && (oMatches = /^(\d{2})(\d{2})(\d{2})$/.exec(sDate)) != null) {
					iYYYY = 2000 + Number(oMatches[3]);
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
					bParsed = true;
					bValid = _validateDate(iYYYY, iMM, iDD);
				}
				// ddMM
				if (!bParsed && (oMatches = /^(\d{2})(\d{2})$/.exec(sDate)) != null) {
					iYYYY = (new Date()).getFullYear();
					iMM = Number(oMatches[2]);
					iDD = Number(oMatches[1]);
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
				oMatches = (new RegExp("^([+\-]?\\d{0,3})(" + _buildRegexString(oParserParams) + ")$", "i")).exec(sDate);
				oMoment = null;
				if (!bParsed && (oMatches !== null)) {
					oMoment = moment();
					sDiffKey = null;
					sDiffExp = oMatches[2];
					iDiffNumber = Number(oMatches[1]);
					if (iDiffNumber !== 0 && sDiffExp === '') {
						sDiffKey ="day";
					}
					if (iDiffNumber === 0 && sDiffExp === '') {
						sDiffKey = "now";
					}
					if (iDiffNumber === 0 && sDiffExp !== '') {
						iDiffNumber = 1;
					}
					if (sDiffKey === null) {
						sDiffKey = _findKey(sDiffExp, oParserParams);
					}
					switch (sDiffKey) {
						case "now": break;
						case "yesterday":
							oMoment.subtract(1, 'days');
							break;
						case "tomorrow":
							oMoment.add(1, 'days');
							break;
						case "day":							
							oMoment.add(iDiffNumber, 'days');							
							break;
						case "week":
							oMoment.add(iDiffNumber, 'weeks');
							break;
						case "month":
							oMoment.add(iDiffNumber, 'months');							
							break;
						case "year":
							oMoment.add(iDiffNumber, 'years');
							break;
						default: oMoment = null;
					}
					bParsed = true;
				}
				
				// (+/-)? ... (H/h/M/m/S/s)?
		        if (! bParsed && (oMatches = /^([+\-]?\d{1,3})([HhMmSs]?)$/.exec(sDate)) != null) {
		            oMoment = moment();
		            switch (oMatches[2].toUpperCase()) {
		                case "H":
		                case "":
		                	oMoment.add(Number(oMatches[1]), 'hours');
		                    break;
		                case "M":
		                	oMoment.add(Number(oMatches[1]), 'minutes');
		                    break;
		                case "S":
		                	oMoment.add(Number(oMatches[1]), 'seconds');
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
						if (sTimeFormat.indexOf('a') !== '-1') {
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
			compareRows: function(row1, row2) {
				return _dataRowsCompare(row1, row2);
			},
			compareValues: function(mLeft, mRight, sComparator, sMultiValDelim) {
				var bResult;
				sMultiValDelim = sMultiValDelim ? sMultiValDelim : ',';
				mLeft = mLeft !== undefined ? mLeft : null;
				mRight = mRight !== undefined ? mRight : null;
				// Make sure, numeric 0 is transformed to string as otherwise the latter || operators
				// will transform it to an empty string because 0 is a falsly value.
				mLeft = mLeft === 0 ? '0' : mLeft;
				mRight = mRight === 0 ? '0' : mRight;
				// Handle `NULL` strings used in metamodel
				mLeft = exfTools.string.isString(mLeft) && mLeft.toUpperCase() === 'NULL' ? null : mLeft;
				mRight = exfTools.string.isString(mRight) && mRight.toUpperCase() === 'NULL' ? null : mRight;
				if (sComparator === '<' || sComparator === '<=' || sComparator === '>' || sComparator === '>=') {
					if (parseFloat(mLeft) !== NaN) {
						mLeft = parseFloat(mLeft);
					}
					if (parseFloat(mRight) !== NaN) {
						mRight = parseFloat(mRight);
					}
				}
				switch (sComparator) {
	                case '==':
	                case '!==':
	                    bResult = (mLeft || '').toString() == (mRight || '').toString();
	                    if (sComparator === '!==') {
							bResult = ! bResult;
						}
	                    break;
	                case '<':
	                	bResult = mLeft < mRight;
	                	break;
	                case '<=':
	                	bResult = mLeft <= mRight;
	                	break;
	                case '>':
	                	bResult = mLeft > mRight;
	                	break;
	                case '>=':
	                	bResult = mLeft >= mRight;
	                	break;
	                case '[':
	                case '![':
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
	                case '=':
	                case '!=':
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
	                default:
	                  	throw 'Unknown comparator "' + sComparator + '"!';
	            }
	            return bResult;
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
			}
		}
	}
	
	return exfTools;
})));