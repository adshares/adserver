/*
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

var serverOrigin = '{{ ORIGIN }}';
var selectorClass = '{{ SELECTOR }}';
var rotateIntervalMs = parseInt('{{ ROTATE_INTERVAL }}') * 1000;
var defaultLocation = '{{ DEFAULT_LOCATION }}';

var topwin = window;
try {
    while (topwin.parent != topwin && topwin.parent.document) {
        topwin = topwin.parent;
    }
} catch (e) {

}
var topdoc = topwin.document;

var winOpen = (function(open) {
    return function() {
        return open.apply(topwin, arguments);
    }
})(topwin.open);

var encodeZones = function (zone_data) {
    var VALUE_GLUE = "\t";
    var PROP_GLUE = "\r";
    var ZONE_GLUE = "\n";
    var fields = {};
    var fCount = 0;

    var result = [null];
    for (var i = 0; i < zone_data.length; i++) {
        var zone = zone_data[i];
        var entry = [];
        for (var prop in zone) {
            if (zone.hasOwnProperty(prop)) {
                if (!fields[prop]) {
                    fields[prop] = fCount++;
                }
                entry.push(fields[prop] + VALUE_GLUE + zone[prop]);
            }
        }
        result.push(entry.join(PROP_GLUE));
    }

    var entry = [];
    for (var prop in fields) {
        if (fields.hasOwnProperty(prop)) {
            entry.push(prop);
        }
        result[0] = entry.join(VALUE_GLUE);
    }
    return UrlSafeBase64Encode(result.join(ZONE_GLUE));
};


var insertedElements = [];
var logInsertedElement = function(el) {
    if (insertedElements.length === 0) {
        addListener(window, 'beforeunload', function (_event) {
            let x;
            while (x = insertedElements.pop()) {
                x.parentElement && x.parentElement.removeChild(x);
            }
        });
    }
    insertedElements.push(el);
}

var dwmthACL = [];
var dwmthURLS = [];

var replaceTag = function (oldTag, newTag, banner) {
    for (var i = 0; i < oldTag.attributes.length; i++) {
        var name = oldTag.attributes[i].name;
        if (name.indexOf('data-') !== 0) {
            newTag.setAttribute(name, oldTag.getAttribute(name));
        }
    }
    newTag.style.overflow = 'hidden';
    newTag.style.position = 'relative';
    // ios 12 fix
    var el = [];
    if (!banner || banner.type !== 'video') {
        while (newTag.firstChild) {
            el.push(newTag.removeChild(newTag.firstChild));
        }
    }
    // ios 12 fix

    while (oldTag.lastElementChild) {
        oldTag.removeChild(oldTag.lastElementChild);
    }
    oldTag.appendChild(newTag);
    setTimeout(function () {
        oldTag.__dwmth = 0;
    }, rotateIntervalMs);

    // ios 12 fix
    setTimeout(function() {
        while (el.length > 0) {
            newTag.appendChild(el.shift());
        }
    }, 0);
    // ios 12 fix

    logInsertedElement(newTag);
};


var prepareElement = function (context, banner, element) {
    var div = document.createElement('div');
    var clickOverlay;
    var infoBox;

    if (false !== banner.infoBox) {
        infoBox = prepareInfoBox(context, banner);
        div.appendChild(infoBox);
    }

    if (element.tagName === 'IFRAME') {
        if (banner.type === 'direct' && !context.skip_overlay) {
            clickOverlay = document.createElement('a');
            clickOverlay.style.cssText = "display:block; position: absolute !important; top: 0px !important; left: 0px !important; right: 0px !important; bottom: 0px !important";
            clickOverlay.setAttribute('href', context.click_url);
            clickOverlay.setAttribute('target', '_blank');
            if (infoBox) {
                div.insertBefore(clickOverlay, infoBox);
            } else {
                div.appendChild(clickOverlay);
            }
        }

        prepareIframe(element);
        addListener(window, 'message', function (event) {
            if (event.source == element.contentWindow && event.data) {
                var data, isString = typeof event.data === 'string';
                if (isString) {
                    data = JSON.parse(event.data);
                } else {
                    data = event.data;
                }
                if (data.dwmthLoad) {
                    if (clickOverlay) { // ad is aware of mechanics
                        div.removeChild(clickOverlay);
                    }
                    var msg = {dwmthLoad: 1, data: context};

                    event.source.postMessage(isString ? JSON.stringify(msg) : msg, '*');
                } else if (data.dwmthClick) {
                    if (document.activeElement != element) {
                        console.log('click without mouse interaction');
                        return;
                    }
                    if (!isVisible(element)) {
                        console.log('invisible click');
                        return;
                    }
                    var url = context.click_url;
                    if (!winOpen(url, '_blank')) {
                        topwin.location.href = url;
                    }
                    // prevent double click
                    document.activeElement.blur();
                }
            }
        });

        div.appendChild(element);
    } else {
        element.border = "0";
        var link = document.createElement('a');
        link.target = '_blank';
        link.href = context.click_url;
        link.appendChild(element);
        div.appendChild(link);
    }

    return div;
};

var prepareInfoBox = function (context, banner) {
    const url = addUrlParam(serverOrigin + '/supply/why', {
        'bid': banner.creativeId,
        'cid': context.cid,
    });

    const div = document.createElement('div');
    div.setAttribute('style', 'all: revert; position: absolute !important; top: 0px !important; right: 0px !important;background-color: #fff !important; height: 16px; width: 16px; overflow: hidden; padding: 0;z-index:1');

    const link = document.createElement('a');
    link.target = '_blank';
    link.href = url;
    link.setAttribute('style', 'all: revert;text-decoration: none !important;background-color: #fff !important');
    link.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" style="all: revert">' +
        '<defs><style>.cls-1{fill:#fff;}.cls-2{fill:#ff414d;}</style></defs>' +
        '<g id="Layer_1"><rect class="cls-1" x="-.78" y="-.84" width="17.54" height="17.61"/></g>' +
        '<g id="Layer_2"><path class="cls-2" d="m11.82,2.45v2.88c-.41-.15-.86-.23-1.33-.23-.9,0-1.73.3-2.4.8.68.62,1.16,1.45,1.33,2.39.24-.33.63-.54,1.07-.54.73,0,1.33.6,1.33,1.33s-.59,1.33-1.33,1.33c-.44,0-.83-.21-1.07-.54-.16-.22-.26-.49-.26-.79,0-1.18-.51-2.24-1.33-2.97-.08-.07-.17-.15-.26-.21-.67-.5-1.5-.8-2.4-.8-2.2,0-3.98,1.78-3.98,3.98s1.78,3.98,3.98,3.98c.9,0,1.73-.3,2.4-.8-.68-.62-1.16-1.45-1.33-2.39-.24.33-.63.54-1.07.54-.73,0-1.33-.6-1.33-1.33s.6-1.33,1.33-1.33c.44,0,.83.21,1.07.54.16.22.26.49.26.79,0,1.18.51,2.24,1.33,2.97.08.07.17.15.26.21.67.5,1.5.8,2.4.8.47,0,.91-.08,1.33-.23,1.55-.55,2.66-2.02,2.66-3.76V2.45h-2.66Z"/></g>' +
        '</svg>';

    div.appendChild(link);

    return div;
};

// checks if element is not hidden with display: none
function isRendered(domObj) {
    if (domObj.nodeType !== 1) {
        return true;
    }
    while (domObj != document.body) {
        if (window.getComputedStyle) {
            var cs = document.defaultView.getComputedStyle(domObj, null);
            if (cs.getPropertyValue("display") === "none" || cs.getPropertyValue("visibility") === "hidden") {
                return false;
            }
        } else if (domObj.currentStyle
            && (domObj.currentStyle["display"] === "none" || domObj.currentStyle["visibility"] === "hidden")) {
            return false;
        } else {
            return true;
        }
        domObj = domObj.parentNode;
    }
    return true;
}

function viewSizeWin(w) {
    var left = 0, top = 0;
    var doc = w.document;
    var docEl = (doc.compatMode && doc.compatMode === 'CSS1Compat')
        ? doc.documentElement : doc.body;

    var width = docEl.clientWidth;
    var height = docEl.clientHeight;

    // mobile zoomed in?
    if (w.innerWidth && width > w.innerWidth) {
        width = w.innerWidth;
        height = w.innerHeight;
    }

    return {width: width, height: height, left: left, top: top, right: width, bottom: height};
}

function locateFrameElement(w_parent, w) {
    var frames = w_parent.document.getElementsByTagName('iframe');
    for (var i = 0, n = frames.length; i < n; i++) {
        if (frames[i].contentWindow == w) {
            return frames[i];
        }
    }
    return null;
}

function viewSize() {
    var w = window;
    var size = viewSizeWin(w);
    while (w != topwin) {
        var parent_size = viewSizeWin(w.parent);
        var frame_el = locateFrameElement(w.parent, w);
        var rect = getBoundRect(frame_el);
        var isect = rectIntersect(parent_size, rect);
        isect.left -= rect.left;
        isect.right -= rect.left;
        isect.top -= rect.top;
        isect.bottom -= rect.top;
        size = rectIntersect(size, isect);
        w = w.parent;
    }

    return size;
}

function rectIntersect(a, b) {
    var x = Math.max(a.left, b.left);
    var num1 = Math.min(a.left + a.width, b.left + b.width);
    var y = Math.max(a.top, b.top);
    var num2 = Math.min(a.top + a.height, b.top + b.height);
    if (num1 >= x && num2 >= y) {
        return {left: x, top: y, width: num1 - x, height: num2 - y, bottom: num2, right: num1};
    } else {
        return false;
    }
}

function getBoundRect(el, overflow) {
    var left = 0, top = 0;
    var width = el.offsetWidth, height = el.offsetHeight;

    if (overflow) {
        var css = el.ownerDocument.defaultView.getComputedStyle(el);
        if (css.overflowX === 'visible') {
            width = 200000;
            left = -100000;
        }
        if (css.overflowY === 'visible') {
            height = 200000;
            top = -100000;
        }
    }

    var rect = el.getBoundingClientRect();

    return {
        top: rect.top + top,
        bottom: rect.top + top + height,
        left: rect.left + left,
        right: rect.left + left + width,
        width: width,
        height: height
    }
}

var isWindowVisible = (function() {
    var hidden, visibilityChange;
    if (typeof document.hidden !== "undefined") { // Opera 12.10 and Firefox 18 and later support
        hidden = "hidden";
        visibilityChange = "visibilitychange";
    } else if (typeof document.msHidden !== "undefined") {
        hidden = "msHidden";
        visibilityChange = "msvisibilitychange";
    } else if (typeof document.webkitHidden !== "undefined") {
        hidden = "webkitHidden";
        visibilityChange = "webkitvisibilitychange";
    }

    var isVisible;

    if (typeof document.addEventListener === "undefined" || hidden === undefined) {
        isVisible = true;
    } else {
        var handleVisibilityChange = function() {
            isVisible = !document[hidden];
        }
        document.addEventListener(visibilityChange, handleVisibilityChange, false);
        handleVisibilityChange();
    }

    return function() {
        return isVisible;
    }
})();


var isOccluded = function(rect, el) {
    if (!rect) {
        return true;
    }
    outer:
    for (var i = 0; i < 10; i++) {
        var top = document.elementFromPoint(Math.floor(rect.left + Math.random() * rect.width), Math.floor(rect.top + Math.random() * rect.height));
        while (top) {
            if (top == el) {
                continue outer;
            }
            top = top.parentElement;
        }
        return true;
    }

    return false;
};

// checks if element is visible on screen
var isVisible = function (el) {
    return isRendered(el) && isWindowVisible();
};

var impressionId;
var getImpressionId = function () {
    if (!impressionId) {
        impressionId = UrlSafeBase64Encode(getRandId(16));
    }
    return impressionId;
};

var getCid = function() {
    var i, l, n;
    var s = getRandId(15) + '\0';
    var o = '';
    for (i = 0, l = s.length; i < l; i++) {
        n = s.charCodeAt(i)
            .toString(16)
        o += n.length < 2 ? '0' + n : n
    }
    return o;
}

var getRandId = function(bytes) {
    var d = new Date().getTime();

    var chars = [];
    for (var i = 0; i < bytes; i++) {
        var r = (d + Math.random() * 256) % 256 | 0;
        d = Math.floor(d / 256);
        chars.push(String.fromCharCode(r));
    }

    return chars.join('');
}

var aduserPixel = function (impressionId, onload) {
    if (!serverOrigin) {
        return;
    }
    var path = '/supply/register?iid=';
    var url = serverOrigin + path + impressionId;

    if (dwmthURLS[url]) {
        return false;
    }

    var iframe = createIframeFromUrl(url);

    if (onload) {
        var loaded = false;
        var loadFn = function() {
            if (loaded) {
                return;
            }
            loaded = true;
            onload();
        };
        iframe.onerror = iframe.onabort = iframe.onload = loadFn;
        setTimeout(loadFn, 1);
    }

    document.body.appendChild(iframe);
    dwmthACL.push(iframe.contentWindow);
    dwmthURLS[url] = 1;
    return true;
};

var createIframeFromUrl = function (url, doc) {
    const iframe = (doc || document).createElement('iframe');
    iframe.setAttribute('style', 'display:none');
    iframe.setAttribute('width', '1');
    iframe.setAttribute('height', '1');
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
    iframe.src = url;

    return iframe;
};

var getPageKeywords = function (doc) {
    var MAX_KEYWORDS = 10;
    var metaKeywords = doc.querySelector("meta[name=keywords]");

    if (metaKeywords === null) {
        return '';
    }

    if (metaKeywords.content) {
        var tmp = metaKeywords.content.replace(/[\t\r\n]/g, ',').split(',');
        var n = Math.min(MAX_KEYWORDS, tmp.length);
        var tmp2 = [];
        for (var i = 0; i < n; i++) {
            tmp2.push(tmp[i].trim());
        }
        return tmp2.join(',');
    }
    return '';
};

var getBrowserContext = function () {
    const metamask = (typeof window.ethereum !== 'undefined') || (typeof window.web3 !== 'undefined');
    var location = topwin.location.href;
    if( !location.startsWith('http') && defaultLocation.length > 0 ){
        location = defaultLocation;
    }
    return {
        iid: getImpressionId(),
        frame: (topwin == top ? 0 : 1),
        width: topwin.screen.width,
        height: topwin.screen.height,
        url: location,
        keywords: getPageKeywords(topdoc),
        metamask: metamask ? 1 : 0,
        ref: 'https://swashapp.io',
        pop: topwin.opener !== null && topwin.opener !== undefined ? 1 : 0
        // agent: window.navigator.userAgent
    }
};

var findBackfillCode = function(container) {
    var tag = container.querySelectorAll('[type="app/backfill"]')[0];
    var text = null;
    if (tag) {
        text = tag.textContent;
    } else {
        for (var i = 0, n = container.childNodes.length; i < n; i++) {
            if (container.childNodes[i].nodeType === 8) {
                text = container.childNodes[i].textContent;
                break;
            }
        }
    }
    return text ? text.replace('<*!--', '<!--').replace('--*>', '-->') : null;
}

var parseZoneOptions = function(str) {
    var opts = {};
    if (typeof str !== 'string') {
        return opts;
    }
    var parts = str.split(',');
    for (var i =0; i < parts.length; i++) {
        var part = parts[i].trim();
        var name_val = part.split('=', 2);
        opts[name_val[0]] = name_val.length === 1 ? true : name_val[1];
    }
    return opts;
};

var abd;

var getActiveZones = function(call_func, retryNo) {
    var _tags = document.querySelectorAll(selectorClass + '[data-placement],' + selectorClass + '[data-zone]');
    var n = _tags.length;

    var retryFn = function () {
        retryNo = retryNo ? retryNo : 0;
        setTimeout(function () {
            getActiveZones(call_func, retryNo + 1);
        }, retryNo < 20 ? 100 : 500);
    };

    var tags = [];
    for (var i = 0; i < n; i++) {
        tags[i] = _tags[i];
    }

    if (0 === n) {
        retryFn();
        return;
    }

    var param, params = [];
    params.push(getBrowserContext());

    var zones = [];

    var valid = 0;
    var waiting = 0;
    tags.forEach(function(tag, i) {
        var zone;
        if (tag.__dwmth) {
            return;
        }
        tag.__dwmth = 1;
        param = {};
        param.width = parseInt(tag.offsetWidth) || parseInt(tag.style.width) || 0;
        param.height = parseInt(tag.offsetHeight) || parseInt(tag.style.height) || 0;
        for (var j = 0, m = tag.attributes.length; j < m; j++) {
            var parts = tag.attributes[j].name.split('-');
            var isData = (parts.shift() === 'data');
            if (isData) {
                var name = parts.join('-');
                if ('placement' === name) {
                    name = 'zone';
                }
                if (typeof param[name] === 'undefined') {
                    param[name] = tag.attributes[j].value;
                }
            }
        }
        if (param.zone) {
            valid++;
            params.push(param);
            zone = zones[i] = {
                id: param.zone,
                width: param.width,
                height: param.height,
                options: parseZoneOptions(param.options),
                destElement: tag
            };

            //popups
            if ($isset(zone.options.count) && $isset(zone.options.interval)) {
                // Do not ask for popups if over limit
                if (!checkPopLimits(zone.options.count, zone.options.interval)) {
                    zone.__invalid = true;
                    param.__invalid = true;
                }
            }

            zone.backfill = findBackfillCode(tag);

            const checkFallbackRate = function () {
                if (Math.random() < zone.options.fallback_rate) {
                    zone.__invalid = true;
                    param.__invalid = true;
                    insertBackfill(tag, zone.backfill);
                }
            }

            if (zone.options.adblock_only) {
                waiting++;
                abd = abd || new BlockDetector();
                abd.detect(function () {
                    waiting--;
                    checkFallbackRate();

                }, function () {
                    zone.__invalid = true;
                    param.__invalid = true;
                    waiting--;
                    insertBackfill(tag, zone.backfill);
                })
            } else {
                checkFallbackRate();
            }

        }
    });

    if (0 === valid) {
        retryFn();
        return;
    }

    var fn;
    fn = function() {
        if (waiting > 0) {
            setTimeout(fn, 50);
        } else {
            var filter = function(x) { return !x.__invalid; };
            zones = zones.filter(filter);
            if (zones.length > 0) {
                call_func(zones, params.filter(filter));
            }
            retryFn();
        }
    }
    fn();
}

var bannersToLoad = 0;
var bannerLoaded = function() {
    bannersToLoad--;
    if (bannersToLoad <= 0) {
        allBannersLoaded();
    }
};

var isBannerPop = function (banner) {
    return (banner.type === 'direct' && (banner.scope === 'pop-up' || banner.scope === 'pop-under'));
}

domReady(function () {
    aduserPixel(getImpressionId(), function () {
        getActiveZones(function (zones, params) {
            const context = params.shift()
            const placements = params.map((p, index) => ({
                id: index.toString(),
                placementId: p.zone,
                topframe: !context.frame,
            }));
            const data = {
                context: {
                    iid: context.iid,
                    metamask: !!(context.metamask || 0),
                    url: context.url,
                },
                placements: placements,
            };
            const url = serverOrigin + '/supply/find';
            const options = {
                json: true,
                method: 'post',
                post: data,
            };

            fetchURL(url, options).then(function (banners) {
                bannersToLoad = 0;

                var bannerMap = {}
                banners.data.forEach((banner) => {
                    bannerMap[banner.id] = banner;
                });

                zones.forEach(function (zone, i) {
                    var requestId = i.toString();
                    var banner = bannerMap[requestId];
                    delete bannerMap[requestId];

                    if (!banner || typeof banner !== 'object') {
                        insertBackfill(zone.destElement, zone.backfill);
                        return;
                    }

                    banner.destElement = zone.destElement;
                    banner.backfill = zone.backfill;

                    if (zone.options.min_cpm > banner.rpm) {
                        insertBackfill(zone.destElement, zone.backfill);
                    } else {
                        bannersToLoad++;
                        fetchBanner(banner, {page: context, zone: params[i] || {}}, zone.options);
                    }
                });

                if (0 === popCandidates.length && !popCandidatesAdded) {
                    for (var requestId in bannerMap) {
                        var banner = bannerMap[requestId];
                        if (isBannerPop(banner)) {
                            bannersToLoad++;
                            fetchBanner(banner, {page: context, zone: {}}, {});
                        }
                    }
                }
            }, function () {
                zones.forEach(function (zone) {
                    if (!zone.destElement) {
                        console.log('no element to replace');
                        return;
                    }
                    insertBackfill(zone.destElement, zone.backfill);
                });
                allBannersLoaded();
            });

            addListener(topwin, 'message', function (event) {
                const hasAccess = dwmthACL.some(function (win) {
                    try {
                        return win && (win === event.source);
                    } catch (e) {
                        return false;
                    }
                });
                if (hasAccess && event.data) {
                    var data, isString = typeof event.data === 'string';
                    if (isString) {
                        data = JSON.parse(event.data);
                    } else {
                        data = event.data;
                    }
                    if (data.insertElem) {
                        data.insertElem.forEach(function (request) {
                            if (dwmthACL.length >= 5 * zones.length) {
                                return;
                            }
                            if (request.type === 'iframe') {
                                if (dwmthURLS[request.url]) {
                                    return;
                                }
                                var iframe = addAnalyticsIframe(request.url);
                                dwmthACL.push(iframe.contentWindow);
                                dwmthURLS[request.url] = 1;
                            } else if (request.type === 'img') {
                                if (dwmthURLS[request.url]) {
                                    return;
                                }
                                addAnalyticsImage(request.url);
                                dwmthACL.push(null);
                                dwmthURLS[request.url] = 1;
                            }
                        });
                    }
                }
            });
        })
    })
});

var addAnalyticsIframe = function (url) {
    if (!url) {
        return;
    }
    const iframe = createIframeFromUrl(url, topdoc);
    topdoc.body.appendChild(iframe);
    setTimeout(function () {
        iframe.parentElement.removeChild(iframe);
    }, 10000);
    return iframe;
};

var addAnalyticsIframeWithHtmlContent = function (html) {
    const iframe = createIframeFromUrl('about:blank', topdoc);
    iframe.srcdoc = html;
    topdoc.body.appendChild(iframe);
    setTimeout(function () {
        iframe.parentElement.removeChild(iframe);
    }, 10000);
    return iframe;
};

var addAnalyticsImage = function (url) {
    if (!url) {
        return;
    }
    var img = new Image();
    img.setAttribute('style', 'display:none');
    img.setAttribute('width', 1);
    img.setAttribute('height', 1);
    img.src = url;
    topdoc.body.appendChild(img);
    return img;
};

var mapInt = function(hex, min) {
    var short = hex.substr(0, 8);
    if (short.charAt(0) > '7') {
        short = '7' + short.substr(1);
    }
    var r = parseInt(short, 16);
    if (min && r < min) {
        r += 1*min;
    }
    return r;
}

var fillPlaceholders = function(url, caseId, bannerId, publisherId, serverId, siteId, zoneId, keywords) {
    var hashPos = url.indexOf('#');
    if (hashPos !== -1) {
        url = url.substr(0, hashPos);
    }
    if (url.indexOf('{cid}') === -1) {
        url = addUrlParam(url, 'cid', caseId);
    } else {
        url = url.replace('{cid}', caseId);
    }

    return url.replace('{bid}', bannerId)
        .replace('{pid}', publisherId)
        .replace('{aid}', serverId)
        .replace('{sid}', siteId)
        .replace('{zid}', zoneId)
        .replace('{zid:int}', mapInt(zoneId, 100000))
        .replace('{kwd}', keywords)
        .replace('{rand}', Math.random().toString().substr(2));
};
var getDomain = function (url) {
    var a = document.createElement('a');
    a.href = url;
    var host = a.host.indexOf('www.') === 0 ? a.host.substr(4) :a.host;
    var colonPos = host.indexOf(':');
    return colonPos === -1 ? host : host.substr(0, colonPos);
};

var popCandidatesAdded = false;
var popCandidates = [];
var addPopCandidate = function(args, rpm) {
    popCandidates.push({args: args, rpm: rpm});
}

function shuffle(array) {
    var tmp, current, top = array.length;

    if (top) {
        while (--top) {
            current = Math.floor(Math.random() * (top + 1));
            tmp = array[current];
            array[current] = array[top];
            array[top] = tmp;
        }
    }

    return array;
}

var allBannersLoaded = function() {
    var hasNulls = popCandidates.some(function(x) {
        return x.rpm === null;
    });
    if (hasNulls) {
        shuffle(popCandidates);
    } else {
        popCandidates.sort(function (x, y) {
            return x.rpm >= y.rpm ? -1 : 1;
        });
    }
    popCandidates.forEach(function(item) {
        addPop.apply(this, item.args);
    });
    popCandidates = [];
    popCandidatesAdded = true;
}

var fetchBanner = function (banner, context, zone_options) {
    fetchURL(banner.serveUrl, {
        binary: true,
        noCredentials: true
    }).then(function (data, _xhr) {
        context.cid = getCid();
        context.page.zone = banner.placementId;
        context.click_url = addUrlParam(
            banner.clickUrl,
            {
                'cid': context.cid,
            }
        );
        context.view_url = addUrlParam(
            banner.viewUrl,
            {
                'cid': context.cid,
            }
        );

        var sendViewEvent = function(element) {
            // record view if visible for 1 second
            var timer = setInterval(function () {
                if (isVisible(element)) {
                    clearInterval(timer);
                    const options = {
                        json: true,
                        noCredentials: true,
                    };
                    fetchURL(context.view_url, options)
                        .then(function (data, _xhr) {
                            if (data.type && data.type.startsWith('text/html')) {
                                dwmthACL.push(addAnalyticsIframeWithHtmlContent(data.bytes).contentWindow);
                            } else {
                                const urls = data.context || [];
                                for (const url of urls) {
                                    dwmthACL.push(addAnalyticsIframe(url).contentWindow);
                                }
                            }
                        });
                }
            }, 1000);
        };

        var displayBanner = function () {
            var caller;

            if (banner.type === 'image') {
                caller = createImageFromData;
            } else if (banner.type === 'video') {
                caller = createVideoFromData;
            } else if (banner.type === 'html') {
                caller = createIframeFromData;
            } else if (banner.type === 'direct') {
                createLinkFromData(data, function(url) {
                    if (url.length > 1024) {
                        url = banner.serveUrl;
                    }
                    context.skip_overlay = (url.indexOf('#dwmth') !== -1);
                    url = fillPlaceholders(url, context.cid, banner.creativeId, banner.publisherId, banner.supplyServer, getDomain(context.page.url), banner.placementId, context.page.keywords);

                    if (banner.scope === 'pop-up' || banner.scope === 'pop-under') {
                        addPopCandidate(
                            [
                                banner.scope,
                                url,
                                $pick(zone_options.count, 1),
                                $pick(zone_options.interval, 1),
                                $pick(zone_options.burst, 1),
                                function () {
                                    dwmthACL.push(addAnalyticsIframe(context.view_url).contentWindow);
                                }
                            ],
                            banner.rpm
                        );
                        bannerLoaded();
                    } else {
                        data.iframe_src = url;
                        caller = createIframeFromSrc;
                    }

                });
            }
            caller && caller(data, function (element) {
                if (!banner.destElement) {
                    console.log('warning: no element to replace');
                    bannerLoaded();
                    return;
                }
                element = prepareElement(context, banner, element);
                replaceTag(banner.destElement, element, banner);
                sendViewEvent(element);
                bannerLoaded();
            });
        };

        var displayIfVisible = function() {
            if (isBannerPop(banner) || !banner.destElement) {
                displayBanner();
            } else {
                if (isVisible(banner.destElement)) {
                    displayBanner();
                } else {
                    var n = 0, fn;
                    setTimeout(fn = function () {
                        if (isVisible(banner.destElement)) {
                            displayBanner();
                        } else {
                            setTimeout(fn, n++ < 10 ? 200 : 1000);
                        }
                    }, 100);
                }
            }
        };

        if (banner.hash) {
            sha1_async(data, function (hash) {
                if (hash === 'NO_SUPPORT' || hash === banner.hash) {
                    if (hash === 'NO_SUPPORT') {
                        console.log('warning: hash not checked');
                    }
                    displayIfVisible();
                } else {
                    console.log('hash error', banner, hash);
                    insertBackfill(banner.destElement, banner.backfill);
                    bannerLoaded();
                }
            });
        } else {
            displayIfVisible();
        }
    }, function () {
        console.log('could not fetch url', banner);
        insertBackfill(banner.destElement, banner.backfill);
        bannerLoaded();
    });
};
