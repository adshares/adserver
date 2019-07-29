var insertBackfill = (function() {

// https://html.spec.whatwg.org/multipage/scripting.html
    var runScriptTypes = [
        'application/javascript',
        'application/ecmascript',
        'application/x-ecmascript',
        'application/x-javascript',
        'text/ecmascript',
        'text/javascript',
        'text/javascript1.0',
        'text/javascript1.1',
        'text/javascript1.2',
        'text/javascript1.3',
        'text/javascript1.4',
        'text/javascript1.5',
        'text/jscript',
        'text/livescript',
        'text/x-ecmascript',
        'text/x-javascript'
    ];

    var seq = function (arr, callback, index) {
        // first call, without an index
        if (typeof index === 'undefined') {
            index = 0
        }

        if(arr.length == 0) {
            callback();
            return;
        }

        arr[index](function () {
            index++
            if (index === arr.length) {
                callback()
            } else {
                seq(arr, callback, index)
            }
        })
    }

    var scriptsDone = function () {
        var DOMContentLoadedEvent = document.createEvent('Event')
        DOMContentLoadedEvent.initEvent('DOMContentLoaded', true, true)
        document.dispatchEvent(DOMContentLoadedEvent)
    }

    var insertScript = function ($script, callback) {
        var s = document.createElement('script')
        s.type = 'text/javascript'
        if ($script.src) {
            s.onload = callback
            s.onerror = callback
            s.src = $script.src
        } else {
            s.textContent = $script.innerText
        }

        $script.parentNode.replaceChild(s, $script);

        if (!$script.src) {
            callback()
        }
    }

    var runScripts = function ($scripts) {
        var runList = []
        var typeAttr

        [].forEach.call($scripts, function ($script) {
            typeAttr = $script.getAttribute('type')

            // only run script tags without the type attribute
            // or with a javascript mime attribute value
            if (!typeAttr || runScriptTypes.indexOf(typeAttr) !== -1) {
                runList.push(function (callback) {
                    insertScript($script, callback)
                })
            }
        })

        seq(runList, scriptsDone)
    }

    return function(element, htmlContent) {
        if(!htmlContent) return;
        var frag = document.createDocumentFragment();

        var div = document.createElement('div');
        div.innerHTML = htmlContent.trim();

        if(div.childNodes.length == 1 && div.firstChild.nodeType === 8) {
            div.innerHTML = div.firstChild.textContent;
        }

        var $scripts = div.querySelectorAll('script')

        while(div.firstChild) {
            frag.appendChild(div.firstChild);
        }

        element.parentNode.replaceChild(frag, element);
        runScripts($scripts)
    }
})();