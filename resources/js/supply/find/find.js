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
var aduserOrigin = '{{ ADUSER }}';
var selectorClass = '{{ SELECTOR }}';

if(window['serverOrigin:' + serverOrigin]) {
    return;
}
window['serverOrigin:' + serverOrigin] = 1;

var UrlSafeBase64Encode = function (data) {
    return btoa(data).replace(/=|\+|\//g, function (x) {
        return x == '+' ? '-' : (x == '/' ? '_' : '')
    });
};

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

var replaceTag = function (oldTag, newTag) {
    for (var i = 0; i < oldTag.attributes.length; i++) {
        var name = oldTag.attributes[i].name;
        if (name.indexOf('data-') != 0) {
            newTag.setAttribute(name, oldTag.getAttribute(name));
        }
    }
    newTag.style.overflow = 'hidden';
    oldTag.parentNode.replaceChild(newTag, oldTag);
};

var addListener = function (element, event, handler, phase) {
    if (element.addEventListener) {
        return element.addEventListener(event, handler, phase);
    } else {
        return element.attachEvent('on' + event, handler);
    }
};

navigator.sendBeacon = navigator.sendBeacon || function (url, data) {
    fetchURL(url, {
        method: 'post',
        post: data
    });
};

var prepareElement = function (context, banner, element, contextParam) {
    var div = document.createElement('div');
    div.setAttribute('style', 'position: relative; z-index: 1;');

    var infoBox = prepareInfoBox(context, banner, contextParam);
    div.appendChild(infoBox);

    if (element.tagName == 'IFRAME') {
        var mouseover = false, evFn;
        addListener(element, 'mouseenter', evFn = function () {
            mouseover = true;
        });
        addListener(element, 'mouseover', evFn);
        addListener(element, 'mouseleave', function () {
            mouseover = false;
        });

        element.setAttribute('width', '100%');
        element.setAttribute('height', '100%');
        element.setAttribute('marginwidth', '0');
        element.setAttribute('marginheight', '0');
        element.setAttribute('vspace', '0');
        element.setAttribute('hspace', '0');
        element.setAttribute('allowtransparency', 'true');
        element.setAttribute('scrolling', 'no');
        element.setAttribute('frameborder', '0');

        addListener(window, 'message', function (event) {
            if (event.source == element.contentWindow && event.data) {
                var data, isString = typeof event.data == "string";
                if (isString) {
                    data = JSON.parse(event.data);
                } else {
                    data = event.data;
                }
                if (data.adsharesLoad) {
                    var msg = {adsharesLoad: 1, data: context};

                    event.source.postMessage(isString ? JSON.stringify(msg) : msg, '*');
                } else if (data.adsharesClick) {
                    if (!mouseover && document.activeElement != element) {
                        console.log('click without mouse interaction');
                        return;
                    }
                    if (!isVisible(element)) {
                        console.log('invisible click');
                        return;
                    }
                    var url = context.click_url;
                    if (!window.open(url, '_blank')) {
                        top.location.href = url;
                    }
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

var prepareInfoBox = function prepareInfoBox(context, banner, contextParam) {

    var url = addUrlParam('http://localhost:8101/supply/why', {
        'cid': context.cid,
        'ctx': contextParam,
        'iid': getImpressionId(),
        'url': banner.serve_url,
    });


    var div = document.createElement('div');
    div.setAttribute('style', 'position: absolute; top: 0; right: 0;');

    var link = document.createElement('a');
    link.target = '_blank';
    link.href = url;

    var image = new Image();
    image.src = 'http://localhost:8101/img/watermark.png';

    var linkText = document.createTextNode('>>');

    link.setAttribute('style', 'text-decoration: none');
    link.appendChild(image);
    link.appendChild(linkText);

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
    };
    return true;
}

function getBoundRect(el) {
    var left = 0, top = 0;
    var width = el.offsetWidth, height = el.offsetHeight;

    do {
        left += el.offsetLeft - el.scrollLeft;
        top += el.offsetTop - el.scrollTop;
    } while (el = (el == document.body ? document.documentElement : el.offsetParent));

    return {
        top: top,
        bottom: top + height,
        left: left,
        right: left + width,
        width: width,
        height: height
    }
}

// checks if eleemnt is visible on screen
var isVisible = function (el) {
    if (!isRendered(el))
        return false;

    var rect = getBoundRect(el),
        top = rect.top,
        height = rect.height,
        left = rect.left,
        width = rect.width,
        el = el.parentNode;
    while (el != document.body) {
        rect = getBoundRect(el);
        if (top <= rect.bottom === false)
            return false;
        if (left <= rect.right === false)
            return false;
        // Check if the element is out of view due to a container scrolling
        if ((top + height) < rect.top)
            return false;
        if ((left + width) < rect.left)
            return false;
        el = el.parentNode;
    };
    // Check its within the document viewport
    return top <= Math.max(document.documentElement.clientHeight, window.innerHeight ? window.innerHeight : 0)
        && top > -height
        && left <= Math.max(document.documentElement.clientWidth, window.innerWidth ? window.innerWidth : 0)
        && left > -width;
};

var addUrlParam = function (url, names, value) {

    if (typeof name != 'object') {
        name = {};
        name[name] = value;
    }
    for (var name in names) {
        value = names[name];
        var param = name + '=' + encodeURIComponent(value);
        var qPos = url.indexOf('?');
        if (qPos > -1) {
            url += (qPos < url.length ? '&' : '') + param;
        } else {
            url += '?' + param;
        }
    }
    return url;
};

var impressionId;
var getImpressionId = function () {
    if (!impressionId) {
        var d = new Date().getTime();

        var chars = [];
        for (var i = 0; i < 16; i++) {
            var r = (d + Math.random() * 256) % 256 | 0;
            d = Math.floor(d / 256);
            chars.push(String.fromCharCode(r));
        }

        impressionId = UrlSafeBase64Encode(chars.join(''));
    }
    return impressionId;
};

var aduserPixel = function (impressionId) {
    if (!aduserOrigin) return;

    var iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'display:none');
    iframe.setAttribute('width', 1);
    iframe.setAttribute('height', 1);
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');
    iframe.src = serverOrigin + '/supply/register?iid=' + impressionId;

    document.body.appendChild(iframe);
};

var getPageKeywords = function () {

    var MAX_KEYWORDS = 10;
    var metaKeywords = document.querySelector("meta[name=keywords]");

    if (metaKeywords === null) {
        return '';
    }

    if (metaKeywords.content) {
        var tmp = metaKeywords.content.split(',');
        var n = Math.min(MAX_KEYWORDS, tmp.length);
        metaKeywords = [];
        for (var i = 0; i < n; i++) {
            metaKeywords.push(tmp[i].trim());
        }
        metaKeywords = metaKeywords.join(',');
    }
    return metaKeywords;
};

var getBrowserContext = function () {
    return {
        iid: getImpressionId(),
        frame: (window == top ? 0 : 1),
        width: window.screen.width,
        height: window.screen.height,
        url: window.location.href,
        keywords: getPageKeywords()
        // agent: window.navigator.userAgent
    }
};

domReady(function () {

    aduserPixel(getImpressionId());

    var tags = document.querySelectorAll(selectorClass + '[data-pub]');
    var n = tags.length;

    if (n == 0) {
        return;
    }

    var param, params = [];
    params.push(getBrowserContext());

    for (var i = 0; i < n; i++) {
        var tag = tags[i];
        param = {};
        param.width = parseInt(tag.offsetWidth) || parseInt(tag.style.width);
        param.height = parseInt(tag.offsetHeight) || parseInt(tag.style.height);
        for (var j = 0, m = tag.attributes.length; j < m; j++) {
            var parts = tag.attributes[j].name.split('-');
            var isData = (parts.shift() == "data");
            if (isData) {
                param[parts.join('-')] = tag.attributes[j].value;
            }
        }
        if (param.zone && param.pub) {
            params.push(param);
        }
    }

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
        var foundTagIndexes = [];
        banners.forEach(function (banner, i) {
            if (!banner)
                return;

            var foundIndex = findDestination(banner.zone_id, tags, foundTagIndexes);

            if (foundIndex === null) {
                foundIndex = findDestination(banner.zone_id, tags, []);
            }

            banner.destElement = tags[foundIndex];

            foundTagIndexes.push(foundIndex);

            if (!banner.destElement) {
                console.log('no element to replace', banner);
                return;
            }

            if (isVisible(banner.destElement)) {
                fetchBanner(banner, {page: params[0], zone: params[i + 1]});
            } else {
                var timer = setInterval(function () {
                    if (isVisible(banner.destElement)) {
                        clearInterval(timer);
                        fetchBanner(banner, {page: params[0], zone: params[i + 1]});
                    }
                }, 200);
            }
        })
    });
});

var findDestination = function (zoneId, tags, excludedTags) {
    if (!zoneId) {
        return;
    }

    for (var i = 0; i < tags.length; i++) {
        var dataZone = tags[i].getAttribute('data-zone');

        if (!dataZone) {
            return;
        }
        if (dataZone.toLowerCase() === zoneId.toLowerCase()
            && (excludedTags.length === 0 || excludedTags.indexOf(i) === -1)
        ) {
            return i;
        }
    }
};

var addTrackingPixel = function (context, banner, element) {
    if (!context.view_url) return;
    var img = new Image();
    img.setAttribute('style', 'display:none');
    img.setAttribute('width', 1);
    img.setAttribute('height', 1);
    img.src = context.view_url;
    element.parentNode.insertBefore(img, element);
};

var fetchBanner = function (banner, context) {
    fetchURL(banner.serve_url, {
        binary: true
    }).then(function (data, xhr) {
        context.cid = xhr.getResponseHeader('X-Adshares-Cid');

        context.page.zone = context.zone.zone;
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

        var fn = function () {
            var caller;
            if (data.type.indexOf('image/') != -1) {
                caller = createImageFromData;
            } else {
                caller = createIframeFromData;
//                data.bytes = data.bytes
//                 .replace("'{{ADSHARES_JSON}}'", JSON.stringify({'context':
//                 context, 'click_url': addUrlParam(banner.click_url, 'cid',
//                 banner.cid)}))
//                 .replace('{{ADSHARES_CLICK_URL}}',
//                 addUrlParam(banner.click_url, 'cid', banner.cid));
            }
            caller(data, function (element) {
                element = prepareElement(context, banner, element);
                replaceTag(banner.destElement, element);
                addTrackingPixel(context, banner, element);
            });
        };
        if (banner.creative_sha1) {
            sha1_async(data, function (hash) {
                if (hash == banner.creative_sha1) {
                    fn();
                } else {
                    console.log('hash error', banner, hash);
                }
            });
        } else {
            fn();
        }
    }, function () {
        console.log('could not fetch url', banner);
    });
};
