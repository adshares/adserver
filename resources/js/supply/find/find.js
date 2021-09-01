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

var serverOrigin = '{{ ORIGIN }}';
var selectorClass = '{{ SELECTOR }}';


var topwin = window;
try {
    while(topwin.parent != topwin && topwin.parent.document) {
        topwin = topwin.parent;
    }
} catch(e) {

}
var topdoc = topwin.document;

var winOpen = (function(open)
{
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
    return UrlSafeBase64Encode(result.join(ZONE_GLUE)); // url safe encoding
};


var insertedElements = [];
var logInsertedElement = function(el) {
    if(insertedElements.length === 0) {
        addListener(window, 'beforeunload', function (event) {
            var x;
            while (x = insertedElements.pop()) {
                x.parentElement && x.parentElement.removeChild(x);
            }
        });
    }
    insertedElements.push(el);
}

var dwmthACL = [];
var dwmthURLS = [];

var replaceTag = function (oldTag, newTag) {
    for (var i = 0; i < oldTag.attributes.length; i++) {
        var name = oldTag.attributes[i].name;
        if (name.indexOf('data-') != 0) {
            newTag.setAttribute(name, oldTag.getAttribute(name));
        }
    }
    newTag.style.overflow = 'hidden';
    newTag.style.position = 'relative';

    // ios 12 fix
    var el = [];
    while(newTag.firstChild){
        el.push(newTag.removeChild(newTag.firstChild));
    }
    // ios 12 fix

    oldTag.parentNode.replaceChild(newTag, oldTag);

    // ios 12 fix
    setTimeout(function(){
        while(el.length > 0) {
            newTag.appendChild(el.shift());
        }
    }, 0);
    // ios 12 fix

    logInsertedElement(newTag);
};


var prepareElement = function (context, banner, element, contextParam) {
    var div = document.createElement('div');
    var clickOverlay;

    var infoBox = prepareInfoBox(context, banner, contextParam);
    div.appendChild(infoBox);

    if (element.tagName == 'IFRAME') {

        if(banner.type == 'direct' && !context.skip_overlay) {
            clickOverlay = document.createElement('a');
            clickOverlay.style.cssText = "display:block; position: absolute !important; top: 0px !important; left: 0px !important; right: 0px !important; bottom: 0px !important";
            clickOverlay.setAttribute('href', context.click_url);
            clickOverlay.setAttribute('target', '_blank');
            div.insertBefore(clickOverlay, infoBox);
        }

        prepareIframe(element);
        addListener(window, 'message', function (event) {
            if (event.source == element.contentWindow && event.data) {
                var data, isString = typeof event.data == "string";
                if (isString) {
                    data = JSON.parse(event.data);
                } else {
                    data = event.data;
                }
                if (data.dwmthLoad) {
                    if(clickOverlay) { // ad is aware of mechanics
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

var prepareInfoBox = function (context, banner, contextParam) {
    var url = addUrlParam(serverOrigin + '/supply/why', {
        'bid': banner.id,
        'cid': context.cid,
        'ctx': contextParam,
        'iid': getImpressionId(),
        'url': banner.serve_url,
    });


    var div = document.createElement('div');
    div.setAttribute('style', 'position: absolute !important; top: 0px !important; right: 0px !important;background-color: #fff !important');

    var link = document.createElement('a');
    link.target = '_blank';
    link.href = url;

    link.setAttribute('style', 'text-decoration: none !important;background-color: #fff !important');

    link.innerHTML = '<svg style="width: 16px; height: 16px; display: block;" version="1.1" viewBox="0 0 15 15" xmlns="http://www.w3.org/2000/svg">' +
        '<path d="m11.405 1.9861v6.798c0 0.0309-0.0019 0.0617-0.0037 0.0902a1.1895 1.1895 0 0 1 -0.3426 0.77162 1.1192 1.1192 0 0 1 -0.8315 0.35834h-0.0037q-0.48088 0-0.82718-0.35865a1.1988 1.1988 0 0 1 -0.3463 -0.86205v-2e-3c0-0.0253 0-0.0503 0.00187-0.0753-0.0028-0.67655-0.15649-1.2988-0.45989-1.8602a4.3519 4.3519 0 0 0 -0.77725 -1.0493q-0.15247-0.15432-0.31235-0.28796-1.1016-0.92595-2.5596-0.9528c-0.030893 0-0.059919-2e-3 -0.090438-2e-3q-1.8417 0-3.097 1.2186-1.2553 1.2185-1.255 3.0031v8e-3q0.0028 1.747 1.2546 2.9868 1.2519 1.2398 3.0248 1.2429 1.3062 0 2.3303-0.67687-0.14908-0.12593-0.29167-0.26667c-0.6852-0.67903-1.1074-1.4744-1.2608-2.3741a1.1111 1.1111 0 0 1 -0.78613 0.31018q-0.48119 0-0.82717-0.35864a1.1982 1.1982 0 0 1 -0.3463 -0.86369q0-0.51297 0.34229-0.8676a1.1111 1.1111 0 0 1 0.61112 -0.3352 1.2185 1.2185 0 0 1 0.22007 -0.0198 1.117 1.117 0 0 1 0.72038 0.24692c0.024733 0.0225 0.049092 0.0456 0.072798 0.0701a1.4213 1.4213 0 0 1 0.36853 0.66792 1.3917 1.3917 0 0 1 0.020066 0.23827 4.4245 4.4245 0 0 0 0.087078 0.87626c0.14044 0.78365 0.50895 1.4652 1.1173 2.0679 0.098147 0.0972 0.33334 0.30063 0.34846 0.31328q1.0781 0.90033 2.5 0.96638c0.07252 4e-3 0.14599 5e-3 0.21976 6e-3h0.0047c0.96607 0 2.0105-0.21236 3.0195-1.2389 1.009-1.0266 1.2503-2.1661 1.2503-2.9886v-6.7992zm-1.9874 5.9023a1.1093 1.1093 0 0 1 0.80588 -0.32964h0.0037a1.1161 1.1161 0 0 1 0.7386 0.26389v-3.2195a4.2837 4.2837 0 0 0 -0.66113 -0.0515c-0.02623 0-0.05124 3e-3 -0.07746 4e-3q-1.3581 0.0194-2.3923 0.70773 0.18519 0.15185 0.35772 0.32654c0.65742 0.66175 1.0676 1.4324 1.2247 2.2991z" fill="#5fb2f9" stroke-width=".030865"/>' +
        '</svg>';

    div.appendChild(link);

    return div;
};

// checks if element is not hidden with display: none
// function isRendered(domObj) {
// return (domObj.offsetParent !== null);
// }

function isRendered(domObj) {
    if (domObj.nodeType != 1)
        return true;
    while (domObj != document.body) {
        if (window.getComputedStyle) {
            var cs = document.defaultView.getComputedStyle(domObj, null);
            if (cs.getPropertyValue("display") == "none" || cs.getPropertyValue("visibility") == "hidden") {
                return false;
            }
        } else if (domObj.currentStyle
            && (domObj.currentStyle["display"] == "none" || domObj.currentStyle["visibility"] == "hidden")) {
            return false;
        } else {
            return true;
        }
        domObj = domObj.parentNode;
    }
    return true;
}

function viewSizeWin(w)
{
    var left = 0, top = 0;
    var doc = w.document;
    var docEl = (doc.compatMode && doc.compatMode === 'CSS1Compat')?
        doc.documentElement: doc.body;

    var width = docEl.clientWidth;
    var height = docEl.clientHeight;

    // mobile zoomed in?
    if ( w.innerWidth && width > w.innerWidth ) {
        width = w.innerWidth;
        height = w.innerHeight;
    }


    return {width: width, height: height, left: left, top: top, right: width, bottom: height};
}

function locateFrameElement(w_parent, w)
{
    var frames = w_parent.document.getElementsByTagName('iframe');
    for(var i=0,n=frames.length;i<n;i++) {
        if(frames[i].contentWindow == w) {
            return frames[i];
        }
    }
    return null;
}

function viewSize() {
    var w = window;
    var size = viewSizeWin(w);
    // console.log(w.location.href, size);
    while(w != topwin) {

        var parent_size = viewSizeWin(w.parent);
        var frame_el = locateFrameElement(w.parent, w);
        var rect = getBoundRect(frame_el);
        var isect = rectIntersect(parent_size, rect);
        isect.left -= rect.left;
        isect.right -= rect.left;
        isect.top -= rect.top;
        isect.bottom -= rect.top;
        // console.log(w.location.pathname, isect);
        size = rectIntersect(size, isect);
        w = w.parent;
    }

    return size;
}

function rectIntersect(a, b)
{
    var x = Math.max(a.left, b.left);
    var num1 = Math.min(a.left + a.width, b.left + b.width);
    var y = Math.max(a.top, b.top);
    var num2 = Math.min(a.top + a.height, b.top + b.height);
    if (num1 >= x && num2 >= y)
        return {left: x, top: y, width: num1 - x, height: num2 - y, bottom: num2, right: num1};
    else
        return false;
}

function getBoundRect(el, overflow) {
    var left = 0, top = 0;
    var width = el.offsetWidth, height = el.offsetHeight;

    if(overflow) {
        var css = el.ownerDocument.defaultView.getComputedStyle(el);
        if (css.overflowX == 'visible') {
            width = 200000;
            left = -100000;
        }
        if (css.overflowY == 'visible') {
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


var isOccluded = function(rect, el)
{
    if(!rect) return true;
    outer:
    for(var i=0; i < 10; i++) {
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
    if (!isRendered(el) || !isWindowVisible())
        return false;
    return true;
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
    if (!serverOrigin) return;
    var path = '/supply/register?iid=';
    var url = serverOrigin + path + impressionId;

    if(dwmthURLS[url]) return false;
    // adusers from other find.js
    var tags = document.querySelectorAll('iframe[src*="' + path + '"]');
    if(tags.length) {
        return false;
    }

    var iframe = createIframeFromUrl(url);

    if(onload) {
        var loaded = false;
        var loadFn = function() {
            if(loaded) return;
            loaded = true;
            onload();
        };
        iframe.onerror = iframe.onabort = iframe.onload = loadFn;
        setTimeout(loadFn, 500);
    }


    document.body.appendChild(iframe);
    dwmthACL.push(iframe.contentWindow);
    dwmthURLS[url] = 1;
    return true;
};

var createIframeFromUrl = function createIframeFromUrl(url, doc) {
    var iframe = (doc || document).createElement('iframe');
    iframe.setAttribute('style', 'display:none');
    iframe.setAttribute('width', 1);
    iframe.setAttribute('height', 1);
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
    return {
        iid: getImpressionId(),
        frame: (topwin == top ? 0 : 1),
        width: topwin.screen.width,
        height: topwin.screen.height,
        url: topwin.location.href,
        keywords: getPageKeywords(topdoc),
        ref: topdoc.referrer,
        pop: topwin.opener !== null && topwin.opener !== undefined ? 1 : 0
        // agent: window.navigator.userAgent
    }
};

var findBackfillCode = function(container) {
    var tag = container.querySelectorAll('[type="app/backfill"]')[0];
    var text = null;
    if(tag) {
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
    if(typeof str != 'string') {
        return opts;
    }
    var parts = str.split(',');
    for(var i =0; i < parts.length; i++) {
        var part = parts[i].trim();
        var name_val = part.split('=', 2);
        opts[name_val[0]] = name_val.length == 1 ? true : name_val[1];
    }
    return opts;
};

var abd;

var getActiveZones = function(call_func) {
    var _tags = document.querySelectorAll(selectorClass + '[data-zone]');
    var n = _tags.length;

    var tags = [];
    for(var i=0;i<n;i++) {
        tags[i] = _tags[i];
    }

    if (n == 0) {
        return;
    }

    var param, params = [];
    params.push(getBrowserContext());

    var zones = [];

    var valid = 0;
    var waiting = 0;
    tags.forEach(function(tag, i) {
        var zone;
        var tag = tags[i];
        if(tag.__dwmth) {
            return;
        }
        tag.__dwmth = 1;
        param = {};
        param.width = parseInt(tag.offsetWidth) || parseInt(tag.style.width) || 0;
        param.height = parseInt(tag.offsetHeight) || parseInt(tag.style.height) || 0;
        for (var j = 0, m = tag.attributes.length; j < m; j++) {
            var parts = tag.attributes[j].name.split('-');
            var isData = (parts.shift() == "data");
            if (isData && typeof param[parts.join('-')] == 'undefined') {
                param[parts.join('-')] = tag.attributes[j].value;
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
            if($isset(zone.options.count) && $isset(zone.options.interval)) {
                // Do not ask for popups if over limit
                if(!checkPopLimits(zone.options.count, zone.options.interval)) {
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

    if(valid == 0) {
        return;
    }

    var fn;
    fn = function(){
        if(waiting > 0) {
            setTimeout(fn, 50);
        } else {
            var filter = function(x) { return !x.__invalid; };
            call_func(zones.filter(filter), params.filter(filter));
        }
    }
    fn();
}

var extraBannerCheck = function(banner, code)
{
    try {
        return (new topwin.Function('banner', code))(banner);
    } catch(e) {
        return false;
    }
}

var bannersToLoad = 0;
var bannerLoaded = function() {
    bannersToLoad--;
    if(bannersToLoad <= 0) {
        allBannersLoaded();
    }
};

domReady(function () {
    aduserPixel(getImpressionId(), function () {
        getActiveZones(function (zones, params) {
            var data = encodeZones(params);

            var url = serverOrigin + '/supply/find';
            var options = {
                json: true
            };
            if (data.length <= 800) {
                // safe limit for url
                url += '?' + data;
            } else {
                options.method = 'post';
                options.post = data;
            }

            fetchURL(url, options).then(function (banners) {
                bannersToLoad = 0;

                banners.forEach(function (banner, i) {
                    var zone = zones[i] || {options: {}};

                    if (!banner || typeof banner != 'object') {
                        insertBackfill(zone.destElement, zone.backfill);
                        return;
                    }

                    if (banner.extra_check) {
                        if (!extraBannerCheck(banner, banner.extra_check)) {
                            insertBackfill(zone.destElement, zone.backfill);
                            return;
                        }
                    }

                    banner.destElement = zone.destElement;
                    banner.backfill = zone.backfill;

                    if (zone.options.min_cpm > banner.rpm) {
                        insertBackfill(zone.destElement, zone.backfill);
                    } else {
                        bannersToLoad++;
                        fetchBanner(banner, {page: params[0], zone: params[i + 1] || {}}, zone.options);
                    }
                });
            }, function () {
                zones.forEach(function (zone, i) {
                    if (!zone.destElement) {
                        console.log('no element to replace');
                        return;
                    }
                    insertBackfill(zone.destElement, zone.backfill);
                });
                allBannersLoaded();
            });

            addListener(topwin, 'message', function (event) {
                var has_access = dwmthACL.some(function (win) {
                    try {
                        return win && (win === event.source);
                    } catch (e) {
                        return false;
                    }
                });
                if (has_access && event.data) {
                    var data, isString = typeof event.data == "string";
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
                            if (request.type == 'iframe') {
                                if (dwmthURLS[request.url]) {
                                    return;
                                }
                                var iframe = addTrackingIframe(request.url);
                                dwmthACL.push(iframe.contentWindow);
                                dwmthURLS[request.url] = 1;
                            } else if (request.type == 'img') {
                                if (dwmthURLS[request.url]) {
                                    return;
                                }
                                addTrackingImage(request.url);
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

var addTrackingIframe = function (url) {
    if (!url) return;
    var iframe = createIframeFromUrl(url, topdoc);
    topdoc.body.appendChild(iframe);
    setTimeout(function() {
        iframe.parentElement.removeChild(iframe);
    }, 10000);
    return iframe;
};

var addTrackingImage = function (url) {
    if (!url) return;
    var img = new Image();
    img.setAttribute('style', 'display:none');
    img.setAttribute('width', 1);
    img.setAttribute('height', 1);
    img.src = url;
    topdoc.body.appendChild(img);
    return img;
};

var mapInt = function(hex, min)
{
    var short = hex.substr(0, 8);
    if(short.charAt(0) > '7') {
        short = '7' + short.substr(1);
    }
    var r = parseInt(short, 16);
    if(min && r < min) {
        r += 1*min;
    }
    return r;
}

var fillPlaceholders = function(url, caseId, bannerId, publisherId, serverId, siteId, zoneId, keywords)
{
    var hashPos = url.indexOf('#');
    if(hashPos !== -1) {
        url = url.substr(0, hashPos);
    }
    if(url.indexOf('{cid}') === -1) {
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
var getDomain = function(url)
{
    var a = document.createElement('a');
    a.href = url;
    var host = a.host.indexOf('www.') === 0 ? a.host.substr(4) :a.host;
    var colonPos = host.indexOf(':');
    return colonPos == -1 ? host : host.substr(0, colonPos);
};

var popCandidates = [];
var addPopCandidate = function(args, rpm)
{
    popCandidates.push({args: args, rpm: rpm});
}

function shuffle(array) {
    var tmp, current, top = array.length;

    if(top) while(--top) {
        current = Math.floor(Math.random() * (top + 1));
        tmp = array[current];
        array[current] = array[top];
        array[top] = tmp;
    }

    return array;
}

var allBannersLoaded = function()
{
    var hasNulls = popCandidates.some(function(x) {
        return x.rpm === null;
    });
    if(hasNulls) {
        shuffle(popCandidates);
    } else {
        popCandidates.sort(function (x, y) {
            return hasNulls ? (Math.random() > 0.5 ? -1 : 1) : (x.rpm >= y.rpm ? -1 : 1);
        });
    }
    popCandidates.forEach(function(item) {
        addPop.apply(this, item.args);
    });
}

var fetchBanner = function (banner, context, zone_options) {
    fetchURL(banner.serve_url, {
        binary: true,
        noCredentials: true
    }).then(function (data, xhr) {
        context.cid = getCid();

        context.page.zone = context.zone.zone || banner.zone_id;
        var contextParam = encodeZones([context.page]);
        context.click_url = addUrlParam(banner.click_url,
            {
                'cid': context.cid,
                'pto': banner.pay_to,
                'pfr': banner.pay_from,
                'ctx': contextParam,
                'iid': getImpressionId()
            });
        context.view_url = addUrlParam(banner.view_url,
            {
                'cid': context.cid,
                'pto': banner.pay_to,
                'pfr': banner.pay_from,
                'ctx': contextParam,
                'iid': getImpressionId()
            });

        var sendViewEvent = function(element) {
            // record view if visible for 1 second
            var timer = setInterval(function () {
                if (isVisible(element)) {
                    clearInterval(timer);
                    dwmthACL.push(addTrackingIframe(context.view_url).contentWindow);
                }
            }, 1000);
        };

        var displayBanner = function () {
            var caller;

            if (banner.type == 'image') {
                caller = createImageFromData;
            } else if (banner.type == 'html') {
                caller = createIframeFromData;
            } else if (banner.type == 'direct') {
                createLinkFromData(data, function(url) {
                    if(url.length > 1024) {
                        url = banner.serve_url;
                    }
                    context.skip_overlay = (url.indexOf('#dwmth') != -1);
                    url = fillPlaceholders(url, context.cid, banner.id, banner.publisher_id, banner.pay_to, getDomain(context.page.url), banner.zone_id, context.page.keywords);

                    if(banner.size == 'pop-up' || banner.size == 'pop-under') {
                        addPopCandidate([banner.size,
                            url,
                            $pick(zone_options.count, 1),
                            $pick(zone_options.interval, 1),
                            $pick(zone_options.burst, 1),
                            function () {
                                dwmthACL.push(addTrackingIframe(context.view_url).contentWindow);
                            }], banner.rpm
                        );
                    } else {
                        data.iframe_src = url;
                        caller = createIframeFromSrc;
                    }

                });
            }
            caller && caller(data, function (element) {
                if(!banner.destElement) {
                    console.log('warning: no element to replace');
                    return;
                }
                element = prepareElement(context, banner, element);
                replaceTag(banner.destElement, element);
                sendViewEvent(element);
            });
        };

        var displayIfVisible = function()
        {
            if ((banner.type == 'direct' && (banner.size == 'pop-up' || banner.size == 'pop-under')) || !banner.destElement) {
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

        if (banner.creative_sha1) {
            sha1_async(data, function (hash) {
                if (hash === 'NO_SUPPORT' || hash == banner.creative_sha1) {
                    if(hash === 'NO_SUPPORT') {
                        console.log('warning: hash not checked');
                    }
                    displayIfVisible();
                } else {
                    console.log('hash error', banner, hash);
                    insertBackfill(banner.destElement, banner.backfill);
                }
            });
        } else {
            displayIfVisible();
        }
        bannerLoaded();
    }, function () {
        console.log('could not fetch url', banner);
        insertBackfill(banner.destElement, banner.backfill);
        bannerLoaded();
    });
};
