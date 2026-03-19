<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 — Zu viele Anfragen</title>
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
            max-width: 480px;
        }
        .emoji {
            font-size: 5rem;
            display: block;
            margin-bottom: 24px;
            animation: wobble 1.2s ease-in-out infinite;
        }
        @keyframes wobble {
            0%, 100% { transform: rotate(-4deg); }
            50%       { transform: rotate(4deg); }
        }
        h1 {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 12px;
        }
        .tagline {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.4;
            margin-bottom: 16px;
        }
        .sub {
            font-size: .95rem;
            color: #666;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        a {
            display: inline-block;
            padding: 10px 24px;
            background: #1a1a1a;
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
        }
        a:hover { background: #333; }
        .retry {
            display: block;
            margin-top: 12px;
            font-size: .8rem;
            color: #aaa;
        }
    </style>
</head>
<body>
    <div class="container">
        <span class="emoji">🍺</span>
        <h1>429 — Zu viele Anfragen</h1>
        <p class="tagline">
            Langsam, langsam —<br>auch beim Zapfen braucht's Geduld!
        </p>
        <p class="sub">
            Sie haben zu viele Anfragen in zu kurzer Zeit gestellt.<br>
            Gönnen Sie sich kurz eine Pause — wie das Bier beim Setzen.
        </p>
        <a href="{{ url()->previous('/') }}">← Zurück</a>
        <span class="retry">Bitte versuchen Sie es in ein paar Sekunden erneut.</span>
    </div>
</body>
</html>
