/*
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

domReady(function() {
	var log = [];
	window.console_log = function(a, b, c, d, e, f)
	 {
		var line = [];
		if(a) line.push(a);
		if(b) line.push(b);
		if(c) line.push(c);
		if(d) line.push(d);
		if(e) line.push(e);
		if(f) line.push(f);
		log.push(line.join(', '));
		document.getElementById('log').innerHTML = log.join('<br>\n');
	 }

	fetchURL('http://' +  (window.location.host == 'adshares.priv' ? 'adshares2.priv' : 'adshares2.zel.pl') + '/hashTest/iframe').then(function(data) {		
		createIframeFromData(data, function(iframe) {
			iframe.height = 400;
			document.body.appendChild(iframe);
			
			setTimeout(function(){
    			var start = new Date();
    			sha256_async(data, function(hash) {
    				console_log(hash == "7cca008cc93b0d76fdb50d6b298a9592796974f28121b03c6505b84e0f3c1750" ? "OK" : "ERR", hash, 'size=' + (data.size ? data.size : data.bytes.length),'time=' + (1*(new Date() - start) / 1000));
    				
    				start = new Date();
    				sha1_async(data, function(hash) {
    					console_log(hash == "b73ec7bebb22d1e2a4f7d6580918716204d7c18e" ? "OK" : "ERR", hash, 'size=' + (data.size ? data.size : data.bytes.length),'time=' + (1*(new Date() - start) / 1000));
    				});
    				
    				fetchURL('http://' + (window.location.host == 'adshares.priv' ? 'adshares2.priv' : 'adshares2.zel.pl') + '/serve/7', {binary: true}).then(function(data) {
    					createImageFromData(data, function(image) {
    						image.width = 300;
    						image.height = 400;
    						document.body.appendChild(image);
    						
    						setTimeout(function(){
        						start = new Date();
        						sha256_async(data, function(hash) {
        							console_log(hash == "0fdbf1685a635145eabfb32318a54f6d7c15c04448cf1303ffc7466d785000ac" ? "OK" : "ERR", hash, 'size=' + (data.size ? data.size : data.bytes.length),'time=' + (1*(new Date() - start) / 1000));
        							
        							start = new Date();
        							sha1_async(data, function(hash) {
        								console_log(hash == "fc581351eb00e6248e87b168abc9fc0fbd5aa4ab" ? "OK" : "ERR", hash, 'size=' + (data.size ? data.size : data.bytes.length),'time=' + (1*(new Date() - start) / 1000));
        							});
        						});
    						}, 1);
    					});
    					
    				});
    			});
			}, 1);
		});		
	});
});
