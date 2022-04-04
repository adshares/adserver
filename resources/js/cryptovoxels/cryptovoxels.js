/*
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

var getImpressionId = function() {
    if(!lastImpressionId) {
        lastImpressionId = UrlSafeBase64Encode(getRandId(16))
    }
    return lastImpressionId;
}

var getContext = function (iid) {
    return {
        iid: iid,
        url: "https://" + parcel.id + ".adshares.cryptovoxels.com/",
        keywords: 'cryptovoxels,metaverse'
    }
};

var refreshTime = 10000;
var cid = getCid();

var lastImpressionTime = null;
var lastImpressionId = null;
var banner;
var context;

let loadedAdusers = {};

let showWatermark = function(request, banner, props) {
    let watermark = parcel.getFeatureById(feature.uuid + '-mark');
    if(!watermark) {
        watermark  = parcel.createFeature('image', {
            id:  feature.uuid + '-mark'
        });
    }

    let pos;
    let scale;
    let rotation;
    if(props.type === 'model') {
        let size = Math.sqrt(feature.scale.x * feature.scale.z) / 5
        scale = {
            x: size,
            y: size
        };
        pos = new Vector3(-(feature.scale.x - scale.x) / 2, 0, (feature.scale.z - scale.y) / 2);
        rotation = [feature.rotation.x+Math.PI/2, feature.rotation.y+Math.PI, feature.rotation.z]
    } else {
        let size = Math.sqrt(feature.scale.x * feature.scale.y) / 10
        scale = {
            x: size,
            y: size
        };
        pos = new Vector3((feature.scale.x - scale.x) / 2, (feature.scale.y - scale.y) / 2, -0.005);
        rotation = [feature.rotation.x, feature.rotation.y, feature.rotation.z];
    }
    let matrix = Matrix.RotationYawPitchRoll(feature.rotation.y, feature.rotation.x, feature.rotation.z);
    pos = Vector3.TransformCoordinates(pos, matrix);

    let url = addUrlParam(props.adserver + '/supply/why', {
        'bid': banner.id,
        'cid': banner.cid,
        'iid': request.view_id,
        'url': banner.serve_url,
        'ctx': UrlSafeBase64Encode(JSON.stringify(request.context))
    });

    watermark.set({
        position: [feature.position.x+pos.x, feature.position.y+pos.y, feature.position.z+pos.z],
        rotation: rotation,
        scale:  [scale.x, scale.y, 0],
        'stretched': true,
        'url': props.adserver + '/img/watermark.png',
        'link': url,
        'blendMode': 'Combine'
    });
}

let showVideo = function(banner) {
    let video = parcel.getFeatureById(feature.uuid + '-video');

    if(!banner) {
        if(video) {
            parcel.removeFeature(video);
        }
        return;
    }
    if(!video) {
        video  = parcel.createFeature('video', {
            id:  feature.uuid + '-video'
        });
    }
    let pos = new Vector3(0, 0, 0.001);
    let matrix = Matrix.RotationYawPitchRoll(feature.rotation.y, feature.rotation.x, feature.rotation.z);
    pos = Vector3.TransformCoordinates(pos, matrix);

    video.set({
        position: [feature.position.x+pos.x, feature.position.y+pos.y, feature.position.z+pos.z],
        rotation: [feature.rotation.x, feature.rotation.y, feature.rotation.z],
        'scale':  [feature.scale.x, feature.scale.y, feature.scale.z],
        'stretched': true,
        'url': banner.serve_url,
        'blendMode': 'Combine'
    });
    video.play();
}

let displayBanner = function (props, banner) {
    if(banner.type === 'video') {
        feature.set({
            'url': 'https://upload.wikimedia.org/wikipedia/commons/c/ce/Transparent.gif', //props.adserver + '/img/empty.gif',
            'link': banner.click_url,
            'blendMode': 'Screen'
        });
        showVideo(banner);
    } else {
        showVideo(null);
        feature.set({
            'url': banner.serve_url,
            'link': banner.click_url,
            'blendMode': 'Combine'
        });

    }
}

let displayModel = function (props, banner) {
        feature.set({
            'url': banner.serve_url,
            'link': banner.click_url
        });
}

let displayAd = function (props, banner) {
    if(props.type === 'model') {
        displayModel(props, banner)
    } else {
        displayBanner(props, banner)
    }
};

let renderText = function(text) {
    console.log(text)
}

let renderError = function(error) {
    console.error(error)
}

let getSceneId = function(land) {
    return 'scene-' + land.id
}

let getSceneTags = function(land, extraTags) {
    return extraTags.join(",");
}

let find = function(player, props) {
    const userAccount = player.wallet;
    const land = {id: parcel.id, owner: parcel.owner}

    if(!lastImpressionId) {
        fetch((props.adserver + '/supply/register?iid=' + getImpressionId()) + '&stid=' + userAccount).then()
    }

    let request = {
        "pay_to": props.payout_network + ':' + props.payout_address,
        "view_id": getImpressionId(),
        "zone_name": props.zone_name,
        "width": feature.scale.x,
        "height": feature.scale.y,
        "depth": feature.scale.z,
        "min_dpi": 10,
        "exclude": JSON.parse(props.exclude),
        "type": props.type === 'model' ? ['model'] : ["image", "video"],
        "mime_type": props.type === 'model' ? ['model/voxel'] : ["image/jpeg",  "image/png",  "image/gif", "video/mp4"],
        "context": {
            "site": {
                "url": "https://" + getSceneId(land) + ".cryptovoxels.com/",
                "keywords": getSceneTags(land, props.keywords.split(",")),
                "metamask": 1
            },
            "user": {
                "account": userAccount
            }
        },
        "medium": "metaverse",
        "vendor": "cryptovoxels",
        "version": "{{ VERSION }}",
    };

    let response = {};

    try {
        let callUrl = props.adserver + "/supply/anon?stid=" + userAccount
        let callResponse = fetch(callUrl, {
            headers: { "Content-Type": "application/json" },
            method: "POST",
            body: JSON.stringify(request),
        }).then(function(callResponse) {
            callResponse.json().then(function(response) {
                console.log(request, response)

                if (response.banners) {
                    let banner = response.banners[0];
                    if(banner) {
                        banner.cid = getCid();
                        let viewContext = {
                            page: {
                                iid: request.view_id,
                                url: request.context.site.url,
                                keywords: request.context.site.keywords,
                                metamask: request.context.site.metamask
                            },
                            user: {
                                account: request.context.user.account
                            }
                        }
                        banner.click_url = addUrlParam(banner.click_url,
                            {
                                'cid': banner.cid,
                                'ctx': UrlSafeBase64Encode(JSON.stringify(viewContext)),
                                'iid': request.view_id,
                                'stid': userAccount
                            }
                        );
                        banner.view_url = addUrlParam(banner.view_url,
                            {
                                'cid': banner.cid,
                                'ctx': UrlSafeBase64Encode(JSON.stringify(viewContext)),
                                'iid': request.view_id,
                                'json': 1,
                                'stid': userAccount
                            });
                        displayAd(props, banner)
                        showWatermark(request, banner, props);

                        try {
                            fetch(banner.view_url).then(function (response) {
                                response.json().then(function (object) {
                                    if (object.aduser_url && !loadedAdusers[object.aduser_url]) {
                                        fetch(addUrlParam(object.aduser_url, 'stid', userAccount));
                                        loadedAdusers[object.aduser_url] = true
                                    }
                                });
                            });

                        } catch(e)
                        {
                            console.log("view log failed", e);
                        }
                    } else {
                        renderText("Banner not found\n\nImpression ID: " + request.view_id + "\n\nconfig: " + JSON.stringify(props, null, "\t"))
                    }
                }
                if (!response.success) {
                    let errors = [];
                    if (response.errors) {
                        let k;
                        let v;
                        for (k in response.errors) {
                            //errors.push(k)
                            v = response.errors[k]
                            if (typeof v !== 'object') {
                                v = Array(v)
                            }
                            v.forEach(function(text) {
                                errors.push(text)
                            })
                            errors.push("")
                        }
                    }
                    errors.push("\nconfig: " + JSON.stringify(props, null, "\t"))
                    renderError(errors)
                }
            });
        })
    } catch (e) {
        console.log("failed to reach URL", e)
    }
}

let props = {
    payout_network: "ads",
    payout_address: "",
    keywords: "cryptovoxels,metaverse",
    zone_name: "default",
    adserver: serverOrigin,
    exclude: "{\"quality\": [\"low\"], \"category\": [\"adult\"]}"
}

if(typeof config === 'object') {
    for(let key in config) {
        if(props.hasOwnProperty(key)) {
            props[key] = config[key];
        }
    }
}

let fn = function(e) {
    if (lastImpressionTime && (new Date() * 1) - lastImpressionTime < refreshTime) {
        console.log('waiting for refresh ' + (refreshTime - (new Date() * 1) + lastImpressionTime));
    } else {
        lastImpressionTime = (new Date() * 1);
        lastImpressionId = getImpressionId();

        feature.set({
            'lastImpTime': lastImpressionTime,
            'lastImpId': lastImpressionId
        });
        if(feature.type === 'megavox') {
            props.type = 'model'
        } else if(feature.type === 'image') {
            props.type = 'image'
        } else {
            renderError(["Invalid feature type: use image or megavox"])
            return;
        }
        find(e.player, props);
    }
}

parcel.on('playerenter', fn);
parcel.on('playernearby', fn);

fn({
    player: parcel.getPlayers()[0]
});