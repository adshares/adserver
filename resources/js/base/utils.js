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


var $isset = function (value) {
    return value !== null && value !== undefined;
};

var $pick = function (value, ifnull) {
    return $isset(value) ? value : ifnull;
};


function ancestor(HTMLobj){
    while(HTMLobj.parentElement){HTMLobj=HTMLobj.parentElement}
    return HTMLobj;
}
function inTheDOM(obj){
    return obj.isConnected !== undefined ? obj.isConnected : (ancestor(obj)===document.documentElement);
}