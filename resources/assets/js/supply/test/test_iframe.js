var log = [];
function console_log(a, b, c, d)
{
	var line = [];
	if(a) line.push(a);
	if(b) line.push(b);
	if(c) line.push(c);
	if(d) line.push(d);
	log.push(line.join(', '));
	document.getElementById('log').innerHTML = log.join('\n');
}
console_log('ext script YAY!');

window.SECRET = "2236";

top.SECRET = "NOSECRET";
top.document.getElementById('secret').innerHTML = 'secret is ' + SECRET;