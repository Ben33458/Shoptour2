<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Onboarding abgeschlossen – Kolabri</title>
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0f172a; color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 480px; text-align: center; }
    .icon { font-size: 3.5rem; margin-bottom: 1rem; }
    h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: .75rem; color: #4ade80; }
    p { color: #94a3b8; font-size: .95rem; line-height: 1.7; margin-bottom: 1rem; }
    a { color: #60a5fa; text-decoration: none; font-size: .9rem; }
    a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <div class="icon">✅</div>
    <h1>Deine Angaben wurden übernommen.</h1>
    <p>Vielen Dank – dein Zugang wird gerade vorbereitet.<br>
       Du wirst per E-Mail benachrichtigt, sobald du dich an der Stempeluhr anmelden kannst.</p>
    <a href="{{ route('timeclock.index') }}">← Zurück zur Stempeluhr</a>
</div>
</body>
</html>
