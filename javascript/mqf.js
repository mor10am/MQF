
var mqf_hide_when_loading = false;
var __MQF__ = Class.create();

__MQF__.prototype = {
    defaultLoadingText: 'Loading...',
    defaultSavingText: 'Saving...',

	initialize: function()
	{
	},

    getContentAsString: function(node)
    {
        return node.xml != undefined ?
            this._getContentAsStringIE(node) :
            this._getContentAsStringMozilla(node);
    },

    _getContentAsStringIE: function(node)
    {
        var contentStr = "";
        var nodelen = node.childNodes.length;
        for ( var i = 0 ; i < nodelen ; i++ ) {
            var n = node.childNodes[i];
            if (n.nodeType == 4) {
                contentStr += n.nodeValue;
            } else {
                contentStr += n.xml;
            }
        }
        return contentStr;
     },

    _getContentAsStringMozilla: function(node)
    {
        var xmlSerializer = new XMLSerializer();
        var contentStr = "";
        var nodelen = node.childNodes.length;

        for ( var i = 0 ; i < nodelen ; i++ ) {
            var n = node.childNodes[i];
            if (n.nodeType == 4) {
                contentStr += n.nodeValue;
            } else {
                contentStr += xmlSerializer.serializeToString(n);
            }
        }
        return contentStr;
    },

    processCallback: function(id, element)
    {
        if (id == 'mqfCallbackBroker') {
		    try {
			    var objstr = this.getContentAsString(element);
                var obj = objstr.evalJSON();

			    eval(obj.callback_hook)(obj.value);
		    } catch (e) {
			    alert(e.message);
		    }
        }
    },

    processHTML: function (id, element)
    {
        var string = this.getContentAsString(element);
        var html = string.stripScripts();
        $(id).innerHTML = string;
        string.evalScripts();
    },

    getCallOptions: function(url, sesskey, args)
    {
        if (args.length < 2) {
            alert('getCallOptions(method, parameters [, id [, options]])');
            return false;
        } else {
            var method = args[0];

            var methodandid = method.split('!');

            method = methodandid[0];

            var hide_when_loading = methodandid[1];

            var parameters = args[1];
            var id = '';
            var options = '';
            var callback = "R=element";

            if (args.length > 2) {
                id = args[2];
            }

            if (args.length > 3) {
                options = args[3];
            }

            var p = new Array();
            var pc = 0;

            parameters.each(function(pval) {
                p[pc] = "P_" + pc + "=" + encodeURIComponent(Object.toJSON({p: pval}));
                pc++;
            });

            switch (typeof id) {
            case 'function':
                alert("The passing of callback functions is not supported by IE.\nUse the string 'function xx' instead");
                break;
            case 'string':
                if (id == "replacecanvas") {
                    callback = "R=replacecanvas";
                    break;
                }

                if (id.substr(0, 9) == 'function ') {
                    callback = "R=object";
                    id = id.substr(9);
                    break;
                }
            default:
                callback = "R=element";
            }

            var o = new Array();
            var i = 0;

            if (typeof options == "object") {
                for (var k in options) {
                    if (k == "extend") continue;
                    o[i] = "O_" + k + "=" + Object.toJSON({o: options[k]});
                    i++;
                }
            }

            if (id) {
                o[i] = "O_id=" + Object.toJSON({o: id});
                i++;
            }


            var execid = Math.round((Math.random() * Math.random()) * 1000000000);

            o[i] = "O_execid=" + Object.toJSON({o: execid});

            var paramstring = '';

            if (p.length > 0) {
                for (i=0;i<p.length;i++) {
                    paramstring += "&"+p[i];
                }
            }

            var optionstring = '';

            if (o.length > 0) {
                for (i=0;i<o.length;i++) {
                    optionstring += "&"+o[i];
                }
            }

            var ret = {
                url: url,
                data: "F="+method+"@"+sesskey+"&" + callback + paramstring + optionstring,
                hide_when_loading: hide_when_loading,
                execid: execid
            };

            return (ret);
        }
    },

    loading: function(cmd)
    {
        /* CREATE DIV WITH LOADING INFO */
        if (!$('mqfRequestLoadingDiv')) {
            try {
                var div = document.createElement('div');
                div.setAttribute('id', 'mqfRequestLoadingDiv');
                var nl = document.getElementsByTagName('body');
                if (nl.length == 1) {
                    var body = nl[0];
                    body.appendChild(div);
                    div.innerHTML = "<table id='mqfRequestLoadingDivTable' class='mqfRequestLoadingTable' style='background-color: red; color: white; width: 100px; margin: 4px; position: absolute; left: 0; top: 0'><tr><td id='mqfLoadingTextCell'>"+this.defaultLoadingText+"</td></tr></table>";
                }
            } catch (e) {
                alert(e);
            }
        }

        /* SHOW 'LOADING' WHEN REQUEST STARTS */
        if (cmd == 'show') {
            $('mqfRequestLoadingDiv').style.display = 'block';
        } else if (cmd == 'hide') {
            $('mqfRequestLoadingDiv').style.display = 'none';
        }
    },

    call: function(opt)
    {
        if (!opt) return false;

        var url = opt.url;
        var hide_when_loading = opt.hide_when_loading;
        var execid = opt.execid;

        MQF.loading('show');

        try {
            if (hide_when_loading) {
                try {
                    Protoload.timeUntilShow = 100;

                    if (hide_when_loading == 'body') {
                        mqf_hide_when_loading = document.body;
                        Protoload.startWaiting(mqf_hide_when_loading, 'bigBlackWaiting');
                    } else {
                        mqf_hide_when_loading = $(hide_when_loading);

                        if (mqf_hide_when_loading.tagName == 'DIV') {
                            Protoload.startWaiting(mqf_hide_when_loading, 'bigBlackWaiting');
                        } else {
                            Protoload.startWaiting(mqf_hide_when_loading);
                        }
                    }

                } catch (e) {
                    alert(e.message);
                }
            }

            var options = {
                onComplete: mqfRequestCompleted,
                parameters: opt.data,
                method: 'post'
            };



            new Ajax.Request(url, options);

        } catch (e) {
            mqfRequestCompleted();
            alert(e.message);
        }

    },

    paintProducts: function()
    {
    },

    openTranslateUI: function(canvas, orgstring, currentstring, language, refresh)
    {
        var newString = prompt("Translate phrase:\n\nLanguage: " + language + "\nPhrase: "+orgstring, currentstring);

        if (newString != null && newString.length > 0) {
            mqfCallMethod(canvas+'.addTranslation', [orgstring, newString, language, refresh]);
        }
    },

    refreshTranslateDialog_callback: function(obj) {
        var div = $(obj.domID);
        div.innerHTML = obj.HTML;
    },

    refreshTranslateDialog: function(canvas, domID)
    {
        mqfCallMethod(canvas+'.getTranslationDialogContent', [domID], 'function MQF.refreshTranslateDialog_callback');
    },

    /**
    *
    * Usage:
    *
    * <div id='eipid'>Click me</div>
    *
    * <script>
    * MQF.editInPlace('eipid', 'Module.method');
    * </script>
    *
    */
    editInPlace: function(element, modulemethod, args)
    {
        if (Scriptaculous) {
            if (!args) {
                args = {};
            }

            args.onComplete = mqfRequestCompleted;

            var editor = new Ajax.InPlaceEditor(element, mqfGetCallUrl(modulemethod, [], 'function MQF.editInPlaceCallback') + '&O_urltype=' + Object.toJSON({o: 'EIP'}), args);
            
            return editor;
        } else {
            alert('ERROR! Scriptaculous has not been loaded!');
        }
    },

    editInPlaceCallback: function(eip)
    {
        try {
            if ($(eip.elementId) || !eip.elementId || !eip.value) {
                if (eip.value.length == 0) {
                    eip.value = 'Click to edit...';
                }
                $(eip.elementId).innerHTML = eip.value;
            } else {
                alert("The callback needs a object like this: {elementId: <id>, value: <value>} from the called MQF method.");
            }
        } catch (e) {
            alert("The callback needs a object like this: {elementId: <id>, value: <value>} from the called MQF method.");
        }
    },

    selectInPlace: function(element, modulemethod, args)
    {
        if (Scriptaculous) {
            if (!args) {
                args = {};
            }

            args.onComplete = mqfRequestCompleted;

            var editor = new Ajax.InPlaceCollectionEditor(element, mqfGetCallUrl(modulemethod, [], 'function MQF.editInPlaceCallback') + '&O_urltype=' + Object.toJSON({o: 'EIP'}), args);
            
            return editor;
        } else {
            alert('ERROR! Scriptaculous has not been loaded!');
        }
    },

    insertModule: function(moduleid, elementname)
    {
        mqfCallMethod('UI.insertDynamicModule', [moduleid, elementname], 'function MQF.insertModuleCallback');
    },

    insertModuleCallback: function(html)
    {
        if (html && html.length && $('mqfmoduleplaceholder')) {
            $('mqfmoduleplaceholder').insert({bottom: html});
        }
    },

    autoCompleter: function(completerid, completerchoiceid, modulemethod, options)
    {
        var mqfurl = mqfGetCallUrl(modulemethod, []);

        if (!options) {
            var options = {};
        }

        options['method'] = 'post';

        mqfurl += '&O_urltype=' + Object.toJSON({o: 'AUTOCOMPLETE'});
        mqfurl += '&O_autocompletevar=' + Object.toJSON({o: completerid});

        new Ajax.Autocompleter(completerid, completerchoiceid, mqfurl, options);
    }
}

var MQF = new __MQF__();

function mqfRequestCompleted(xhr)
{

    MQF.loading('hide');
    $('mqfLoadingTextCell').innerHTML = MQF.defaultLoadingText;
    //$('mqfRequestLoadingDiv').style.display = 'none';

    if (mqf_hide_when_loading) {
        try {
            Protoload.stopWaiting(mqf_hide_when_loading);
            mqf_hide_when_loading = false;
        } catch (e) {
            alert(e.message);
        }
    }

    var execid = 0;

    if (!xhr) return true;

    if(!xhr.responseXML) {
        if(mqf_exception_msg != null) { // quick hack to check if we have the mqf exception stuff loaded
            mqf_exception_show_popup("Didn't recive a valid XML document", xhr.responseText);

        } else alert("Didn't recive a valid XML document:\n" + xhr.responseText);

    }

    try {
        var response = xhr.responseXML.getElementsByTagName("ajax-response");

        var ares = xhr.responseXML.getElementsByTagName("ROOTNODE");
        var elements = ares[0].childNodes;
        var arelen = elements.length;

        for (var i=0;i<arelen;i++) {
            if (elements[i].nodeType != 1) continue;
            execid = elements[i].getAttribute("execid");
            break;
        }

    } catch (e) {
        return false;
    }

	if (response == null || response.length != 1) {
	    alert(xhr.responseText);
	    return false;
	}

    var elements = response[0].childNodes;
    var elen = elements.length;

    for (var i = 0 ; i < elen; i++) {
        var responseElement = elements[i];

        if (responseElement.nodeType != 1) {
            continue;
        }

        var responseType = responseElement.getAttribute("type");
        var responseId   = responseElement.getAttribute("id");

        if (responseType == 'object') {
            MQF.processCallback(responseId, responseElement);
        } else if (responseType == 'element') {
            MQF.processHTML(responseId, responseElement);
        } else {
            alert(responseElement);
            return false;
        }
    }
}

function mqfThrowException(e)
{
	alert(e.message);
}

function mqfRedirectBrowser(data)
{
    document.location = data.URL;
}

function mqfFrontControllerFailure(ex)
{
	if (ex.message) {
	    var error_msg = ex.message;
	} else {
	    var error_msg = "The application failed without giving an error message!\n The session has probably timeout.\n Press F5 or Reload/Refresh in your browser to restart.";
	}

	alert(error_msg);

    return true;
}



var __MQFCookieCallbacks__ = Class.create();

__MQFCookieCallbacks__.prototype = {
    callbacks: false,

	initialize: function()
	{
        this.callbacks = $H({});
	},

	register: function(id, func)
	{
        this.callbacks[id] = func;
    },

    run: function(id, value)
    {
    }
}

var MQFCC = new __MQFCookieCallbacks__;




function mqfReadCookies(prefix, sessionid)
{
	var cookiestring = document.cookie;

	var onlyid = '';
	if (arguments.length == 3) {
        onlyid = arguments[2];
    }

	var cookies = cookiestring.split('; ');

	cookies.each(function(c)
	{
		var a = c.split('=');
		if (a[1] != 'ignore' && a[0] == prefix+'_'+sessionid) {
			var str = decodeURI(a[1]);
            var s = str.evalJSON();

			for (i=0;i<s.length;i++) {
			 	a = s[i].split('§');
                if (onlyid != '' && a[0] != onlyid) continue;
			 	mqfSetValueFromCookie(a[0], a[1]);
			}
		}
	});
}


function mqfSetValueFromCookie(id, value)
{
	if ($(id)) {
		if ($(id).type) {
			switch ($(id).type) {
				case 'select-one':
                    if ($(id).options.length > 0) {
                        var optlen = $(id).options.length;

                        for (var i=0; i<optlen; i++) {
                            if ($(id).options[i].value == value) {
                                $(id).options[i].selected = true;
                            }
                        }
                    }
                    break;
				case 'checkbox':
				case 'radio':
					if (value == 'on') {
						$(id).checked = true;
					} else {
						$(id).checked = false;
					}
					break;
				case 'text':
				case 'hidden':
				    $(id).focus();
					if (!value) value = '';
					$(id).value = value;
                    $(id).blur();
                    break;
			}
		}
	}
}

function mqfWriteCookie(formid, prefix, sessionid)
{
	var s = '';
	var frm = $(formid).elements;
	var array = new Array();
    var frmlen = frm.length;

    for (var i=0;i<frmlen;i++) {
    	var id = frm[i].id;
    	var value = '';
    	if ((value = mqfGetValue(id)) === false) continue;
    	s = id + "§" + value;
    	array[i] = s;
    }

    s = Object.toJSON(array);

    var exp = new Date();
    var expiretime = exp.getTime() + (10*60*1000);
    exp.setTime(expiretime);

    s = prefix+"_"+sessionid+"="+s+"; expires="+exp.toGMTString()+";";
    document.cookie = s;
}


function mqfGetValue(id) {
	if ($(id)) {
		if ($(id).type) {
			switch ($(id).type) {

				case 'select-one':
                    return $(id).options[$(id).selectedIndex].value;

				case 'button':
				case 'file':
				case 'image':
				case 'reset':
				case 'submit':
					return false;
				case 'checkbox':
				case 'radio':
					if ($(id).checked) {
						return 'on';
					} else {
						return 'off';
					}
				case 'text':
				case 'hidden':
				case 'password':
					var value = $F(id);
					if (!value) return value = '';
					return value;
				default:
					return false;
			}
		}
	} else {
		return false;
	}
}


function mqfClearCookies()
{
	var cs = document.cookie;
	var cookies = cs.split('; ');

	cookies.each(function(c)
	{
		var a = c.split('=');

		var exp = new Date();
    	exp.setTime(0);

		document.cookie = a[0] + "=ignore; expires="+exp.toGMTString()+";";
	});
}

function mqfIsMobilePhoneNumber(num)
{
	if (isNaN(num)) return false;
	if (num.length != 8) return false;
	if (num.substr(0, 1) != 4 && num.substr(0, 1) != 9) return false;
	return true;
}

function mqfIsLegalPhone(num, checkmobile)
{
	if (isNaN(num)) return false;
	if (num.length != 8) return false;
	if (num.substr(0, 1) == 1 || num.substr(0, 1) == 8) return false;
	if (checkmobile) return mqfIsMobilePhoneNumber(num);
	return true;
}

function mqfGetValueFromElements(form, type, id)
{
	var elements = Form.getInputs(form, type, id)

	var value = false;

	elements.each(
		function(obj) {
			if (obj.checked) {
				value = obj.value;
			}
		}
	);

	return value;
}
