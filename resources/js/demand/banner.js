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

domReady(function() {
    let onlyStrings = false;
    let target = parent;

    let handler = function(e) {
        let msg = {
            dwmthClick : 1
        };
        target.postMessage(onlyStrings ? JSON.stringify(msg) : msg, '*');
        e.preventDefault();
    };

    let links = document.getElementsByTagName('a');
    addListener(document.body, 'click', handler, true);

    try {
        window.postMessage({
            toString : function() {
                onlyStrings = true;
            }
            }, "*");
    } catch (e) {

    }
    let fn = function(event) {
        let msg;

        try {
            if (typeof event.data == 'string') {
                msg = JSON.parse(event.data);
            } else {
                msg = event.data;
            }
            if (msg.dwmthLoad) {
                let data = msg.data;

                if (data.click_url) {
                    for (let i = 0; i < links.length; i++) {
                        links[i].href = data.click_url;
                    }
                }
            }
        } catch (e) {}
    };
    window.addEventListener ? addEventListener('message', fn) : attachEvent(
        'onmessage', fn);
    let msg = {
        dwmthLoad : 1
    };
    target.postMessage(onlyStrings ? JSON.stringify(msg) : msg , '*');
});
