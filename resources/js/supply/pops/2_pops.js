var addPop;
var checkPopLimits;

(function() {

    var orgWindowOpen = window.open;

    var popQueue = [];
    var currentPop = null;
    var preparePop, executePop, executeProxy, executeProxyTimer;
    var executeCount = 0;

    var saveLog = function (popLog) {
        let minTimestamp = (new Date()).getTime() / 1000 - 48 * 3600;
        let valid = [];
        for (let i = 0, n = popLog.length; i < n; i++) {
            let log = popLog[i];
            if (log.time >= minTimestamp) {
                valid.push(log);
            }
        }
        store.set('dwmth-pops' + selectorClass, valid);
    };

    var loadLog = function () {
        return store.get('dwmth-pops' + selectorClass) || [];
    }

    executeProxy = function (e) {
        let target = e.target;
        while(target && target != document.body) {
            if(target.tagName == 'A') {
                if (!executeProxyTimer) {
                    executeProxyTimer = setTimeout(executePop, 1);
                }
            }
            target = target.parentElement;
        }
    };

    /**
     *
     * @param type
     * @param url
     * @param count - max popups per interval
     * @param interval
     * @param burst - popup limit on single page load
     * @param callback - triggered after popup is displayed
     */
    addPop = function (type, url, count, interval, burst, callback) {
        if (currentPop) {
            popQueue.push(arguments);
            return;
        }

        preparePop.apply(this, arguments)
    };


    checkPopLimits = function(count, interval)
    {
        if (count <= 0) {
            return false;
        }

        if (interval > 0) {
            let popLog = loadLog();
            let inTimespan = 0;
            let minTimestamp = (new Date()).getTime()/1000 - interval * 3600;
            for (let i = 0, n = popLog.length; i < n; i++) {
                let log = popLog[i];
                if (log.time >= minTimestamp) {
                    inTimespan++;
                }
            }
            if (inTimespan >= count) {
                return false;
            }
        }

        return true;
    };

    preparePop = function (type, url, count, interval, burst, callback) {
        if(!checkPopLimits(count, interval)) {
            return false;
        }


        if (burst && burst <= executeCount) {
            return false;
        }

        window.open = function() {return window;};
        currentPop = arguments;
        addListener(document, 'click', executeProxy, true);
        addListener(document, 'click', executeProxy, false);

        let links = document.getElementsByTagName('a');
        for (let i = 0, n = links.length; i < n; i++) {
            let link = links[i];
            let href = link.getAttribute('href');
            let target = link.getAttribute('target');
            if (href) {
                if (type === 'pop-up') {
                    link.setAttribute('href', url);
                    link.setAttribute('data-orghref', href);
                    if (target) {
                        link.setAttribute('data-orgtarget', target);
                    }
                }
                link.setAttribute('target', '_blank');
            }
        }
        return true;
    };

    executePop = function () {
        clearTimeout(executeProxyTimer);
        executeProxyTimer = null;
        if (!currentPop) {
            return;
        }

        removeListener(document, 'click', executeProxy, true);
        removeListener(document, 'click', executeProxy, false);
        window.open = orgWindowOpen;

        executeCount++;
        let popLog = loadLog();
        popLog.push({
            // type: currentPop[0],
            time: Math.round((new Date()).getTime()/1000)
        });
        saveLog(popLog);
        if(currentPop[5]) {
            try {
                currentPop[5]();
            } catch (e) {

            }
        }

        if (currentPop[0] === 'pop-up') {
            let links = document.getElementsByTagName('a');
            for (let i = 0, n = links.length; i < n; i++) {
                let link = links[i];
                let orgtarget = link.getAttribute('data-orgtarget');
                let orghref = link.getAttribute('data-orghref');
                if (orghref) {
                    link.removeAttribute('data-orghref');
                    link.setAttribute('href', orghref);
                }
                if (orgtarget) {
                    link.removeAttribute('data-orgtarget');
                    link.setAttribute('target', orgtarget);
                } else {
                    link.removeAttribute('target');
                }
            }
        } else if (currentPop[0] === 'pop-under') {
            let url = currentPop[1];
            setTimeout(function(){window.location.href = url;}, 2000);
        }

        currentPop = null;
        while (popQueue.length > 0) {
            if (preparePop.apply(this, popQueue.shift())) {
                break;
            }
        }

    };
})();