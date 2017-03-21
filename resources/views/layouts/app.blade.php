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
					<div id="proxy-logo" class="visible-lg col-lg-2">
						<a href="https://metager.de">
							<img src="/img/mglogo_klein.png" />
						</a>
					</div>
					<div id="proxy-text" class="col-xs-6 col-lg-5">
						@if(isset($targetUrl))
						<ul class="list-unstyled">
							<li><nobr>MetaGer/SUMA-EV Proxy (Beta): Anonymisiert & verschlüsselt.</nobr> <nobr>SUMA-EV ist weder Eigentümer noch Urheber von Inhalten.</nobr></li>
							<li>Skripte sind deaktiviert. Webseiten-Darstellung kann verändert sein.</li>
							<li><a href="{!!$targetUrl!!}" class="btn btn-danger btn-xs">Proxy ausschalten</a></li>
						</ul>
						@endif
					</div>
					<div id="proxy-advertisement" class="col-xs-6 col-lg-5">
					<a href="http://metager.to/k25u0" target="_blank">
						<span class="ad-marker">Anzeige</span>
						<p class="heading">Samsung Galaxy S7 im Preisvergleich</p>
						<p class="url">www.idealo.de/preisvergleich/samsung-galaxy-s7/</p>
						<p class="line">Jetzt Samsung Galaxy S7 ohne Vertrag zum günstigsten Preis finden!</p>
						<p class="line">Große Shopvielfalt, Testberichte & Meinungen. Jetzt auf idealo.de</p>
					</a>

					</div>
				</div>
			</div>
		</nav>

		@yield('content')

		<script src="/js/jquery.min.js"></script>
		<script src="/js/bootstrap.min.js"></script>
		<script src="/js/script.js"></script>
	</body>
</html>
