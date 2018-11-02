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

var SHA1 = function () {};

var sha1Functions = {
    reset: function () {
        this._hash = [0x67452301, 0xefcdab89, 0x98badcfe, 0x10325476, 0xc3d2e1f0];
        this.W = new Array(80);
    },
    digest: function (str, callback) {
        this.reset();

        var words = new Array(16);

        var length = str.length;
        var fullLength = (length >>> 6) << 6;
        var nBitsTotal = length * 8;

        var self = this;

        var curPos = 0;

        var fn_final = function () {
            for (var i = 0; i < 16; i++) {
                words[i] = 0;
            }
            var remainder = length - fullLength;
            for (var i = fullLength; i < length; i++) {
                words[(i >>> 2) % 16] |= str.charCodeAt(i) << (24 - (i % 4) * 8);
            }
            words[(length >>> 2) % 16] |= 0x80 << (24 - (length % 4) * 8);

            var x = Math.floor(nBitsTotal / 0x100000000);
            if ((length >>> 2) % 16 < 14) {
                words[14] = x;
                words[15] = nBitsTotal;
                self.doBlock(words, 0);
            } else {
                self.doBlock(words, 0);
                for (var i = 0; i < 14; i++) {
                    words[i] = 0;
                }
                words[14] = x;
                words[15] = nBitsTotal;
                self.doBlock(words, 0);
            }
            callback(self.toHex(self._hash));
        };
        var fn = function () {
            /* Transform as many times as possible */
            var start = new Date();

            var i = curPos, j;
            for (; i < fullLength; i += 64) {
                for (j = 0; j < 16; j++) {
                    words[j] = str.charCodeAt(curPos++) << 24 | str.charCodeAt(curPos++) << 16
                        | str.charCodeAt(curPos++) << 8 | str.charCodeAt(curPos++);
                }
                self.doBlock(words, 0);
                if (curPos % 16000 == 0 && (new Date() - start) > 50) {
                    setTimeout(fn, 0); // do not freeze browser
                    return;
                }
            }
            fn_final();
        };

        fn();
    },

    toHex: function (words) {
        var sigBytes = words.length * 4;

        // Convert
        var hexChars = [];
        for (var i = 0; i < sigBytes; i++) {
            var bite = (words[i >>> 2] >>> (24 - (i % 4) * 8)) & 0xff;
            hexChars.push((bite >>> 4).toString(16));
            hexChars.push((bite & 0x0f).toString(16));
        }

        return hexChars.join('');
    },

    doBlock: function (M) {
        // Shortcut
        var H = this._hash;
        var W = this.W;

        // Working variables
        var a = H[0];
        var b = H[1];
        var c = H[2];
        var d = H[3];
        var e = H[4];

        // Computation
        var i = 0;
        var t;
        var n;

        while (i < 16) {
            W[i] = M[i] | 0;

            t = ((a << 5) | (a >>> 27)) + e + W[i] + ((b & c) | (~b & d)) + 0x5a827999;

            e = d;
            d = c;
            c = (b << 30) | (b >>> 2);
            b = a;
            a = t;

            i++;
        }

        while (i < 20) {
            n = W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16];
            W[i] = (n << 1) | (n >>> 31);

            t = ((a << 5) | (a >>> 27)) + e + W[i] + ((b & c) | (~b & d)) + 0x5a827999;

            e = d;
            d = c;
            c = (b << 30) | (b >>> 2);
            b = a;
            a = t;

            i++;
        }

        while (i < 40) {
            n = W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16];
            W[i] = (n << 1) | (n >>> 31);

            t = ((a << 5) | (a >>> 27)) + e + W[i] + (b ^ c ^ d) + 0x6ed9eba1;

            e = d;
            d = c;
            c = (b << 30) | (b >>> 2);
            b = a;
            a = t;

            i++;
        }

        while (i < 60) {
            n = W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16];
            W[i] = (n << 1) | (n >>> 31);

            t = ((a << 5) | (a >>> 27)) + e + W[i] + ((b & c) | (b & d) | (c & d)) - 0x70e44324;

            e = d;
            d = c;
            c = (b << 30) | (b >>> 2);
            b = a;
            a = t;

            i++;
        }

        while (i < 80) {
            n = W[i - 3] ^ W[i - 8] ^ W[i - 14] ^ W[i - 16];
            W[i] = (n << 1) | (n >>> 31);

            t = ((a << 5) | (a >>> 27)) + e + W[i] + (b ^ c ^ d) - 0x359d3e2a;

            e = d;
            d = c;
            c = (b << 30) | (b >>> 2);
            b = a;
            a = t;

            i++;
        }

        // Intermediate hash value
        H[0] = (H[0] + a) | 0;
        H[1] = (H[1] + b) | 0;
        H[2] = (H[2] + c) | 0;
        H[3] = (H[3] + d) | 0;
        H[4] = (H[4] + e) | 0;
    }
};

for (var func in sha1Functions) {
    SHA1.prototype[func] = sha1Functions[func];
}

var running = [];
var sha1 = function (data, callback) {
    if (!callback.$delayed) {
        running.push([data, callback]);
        if (running.length > 1) {
            callback.$delayed = true;
            return;
        }
    }
    new SHA1().digest(data, function (hash) {
        callback(hash);
        running.shift();
        if (running.length >= 1) {
            setTimeout(sha1.bind(this, running[0][0], running[0][1]), 0);
        }
    });
};

var sha1_async = function (data, callback) {
    if (window.Blob && data instanceof Blob) // blob
    {
        var reader = new FileReader();

        reader.onload = function (e) {
            sha1(reader.result, function (hash) {
                callback(hash);
            });
        };

        if (reader.readAsBinaryString)
            reader.readAsBinaryString(data);
        else
            reader.readAsText(data);
    } else {
        var start = new Date();
        sha1(data.bytes, function (hash) {
            callback(hash);
        });
    }
};
