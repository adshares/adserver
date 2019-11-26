var addPop;

(function() {

    var popQueue = [];
    var currentPop = null;
    var preparePop, executePop, executeProxy, executeProxyTimer;
    var executeCount = 0;

    var saveLog = function (popLog) {
        let minTimestamp = (new Date()).getTime() - 48 * 3600 * 1000;
        let valid = [];
        for (let i = 0, n = popLog.length; i < n; i++) {
            let log = popLog[i];
            if (log.time >= minTimestamp) {
                valid.push(log);
            }
        }
        store.set('dwmth-pops', valid);
    };

    var loadLog = function () {
        return store.get('dwmth-pops') || [];
    }

    executeProxy = function () {
        if (!executeProxyTimer) {
            executeProxyTimer = setTimeout(executePop, 1);
        }
    };

    /**
     *
     * @param type
     * @param url
     * @param count - max popups per timespan
     * @param timespan
     * @param burst - popup limit on single page load
     */
    addPop = function (type, url, count, timespan, burst) {
        if (currentPop) {
            popQueue.push(arguments);
            return;
        }

        preparePop.apply(this, arguments)
    };

    preparePop = function (type, url, count, timespan, burst) {
        if (count <= 0) {
            return false;
        }

        if (timespan > 0) {
            let popLog = loadLog();
            let inTimespan = 0;
            let minTimestamp = (new Date()).getTime() - timespan * 3600 * 1000;
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

        if (burst && burst <= executeCount) {
            return false;
        }

        currentPop = arguments;
        addListener(document, 'click', executeProxy, true);
        addListener(document, 'click', executeProxy, false);

        let links = document.getElementsByTagName('a');
        for (let i = 0, n = links.length; i < n; i++) {
            let link = links[i];
            let href = link.getAttribute('href');
            let target = link.getAttribute('target');
            if (href) {
                if (type === 'popup') {
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

        if (currentPop[0] === 'popup') {
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
        } else if (currentPop[0] === 'popunder') {
            window.location = currentPop[1];
        }

        executeCount++;
        let popLog = loadLog();
        popLog.push({
            // type: currentPop[0],
            time: (new Date()).getTime()
        });
        saveLog(popLog);

        currentPop = null;
        while (popQueue.length > 0) {
            if (preparePop.apply(this, popQueue.shift())) {
                break;
            }
        }

    };
})();