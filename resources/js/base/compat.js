/*
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

if (typeof Array.prototype.forEach != 'function') {
    Array.prototype.forEach = function(callback){
        for (var i = 0; i < this.length; i++){
            callback.apply(this, [this[i], i, this]);
        }
    };
}


if (!Function.prototype.bind) {
	Function.prototype.bind = function(oThis) {
		var aArgs = Array.prototype.slice.call(arguments, 1), fToBind = this, fNOP = function() {
		}, fBound = function() {
			return fToBind.apply(this instanceof fNOP && oThis ? this : oThis,
					aArgs.concat(Array.prototype.slice.call(arguments)));
		};

		fNOP.prototype = this.prototype;
		fBound.prototype = new fNOP();

		return fBound;
	};
}

var addListener = function (element, event, handler, phase) {
    if (element.addEventListener) {
        return element.addEventListener(event, handler, phase);
    } else {
        return element.attachEvent('on' + event, handler);
    }
};

var removeListener = function (element, event, handler, phase) {
    if (element.removeEventListener) {
        return element.removeEventListener(event, handler, phase);
    } else {
        return element.detachEvent('on' + event, handler);
    }
};
