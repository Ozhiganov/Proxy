<html>
	<head>
		<meta charset="utf-8" />
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
						<li><a href="{!!$targetUrl!!}" class="btn btn-danger btn-xs">Proxy ausschalten</a></li>
						</ul>
					</div>
				</div>
			</div>
		</nav>
		<div class ="container-fluid">
		</div>
		<iframe
			id="site-proxy-iframe"
			src="{!!$iframeUrl!!}"
			sandbox="
			allow-forms
			allow-popups
			allow-top-navigation
			allow-same-origin
			allow-scripts
			"
			 >

		</iframe>
		<script src="/js/jquery.min.js"></script>
		<script src="/js/bootstrap.min.js"></script>
		<script src="/js/script.js"></script>
	</body>
</html>
