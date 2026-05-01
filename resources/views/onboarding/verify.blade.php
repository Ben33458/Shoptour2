<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>E-Mail bestätigen – Kolabri Onboarding</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0f172a; color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5rem; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2rem 2.5rem; width: 100%; max-width: 440px; }
    .logo { text-align: center; font-size: 2rem; margin-bottom: .5rem; }
    h1 { text-align: center; font-size: 1.35rem; font-weight: 700; margin-bottom: .4rem; }
    .sub { text-align: center; font-size: .9rem; color: #94a3b8; margin-bottom: 1.75rem; line-height: 1.6; }
    label { display: block; font-size: .8rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .35rem; }
    input[type=text] { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: #f1f5f9; font-size: 1.8rem; padding: .7rem 1rem; outline: none; text-align: center; letter-spacing: .3em; font-family: monospace; transition: border-color .15s; }
    input[type=text]:focus { border-color: #60a5fa; }
    .btn { width: 100%; padding: .85rem; background: #1d4ed8; color: #fff; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; margin-top: 1rem; transition: opacity .15s; }
    .btn:hover { opacity: .85; }
    .alert { border-radius: 8px; padding: .75rem 1rem; font-size: .9rem; margin-bottom: 1.25rem; }
    .alert-info  { background: #1e3a5f; border: 1px solid #2563eb; color: #93c5fd; }
    .alert-error { background: #7f1d1d; border: 1px solid #dc2626; color: #fecaca; }
    .back { display: block; text-align: center; margin-top: 1rem; color: #60a5fa; font-size: .85rem; text-decoration: none; }
    .back:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <div class="logo"><img src="{{ asset('images/kolabri_logo.png') }}" alt="Kolabri Getränke" style="height:60px;width:auto"></div>
    <h1>E-Mail bestätigen</h1>
    <p class="sub">
        Wir haben dir eine E-Mail geschickt.<br>
        Klicke auf den Link in der E-Mail oder gib den 6-stelligen Code ein.
    </p>

    @if(session('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session('error') || $errors->any())
        <div class="alert alert-error">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('onboarding.post-verify') }}">
        @csrf
        <label for="code">6-stelliger Code</label>
        <input type="text" id="code" name="code" maxlength="6" pattern="\d{6}"
               inputmode="numeric" placeholder="· · · · · ·" autofocus required>
        <button type="submit" class="btn">Code bestätigen</button>
    </form>

    <a href="{{ route('onboarding.start') }}" class="back">← E-Mail neu eingeben</a>
</div>
</body>
</html>
