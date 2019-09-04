$("#site-proxy-iframe").load(function () {
    var title = $("#site-proxy-iframe").contents().find("title").html();
    $("title").html(title + " - MetaGer Proxy");
});