/**
 *
 * \author Magnus Espeland <magg@tpn.no>
 *
 * \version $Id: full_exception.js 934 2008-01-17 15:30:54Z mortena $
 *
 * Display exceptions onscreen in the application
 *
 */

var mqf_exception_msg = '';

function mqf_full_exception(ex) 
{
	var e = ex;
	var trace = e.trace;

	if (e.message) {
	    var error_msg = e.message;
	} else {
	    var error_msg = '*no message*';
	}


    error_msg += '<br /><br />' + e.file + ' at line ' + e.line + '\n';
    var error_ip = e.client_ipaddress + ' -> ' + e.server_ipaddress + ':' + e.server_port;
    
	trace.each(mqf_exception_itr_trace);


    mqf_exception_show_popup(error_msg, mqf_exception_msg, error_ip);

}

function mqf_exception_show_popup(short_msg, long_msg, error_ip)
{

    if(!$('mqfExceptionPopup')) mqf_exception_create_div();

    var now = new Date();

	var popup_content = '';
	
	popup_content += '<div id="mw_log_border" style="border: 8px solid black; visibility:visible;background-color: black;">';
	popup_content += '<div id="mw_log" style="border: solid #ff0000; padding: 8px; visibility:visible; background-color: black;">';
	popup_content += '<table id="mw_log_table" style="width:90%; text-align:center; "><tr width="100%"><td>';
	popup_content += '<div style="position: relative; font-size: 14pt;clear: both; color: white;">Guru Meditation @ ' + now.toString() + '</div >';
    popup_content += '<br />';
    popup_content += '<div style="color: white;"><code>' + short_msg + '('+error_ip+')</code></div>';
    popup_content += '</td></tr></table>';
    popup_content += '</div></div><br/>';
	
	popup_content += "<button class='exception_popup_button' onclick=\"$('mqfExceptionPopup').style.visibility = 'hidden';$('mqfExceptionFixIframe').style.visibility = 'hidden'; $('mw_log_border').style.visibility = 'hidden'; $('mw_log').style.visibility = 'hidden'; \">Close</button>";

    popup_content = popup_content + '<pre class="exception_popup_pre">'+ long_msg +'</pre>';

	$('mqfExceptionPopup').innerHTML = popup_content;
	
	$('mqfExceptionPopup').style.visibility     = 'visible';
	$('mqfExceptionFixIframe').style.visibility = 'visible';

	$('mqfExceptionFixIframe').style.width	= $('mqfExceptionPopup').offsetWidth + "px";
	$('mqfExceptionFixIframe').style.height = $('mqfExceptionPopup').offsetHeight + "px";
	$('mqfExceptionFixIframe').style.left 	= $('mqfExceptionPopup').offsetLeft + "px";
	$('mqfExceptionFixIframe').style.top 	= $('mqfExceptionPopup').offsetTop + "px";

    mqf_full_exception_blinking_border();
    setInterval(mqf_full_exception_blinking_border, 1000);

}

function mqf_exception_itr_trace (o) {
	mqf_exception_msg += '\n\t';
	if(o.class_name) mqf_exception_msg += o.class_name;
	if(o.type) mqf_exception_msg += o.type;

	mqf_exception_msg += o.function_name;

	if(o.file) mqf_exception_msg +=' (' + o.file + ' : ' + o.line + ')';

	mqf_exception_msg += '\n\targs:\n';

    if(o.args) {
        if(o.args[0]) {
	    	o.args.each(mqf_exception_itr_args);
        }
	}
	
}

function mqf_exception_itr_args (a) {
    try {
    	if(typeof(a) == 'object' && a[0]) a.each(mqf_exception_itr_args);
	    else if(typeof(a) == 'object') {
            mqf_exception_msg = mqf_exception_msg + '\t\tObject: ' + a.__className + '\n';
	    } else mqf_exception_msg = mqf_exception_msg + '\t\t' + a + '\n';
	} catch (e) {
    	mqf_exception_msg = mqf_exception_msg + '\t\tObject: ' + a + '\n';
	}
}


function mqf_exception_create_div() {

	var exceptionDiv = document.createElement("div");
	exceptionDiv.setAttribute("id", 'mqfExceptionPopup');
	document.body.appendChild(exceptionDiv);

	$('mqfExceptionPopup').style.position = 'absolute';
	$('mqfExceptionPopup').style.top = '50px';
	$('mqfExceptionPopup').style.left = '50px';
	$('mqfExceptionPopup').style.bottom = '50px';
	$('mqfExceptionPopup').style.right = '50px';
	$('mqfExceptionPopup').style.zIndex = '1000';
	$('mqfExceptionPopup').style.backgroundColor = 'black';
	$('mqfExceptionPopup').style.border = 'solid red';
	$('mqfExceptionPopup').style.padding = '10px';
	$('mqfExceptionPopup').style.visibility = 'hidden';
	$('mqfExceptionPopup').style.overflow = 'auto';
	$('mqfExceptionPopup').style.fontFamily = "'Fixedsys' 'Terminal' monospace";
	$('mqfExceptionPopup').style.color = 'red';

	var exceptionIframe = document.createElement("iframe");
	exceptionIframe.setAttribute("id", 'mqfExceptionFixIframe');
	document.body.appendChild(exceptionIframe);

	$('mqfExceptionFixIframe').style.position = 'absolute';
	$('mqfExceptionFixIframe').style.zIndex = '999';
	$('mqfExceptionFixIframe').style.visibility = 'hidden';

}



function mqf_full_exception_blinking_border()
{
    var id = document.getElementById('mw_log');

    if (id.style.borderColor == 'black black black black') {
        id.style.borderColor = 'red red red red';
    } else {
        id.style.borderColor = 'black black black black';           
    }
}
