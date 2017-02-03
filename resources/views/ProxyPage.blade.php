<html>
	<head>
		<link href="/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
		<link href="/css/style.css" rel="stylesheet" type="text/css" />
	</head>
	<body>
		<nav>
			<div class="container-fluid">
				<div class="row">
					<div id="proxy-logo" class="col-xs-2">
						<img src="/img/mglogo_klein.png" />
					</div>
					<div id="proxy-text" class="col-xs-8">
						<ul class="list-unstyled">
							<li>MetaGer/SUMA-EV Proxy (Beta): Anonymisiert & verschlüsselt. SUMA-EV ist weder Eigentümer noch Urheber von Inhalten.</li>
							<li>Skripte sind deaktiviert. Webseiten-Darstellung kann verändert sein.</li>
						</ul>
					</div>
					<div id="proxy-options" class="col-xs-2">
						<ul class="list-unstyled">
						@if(!$scriptsEnabled)
						<li><a href="{{$scriptUrl}}" class="btn btn-warning btn-xs">Skripte blockiert</a></li>
						@else
						<li><a href="{{$scriptUrl}}" class="btn btn-info btn-xs">Skripte zugelassen</a></li>
						@endif
						@if(!$cookiesEnabled)
						<li><a href="{{$cookieUrl}}" class="btn btn-warning btn-xs">Cookies gesperrt</a></li>
						@else
						<li><a href="{{$cookieUrl}}" class="btn btn-info btn-xs">Cookies zugelassen</a></li>
						@endif
						<li><a href="{!!$targetUrl!!}" class="btn btn-danger btn-xs">Proxy ausschalten</a></li>
						</ul>
					</div>
				</div>
			</div>
		</nav>
		<div class ="container-fluid">
		</div>
		<iframe id="site-proxy-iframe" src="{!!$iframeUrl!!}" >

		</iframe>
		<script src="/js/jquery.min.js"></script>
		<script src="/js/bootstrap.min.js"></script>
		<script src="/js/script.js"></script>
	</body>
</html>
