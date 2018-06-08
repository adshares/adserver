var serverOrigin = '{{ ORIGIN }}';

var addUrlParam = function (url, names, value) {
    
    if(typeof names != 'object') {
        var x = {};
        x[names] = value;
        names = x;
    }
    for(var name in names) {
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
}

var UrlSafeBase64Encode = function(data) {
	return btoa(data).replace(/=|\+|\//g, function (x) {
        return x == '+' ? '-' : (x == '/' ? '_' : '')
    });
}


var getBrowserContext = function() {	
	return {
        frame : (parent == top ? 0 : 1),
        width : window.screen.width,
        height : window.screen.height,
        url : (parent !== window) ? document.referrer : document.location.href
    }
}

var logContext = function(log_id) {
	
	var url = serverOrigin + '/context/' + log_id;
	
	url = addUrlParam(url, 'k', UrlSafeBase64Encode(JSON.stringify(getBrowserContext())));
	
	var img = new Image();
	img.src = url;
	
	document.body.appendChild(img);
}

var getLocation = function(href) {
    var location = document.createElement("a");
    location.href = href;
    // IE doesn't populate all link properties when setting .href with a relative URL,
    // however .href will return an absolute URL which then can be used on itself
    // to populate these additional fields.
    if (location.host == "") {
      location.href = location.href;
    }
    return location;
};

domReady(function() {
	var onlyStrings = false;
	var adsharesLink = document.getElementById('adsharesLink');
	adsharesLink.onclick = function(e) {
		var msg = {
				adsharesClick : 1
			};
		parent.postMessage(onlyStrings ? JSON.stringify(msg) : msg, '*');
		e.preventDefault();
	};
	try {
		window.postMessage({
			toString : function() {
				onlyStrings = true;
			}
		}, "*");
	} catch (e) {
	}
	var fn = function(event) {
		var msg;
		
		if (typeof event.data == 'string') {
			msg = JSON.parse(event.data);
		} else {
			msg = event.data;
		}
		if (msg.adsharesLoad) {
			var data = msg.data;

			if (data.click_url) {
				adsharesLink.href = data.click_url;
			}
			
			if(data.lid) {
				logContext(data.lid);
			}
		}
	};
	window.addEventListener ? addEventListener('message', fn) : attachEvent(
			'onmessage', fn);
	msg = {
		adsharesLoad : 1
	};
	parent.postMessage(onlyStrings ? JSON.stringify(msg) : msg , '*');
});