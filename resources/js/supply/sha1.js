

let running = [];
let sha1 = function (data, callback) {
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

let hex = function(buffer) {
    let hexCodes = [];
    let view = new DataView(buffer);
    for (let i = 0; i < view.byteLength; i += 4) {
        let value = view.getUint32(i);
        let stringValue = value.toString(16);
        let padding = '00000000';
        let paddedValue = (padding + stringValue).slice(-padding.length);
        hexCodes.push(paddedValue);
    }
    return hexCodes.join("");
}

let sha1_async = function (data, callback) {
    if (window.Blob && data instanceof Blob) // blob
    {
        let reader = new FileReader();

        reader.onload = function (e) {
            sha1(reader.result, callback);
        };

        reader.readAsArrayBuffer(data);

    } else {
        let buffer = new Uint8Array(data.bytes.length);
        for(i=0,len=data.bytes.length;i<len;i++) {
            buffer[i] = data.bytes.charCodeAt(i);
        }
        sha1(buffer, function (hash) {
            callback(hash);
        });
    }
};
