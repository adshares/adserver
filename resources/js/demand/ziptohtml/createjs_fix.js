(function () {
    let DwmthPlugin = function () {
    };
    let s = DwmthPlugin;

    s.getPreloadHandlers = function () {
        return {
            callback: s.preloadHandler, // Proxy the method to maintain scope
            types: ["binary","image", "javascript", "json", "jsonp", "sound", "svg", "text", "xml"],
            extensions: ["jpg", "jpeg", "png", "gif"]
        }
    };

    s.preloadHandler = function (loadItem, queue) {
        if(loadItem.type === "text") {
            let match = loadItem.src.match(/data:([a-z]+)\/[a-z]+/i);
            if(match) {
                loadItem.type = match[1];
            }
        }

        let src = loadItem.src;
        let org = document.querySelector('[data-asset-org="' + src + '"]');
        if(!org) {
            org = document.querySelector('[data-asset-org$="' + src + '"]');
        }

        if(org) {
            loadItem.src = org.getAttribute('data-src') || org.getAttribute('src');
        }

        return true;
    };
    createjs.DwmthPlugin = DwmthPlugin;

    let fn = createjs.LoadQueue.prototype.init;
    createjs.LoadQueue.prototype.init = function() {
        fn.apply(this, arguments);
        this.installPlugin(DwmthPlugin);
    }
}());