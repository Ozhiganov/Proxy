@extends('layouts.app')

@section('content')
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
@endsection
