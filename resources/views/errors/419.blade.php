<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitzung abgelaufen</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fafaf8;
            font-family: system-ui, -apple-system, sans-serif;
            color: #1a1a1a;
        }
        .container {
            text-align: center;
            padding: 48px 32px;
            max-width: 440px;
        }
        .icon {
            font-size: 4rem;
            display: block;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #aaa;
            margin-bottom: 10px;
        }
        .tagline {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 12px;
        }
        .sub {
            font-size: .92rem;
            color: #666;
            margin-bottom: 28px;
            line-height: 1.6;
        }
        .actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 10px 22px;
            border-radius: 6px;
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #1a1a1a; color: #fff; }
        .btn-primary:hover { background: #333; }
        .btn-outline { background: transparent; color: #555; border: 1px solid #ccc; }
        .btn-outline:hover { border-color: #999; color: #1a1a1a; }
    </style>
</head>
<body>
    <div class="container">
        <span class="icon">⏱️</span>
        <h1>Sitzung abgelaufen</h1>
        <p class="tagline">Die Seite ist nicht mehr aktuell.</p>
        <p class="sub">
            Deine Sitzung ist abgelaufen oder du hast die Seite zu lange offen gelassen.<br>
            Lade die Seite neu oder melde dich erneut an.
        </p>
        <div class="actions">
            <button class="btn btn-primary" onclick="history.back()">← Zurück &amp; erneut versuchen</button>
            <a href="{{ route('login') }}" class="btn btn-outline">Neu anmelden</a>
        </div>
    </div>
</body>
</html>
