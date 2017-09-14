<html>
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<link href="/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
<link href="/css/style.css" rel="stylesheet" type="text/css" />
</head>
<body>
	<nav>
		<div class="main">
			<div class="metager-logo">
				<a href="https://metager.de"> <img src="/img/mglogo_klein.png">
				</a>
			</div>
			<div class="navigation">
				<input class="form-control" type="text" value="{{$targetUrl}}" readonly="">
			</div>
			<div id="proxy-text" class="">
				<a href="http://www.duden.de/rechtschreibung/Trug"
					class="btn btn-danger btn-xs">Proxy ausschalten</a>
			</div>
		</div>
		<div style="text-align: center; font-size: 11px;">SUMA-EV ist
			weder Eigentümer noch Urheber von Inhalten. Skripte sind
			deaktiviert. Webseiten-Darstellung kann verändert sein.</div>
	</nav>

	@yield('content')

	<script src="/js/jquery.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/script.js"></script>
</body>
</html>
