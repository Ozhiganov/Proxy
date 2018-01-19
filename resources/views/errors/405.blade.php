@extends('layouts.app')

@section('content')
<div class="container content-container">
	<h1>Aktion nicht erlaubt!</h1>
	<p>
	Sie haben versucht, eine Aktion durchzuführen, die unser Proxy aktiv nicht unterstützt. Der Grund hierfür ist, dass dadurch häufig private Daten übertragen werden (z.B. bei einem Login auf einer Webseite).
	</p>
	<p>
	Würden wir dies unterstützen, wären Ihre Daten vor der aufgerufenen Webseite nicht mehr sicher, da diese aktiv mitgeteilt werden würden.
	</p>
	<p>
		Wenn Sie die gewünschte Aktion trotzdem durchführen möchten, navigieren Sie bitte zurück zur entsprechenden Seite und schalten unseren Proxy vor dem Übermitteln der Daten aus. Alternativ können Sie die gewünschte Seite auch direkt in einem neuen Tab aufrufen.</li>
	</p>
</div>
@endsection
