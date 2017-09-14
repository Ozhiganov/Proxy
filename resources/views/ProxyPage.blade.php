@extends('layouts.app', ['url' => $targetUrl])

@section('content')
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
@endsection
