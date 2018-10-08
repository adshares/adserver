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
/* jslint */
/*
 * global require: false, define: false, requirejs: false, window: false,
 * clearInterval: false, document: false, self: false, setInterval: false
 */

var domReady = (function () {
    var isTop, testDiv, scrollIntervalId, isBrowser = typeof window !== "undefined" && window.document, isPageLoaded = !isBrowser, doc = isBrowser ? document
            : null, readyCalls = [];

    function runCallbacks(callbacks) {
        var i;
        for (i = 0; i < callbacks.length; i += 1) {
            callbacks[i](doc);
        }
    }

    function callReady() {
        var callbacks = readyCalls;

        if (isPageLoaded) {
            // Call the DOM ready callbacks
            if (callbacks.length) {
                readyCalls = [];
                runCallbacks(callbacks);
            }
        }
    }

    /**
     * Sets the page as loaded.
     */
    function pageLoaded() {
        if (!isPageLoaded) {
            isPageLoaded = true;
            if (scrollIntervalId) {
                clearInterval(scrollIntervalId);
            }

            callReady();
        }
    }

    if (isBrowser) {
        if (document.addEventListener) {
            // Standards. Hooray! Assumption here that if standards based,
            // it knows about DOMContentLoaded.
            document.addEventListener("DOMContentLoaded", pageLoaded, false);
            window.addEventListener("load", pageLoaded, false);
        } else if (window.attachEvent) {
            window.attachEvent("onload", pageLoaded);

            testDiv = document.createElement('div');
            try {
                isTop = window.frameElement === null;
            } catch (e) {
            }

            // DOMContentLoaded approximation that uses a doScroll, as found by
            // Diego Perini: http://javascript.nwbox.com/IEContentLoaded/,
            // but modified by other contributors, including jdalton
            if (testDiv.doScroll && isTop && window.external) {
                scrollIntervalId = setInterval(function () {
                    try {
                        testDiv.doScroll();
                        pageLoaded();
                    } catch (e) {
                    }
                }, 30);
            }
        }

        // Check if document already complete, and if so, just trigger page load
        // listeners. Latest webkit browsers also use "interactive", and
        // will fire the onDOMContentLoaded before "interactive" but not after
        // entering "interactive" or "complete". More details:
        // http://dev.w3.org/html5/spec/the-end.html#the-end
        // http://stackoverflow.com/questions/3665561/document-readystate-of-interactive-vs-ondomcontentloaded
        // Hmm, this is more complicated on further use, see "firing too early"
        // bug: https://github.com/requirejs/domReady/issues/1
        // so removing the || document.readyState === "interactive" test.
        // There is still a window.onload binding that should get fired if
        // DOMContentLoaded is missed.
        if (document.readyState === "complete") {
            pageLoaded();
        }
    }

    /** START OF PUBLIC API * */

    /**
     * Registers a callback for DOM ready. If DOM is already ready, the callback
     * is called immediately.
     * 
     * @param {Function}
     *            callback
     */
    function domReady(callback) {
        if (isPageLoaded) {
            callback(doc);
        } else {
            readyCalls.push(callback);
        }
        return domReady;
    }
    /** END OF PUBLIC API * */

    return domReady;
})();
