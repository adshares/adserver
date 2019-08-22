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

var getBrowserContext = function() {
    return {
        frame : (parent == top ? 0 : 1),
        width : window.screen.width,
        height : window.screen.height,
        url : (parent !== window) ? document.referrer : document.location.href,
        pop: top.opener !== null ? 1 : 0
    }
}

window.demandLogContext = function(url) {
	url = addUrlParam(url, 'k', UrlSafeBase64Encode(JSON.stringify(getBrowserContext())));
	var img = new Image();
	img.src = url;

	document.body.appendChild(img);
}
