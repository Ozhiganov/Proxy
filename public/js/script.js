$(document).ready(function() {
    resizeIframe();
    $(window).resize(function(){
    	resizeIframe();
    });
});

function resizeIframe(){
	$("#site-proxy-iframe").height(500);
	var navHeight = $("nav").outerHeight();
    var bodyHeight = $("body").innerHeight();
    $("#site-proxy-iframe").height(bodyHeight - navHeight);
}