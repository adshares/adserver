(function(){
    let styles = document.getElementsByTagName('style');
    for(let i=0;i<styles.length;i++) {
        let code = styles[i].innerHTML;

        code = code.replace(/\/\*\{asset-src:(.*?)\}\*\//g, function(match, src) {
            let uri = ''; let org = document.querySelector('[data-asset-org="' + src + '"]');
            if(org) {
                uri = org.getAttribute('src') || org.getAttribute('data-src');
            }
            return 'url(' + uri + ')';
        });

        let s = document.createElement('style');
        s.type = 'text/css';
        try {
            s.appendChild(document.createTextNode(code));
        } catch (e) {
            s.text = code;
        }
        styles[i].parentElement.replaceChild(s, styles[i]);

    }

    let refs = document.querySelectorAll('[data-asset-src]');
    for(let i=0;i<refs.length;i++) {
        let org = document.querySelector('[data-asset-org="' + refs[i].getAttribute('data-asset-src') + '"]');
        if(org) {
            refs[i].setAttribute('src', org.getAttribute('src'));
        }
    }

    let refs = document.querySelectorAll('img[srcset], source[srcset]');
    for(let i=0;i<refs.length;i++) {
        let any_found = false;
        let newsrcset = refs[i].getAttribute('srcset').replace(/asset-src:([^,\s]+)/g, function(match, href) {
            any_found = true;
            let uri = 'null'; let org = document.querySelector('[data-asset-org="' + href + '"]');
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