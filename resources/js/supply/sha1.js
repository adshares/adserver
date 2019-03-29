

var running = [];
var sha1 = function (data, callback) {
    if (!callback.$delayed) {
        running.push([data, callback]);
        if (running.length > 1) {
            callback.$delayed = true;
            return;
        }
    }

    try {
        crypto.subtle.digest('SHA-1', data).then(function(hash) {
            callback(hex(hash));
        }).catch(function(error) {
            callback('NO_SUPPORT');
        });
    } catch(e) {
        callback('NO_SUPPORT');
    }

    running.shift();
    if (running.length >= 1) {
        setTimeout(sha1.bind(this, running[0][0], running[0][1]), 0);
    }
};

var hex = function(buffer) {
    var hexCodes = [];
    var view = new DataView(buffer);
    for (var i = 0; i < view.byteLength; i += 4) {
        var value = view.getUint32(i);
        var stringValue = value.toString(16);
        var padding = '00000000';
        var paddedValue = (padding + stringValue).slice(-padding.length);
        hexCodes.push(paddedValue);
    }
    return hexCodes.join("");
}

var sha1_async = function (data, callback) {
    if (window.Blob && data instanceof Blob) // blob
    {
        var reader = new FileReader();

        reader.onload = function (e) {
            sha1(reader.result, callback);
        };

        reader.readAsArrayBuffer(data);

    } else {
        var buffer = new Uint8Array(data.bytes.length);
        for(i=0,len=data.bytes.length;i<len;i++) {
            buffer[i] = data.bytes.charCodeAt(i);
        }
        sha1(buffer, function (hash) {
            callback(hash);
        });
    }
};
