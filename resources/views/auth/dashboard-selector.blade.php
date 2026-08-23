<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choisir un dashboard | Sitiame Capital</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
    <meta name="theme-color" content="#0f172a">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #0f172a;
            background-image: radial-gradient(circle at 20% 20%, rgba(240,169,58,0.08), transparent 40%),
                               radial-gradient(circle at 80% 80%, rgba(59,91,219,0.12), transparent 40%);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #e6e9f2;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .wrap { max-width: 900px; width: 100%; text-align: center; }
        .logo { height: 56px; margin-bottom: 22px; }
        h1 { font-size: 26px; margin: 0 0 8px 0; color: #ffffff; }
        p.sub { color: #9aa5c4; margin: 0 0 34px 0; font-size: 14px; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            text-align: left;
        }
        .card {
            display: block;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 12px;
            padding: 20px;
            text-decoration: none;
            color: #e6e9f2;
            transition: border-color .15s ease, transform .15s ease, background .15s ease;
        }
        .card:hover {
            border-color: #f0a93a;
            background: rgba(255,255,255,0.07);
            transform: translateY(-2px);
        }
        .card .icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: rgba(240,169,58,0.15);
            color: #f0a93a;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            margin-bottom: 14px;
        }
        .card h3 { margin: 0 0 4px 0; font-size: 15px; color: #ffffff; }
        .card p { margin: 0; font-size: 12.5px; color: #9aa5c4; line-height: 1.4; }
        .footer { margin-top: 36px; font-size: 12.5px; color: #6b7593; }
        .footer a { color: #9aa5c4; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="wrap">
        <img src="{{ asset('images/sitiam.png') }}" alt="Sitiame Capital" class="logo">
        <h1>Choisissez votre dashboard</h1>
        <p class="sub">Ce compte a accès à plusieurs interfaces — sélectionnez celle que vous voulez ouvrir.</p>

        <div class="grid">
            <a href="{{ route('dashboard') }}" class="card">
                <div class="icon">🏢</div>
                <h3>Entreprise</h3>
                <p>Comptabilité, trésorerie, facturation, stock — le portail client PME.</p>
            </a>
            <a href="{{ route('accountant.dashboard') }}" class="card">
                <div class="icon">📊</div>
                <h3>Cabinet Comptable</h3>
                <p>Dossiers clients, validation des documents OCR, gestionnaire de fichiers.</p>
            </a>
            <a href="{{ route('commercial.dashboard') }}" class="card">
                <div class="icon">🤝</div>
                <h3>Commercial</h3>
                <p>Portefeuille, prospection, commissions et suivi des leads.</p>
            </a>
            <a href="{{ route('commercial-supervisor.dashboard') }}" class="card">
                <div class="icon">🧭</div>
                <h3>Supervision Commerciale</h3>
                <p>Vue d'ensemble de l'équipe commerciale et de ses performances.</p>
            </a>
            <a href="{{ route('admin.dashboard') }}" class="card">
                <div class="icon">🛡️</div>
                <h3>Administration Plateforme</h3>
                <p>RBAC, KYC, licences, facturation, ops center — accès super-admin.</p>
            </a>
        </div>

        <div class="footer">
            <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" style="background:none;border:none;color:#9aa5c4;text-decoration:underline;cursor:pointer;font-size:12.5px;padding:0;">Se déconnecter</button>
            </form>
        </div>
    </div>
</body>
</html>
