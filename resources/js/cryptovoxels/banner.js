var serverOrigin = '{{ ORIGIN }}';

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

var UrlSafeBase64Encode = function (data) {
    return btoa(unescape(encodeURIComponent(data))).replace(/=|\+|\//g, function (x) {
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

var addUrlParam = function (url, names, value) {
    if (typeof names != 'object') {
        var tmp = names;
        names = {};
        names[tmp] = value;
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

var getImpressionId = function () {
    return UrlSafeBase64Encode(getRandId(16));
};

var getContext = function (iid) {
    return {
        iid: iid,
        url: "https://" + parcel.id + ".adshares.cryptovoxels.com/",
        keywords: 'cryptovoxels,metaverse'
    }
};

var refreshTime = 10000;
var cid = getCid();
var viewed = false;


var timer = null;

var lastImpressionTime = null;
var lastImpressionId = null;
var adViewed = false;
var banner;
var context;
var contextParam;

let showWatermark = function() {
    let watermark = parcel.getFeatureById(feature.uuid + '-mark');
    if(!watermark) {
        watermark  = parcel.createFeature('image', {
            id:  feature.uuid + '-mark'
        });
    }

    let size = Math.sqrt(feature.scale.x * feature.scale.y) / 10
    let scale = {
        x: size,
        y: size
    };

    let pos = new Vector3((feature.scale.x - scale.x) / 2, (feature.scale.y - scale.y) / 2, -0.01);
    let matrix = Matrix.RotationYawPitchRoll(feature.rotation.y, feature.rotation.x, feature.rotation.z);
    pos = Vector3.TransformCoordinates(pos, matrix);

    let url = addUrlParam(serverOrigin + '/supply/why', {
        'bid': banner.id,
        'cid': banner.cid,
        'ctx': contextParam,
        'iid': lastImpressionId,
        'url': banner.serve_url,
    });

    watermark.set({
        position: [feature.position.x+pos.x, feature.position.y+pos.y, feature.position.z+pos.z],
        rotation: [feature.rotation.x, feature.rotation.y, feature.rotation.z],
        scale:  [scale.x, scale.y, 0],
        'stretched': true,
        'url': 'https://app.adaround.net/img/watermark.png',
        'link': url,
        'blendMode': 'Combine'
    });
}

let displayAd = function (banner) {
    feature.set({
        'url': banner.serve_url,
        'link': banner.click_url
    });
    showWatermark();
};

let loadAd = function (e) {
    if (lastImpressionTime && (new Date() * 1) - lastImpressionTime < refreshTime) {
        console.log('waiting for refresh ' + (refreshTime - (new Date() * 1) + lastImpressionTime));
    } else {
        lastImpressionTime = (new Date() * 1);
        lastImpressionId = getImpressionId();

        feature.set({
            'lastImpTime': lastImpressionTime,
            'lastImpId': lastImpressionId
        });
        fetch(serverOrigin + '/supply/register?iid=' + lastImpressionId);

        var params = [];
        context = getContext(lastImpressionId);
        params.push(context);
        params.push({
            zone: zoneId,
            options: "banner_type=image"
        });
        var data = encodeZones(params);
        var url = serverOrigin + '/supply/find?' + data;

        fetch(url).then(function (response) {
            response.json().then(function (object) {
                banner = object[0];

                banner.cid = getCid();

                banner.context = {page: params[0], zone: params[1]};
                contextParam = encodeZones([banner.context.page]);
                banner.click_url = addUrlParam(banner.click_url,
                    {
                        'cid': banner.cid,
                        'ctx': contextParam,
                        'iid': lastImpressionId
                    });
                banner.view_url = addUrlParam(banner.view_url,
                    {
                        'cid': banner.cid,
                        'ctx': contextParam,
                        'iid': lastImpressionId,
                        'json': 1
                    });
                displayAd(banner);

                fetch(banner.view_url).then(function (response) {
                    response.json().then(function (object) {
                        if(object.aduser_url) {
                            fetch(object.aduser_url);
                        }
                    });
                });
            });
        });


        adViewed = false;
    }
};

parcel.on('playerenter', loadAd);
parcel.on('playernearby', loadAd);


