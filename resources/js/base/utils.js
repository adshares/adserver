var UrlSafeBase64Encode = function (data) {
    return btoa(window.unescape(window.encodeURIComponent(data))).replace(/=|\+|\//g, function (x) {
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

var $pick = function (value, ifnull) {
    if (value === null || value === undefined)
        return ifnull;
    return value;
}

function validURL(str) {
    var pattern = new RegExp('^(https?:\\/\\/)?'+ // protocol
        '((([a-z\\d]([a-z\\d-]*[a-z\\d])*)\\.)+[a-z]{2,}|'+ // domain name
        '((\\d{1,3}\\.){3}\\d{1,3}))'+ // OR ip (v4) address
        '(\\:\\d+)?(\\/[-a-z\\d%_.~+]*)*'+ // port and path
        '(\\?[;&a-z\\d%_.~+=-]*)?'+ // query string
        '(\\#[-a-z\\d_]*)?$','i'); // fragment locator
    return !!pattern.test(str);
}