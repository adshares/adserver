(function(){
    var styles = document.getElementsByTagName('style');
    for(var i=0;i<styles.length;i++) {
        var code = styles[i].innerHTML;

        code = code.replace(/\/\*\{asset-src:(.*?)\}\*\//g, function(match, src) {
            var uri = ''; var org = document.querySelector('[data-asset-org="' + src + '"]');
            if(org) {
                uri = org.getAttribute('src') || org.getAttribute('data-src');
            }
            return 'url(' + uri + ')';
        });

        var s = document.createElement('style');
        s.type = 'text/css';
        try {
            s.appendChild(document.createTextNode(code));
        } catch (e) {
            s.text = code;
        }
        styles[i].parentElement.replaceChild(s, styles[i]);

    }

    var refs = document.querySelectorAll('[data-asset-src]');
    for(var i=0;i<refs.length;i++) {
        var org = document.querySelector('[data-asset-org="' + refs[i].getAttribute('data-asset-src') + '"]');
        if(org) {
            refs[i].setAttribute('src', org.getAttribute('src'));
        }
    }

    var refs = document.querySelectorAll('img[srcset], source[srcset]');
    for(var i=0;i<refs.length;i++) {
        var any_found = false;
        var newsrcset = refs[i].getAttribute('srcset').replace(/asset-src:([^,\s]+)/g, function(match, href) {
            any_found = true;
            var uri = 'null'; var org = document.querySelector('[data-asset-org="' + href + '"]');
            if(org) {
                uri = org.getAttribute('src') || org.getAttribute('data-src');
            }
            return uri;
        })
        if(any_found) {
            refs[i].setAttribute('srcset', newsrcset);
        }
    }
})();