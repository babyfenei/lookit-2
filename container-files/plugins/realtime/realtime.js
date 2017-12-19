var xmlHttp;
var url;
var ds_step = 0;
var sizeset = false;
var count   = 0;
var browser = "";

function countdown_update() {
	ds_step--;

	if (ds_step < 0) {
		ds_step = document.getElementById("ds_step").value;
		imageOptionsChanged('countdown');
		sizeset = false;
	}

	var el = document.getElementById('countdown');

	if (el) {
		el.innerHTML = '<strong>' + ds_step + ' seconds left.</strong>';
	}

	resizeWindow();

	setTimeout('countdown_update()', 1000)
}

setTimeout('countdown_update()', 1000);

function realtimeDetectBrowser() {
	if (navigator.userAgent.indexOf('MSIE') >= 0) {
		browser = "IE";
	}else if (navigator.userAgent.indexOf('Chrome') >= 0) {
		browser = "Chrome";
	}else if (navigator.userAgent.indexOf('AppleWebKit') >= 0) {
		browser = "Safari";
	}else if (navigator.userAgent.indexOf('Opera') >= 0) {
		browser = "Opera";
	}else if (navigator.userAgent.indexOf('Mozilla') >= 0) {
		browser = "FF";
	}else{
		browser = "Other";
	}

	return browser;
}

function getFromServer() {
	xmlHttp=GetXmlHttpObject();
	if (xmlHttp==null) {
		alert ("Get Firefox!");
		return;
	}

	url="graph_ajax_rt.php"+url;

	xmlHttp.onreadystatechange=stateChanged;
	xmlHttp.open("GET",url,true);
	xmlHttp.send(null);
}

function resizeWindow() {
	height = parseInt(document.getElementById("realtime").height) + 150;
	width  = parseInt(document.getElementById("realtime").width) + 40;

	realtimeDetectBrowser();
	if (browser == "IE") {
		window.resizeTo(width, height);
	}else if (browser == "Safari") {
		window.resizeTo(width, height-30);
	}else if (browser == "Chrome") {
		window.resizeTo(width, height);
	}else{
		window.resizeTo(width, height);
	}
}

function imageOptionsChanged(action) {
	sync = document.getElementById("sync").checked;
	if (sync) {
		sync = "on";
	}else{
		sync = "";
	}

	graph_start    = document.getElementById("graph_start").value;
	graph_end      = 0;
	local_graph_id = document.getElementById("local_graph_id").value;
	ds_step        = document.getElementById("ds_step").value;

	url="?action="+action+"&graph_start=-"+graph_start+"&local_graph_id="+local_graph_id+"&ds_step="+ds_step+"&count="+count+"&sync="+sync;

	getFromServer();
}

function stateChanged() {
	var r_ds_step;
	var r_graph_start;
	var r_sync;

	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
		reply         = xmlHttp.responseText;
		reply         = reply.split("!!!");
		imaging       = reply[0];
		r_ds_step     = reply[1];
		r_graph_start = reply[2];
		r_sync        = reply[3];

		document.getElementById("realtime").src = imaging;

		if (r_sync == "on") {
			document.getElementById("sync").checked=true;

			select = document.getElementById("ds_step");
			for (var j = 0; j < select.length; j++) {
				if (select.options[j].value == r_ds_step) {
					document.getElementById("ds_step").selectedIndex = j;
					break;
				}
			}

			select = document.getElementById("graph_start");
			for (var j = 0; j < select.length; j++) {
				if (select.options[j].value == Math.abs(r_graph_start)) {
					document.getElementById("graph_start").selectedIndex = j;
					break;
				}
			}
		}else{
			document.getElementById("sync").checked=false;
		}
	}
}

function GetXmlHttpObject() {
	var objXMLHttp=null;

	if (window.XMLHttpRequest) {
		objXMLHttp=new XMLHttpRequest();
	} else if (window.ActiveXObject) {
		objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP");
	}

	return objXMLHttp;
}
