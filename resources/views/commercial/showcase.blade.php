@extends('layouts.app')

@section('title', 'SITIAME CAPITAL — Marketing · Community · Service Client')
@section('page_title', 'Offres & Services Commerciaux')

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,600;0,9..144,700;1,9..144,500&family=Inter:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --navy: #12294B;
    --navy-dark: #0B1E38;
    --emerald: #1E7A5F;
    --emerald-light: #2FA37D;
    --gold: #C9A227;
    --white: #FFFFFF;
    --tint: #F3F6FA;
    --ink: #1C2430;
    --muted: #64748B;
    --line: #E4E9F0;
  }
  
  .sc-wrapper {
    background: var(--white);
    color: var(--ink);
    font-family: 'Inter', sans-serif;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 2rem;
  }
  .sc-wrapper h1, .sc-wrapper h2, .sc-wrapper h3 {
    font-family: 'Fraunces', serif;
    margin: 0;
    color: var(--navy);
  }
  .sc-mono { font-family: 'IBM Plex Mono', monospace; letter-spacing: 0.04em; }

  /* ---------- HEADER TABNAV ---------- */
  .sc-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    background: rgba(255, 255, 255, 0.95);
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 20;
  }
  .sc-brand {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Fraunces', serif;
    font-weight: 700;
    font-size: 18px;
    color: var(--navy);
  }
  .sc-brand .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--gold); }
  .sc-tabnav { display: flex; gap: 6px; background: var(--tint); border-radius: 999px; padding: 4px; }
  .sc-tabnav button {
    appearance: none; border: none; background: transparent; cursor: pointer;
    display: flex; flex-direction: column; align-items: flex-start; gap: 2px;
    padding: 8px 18px; border-radius: 999px; font-family: 'Inter'; color: var(--muted);
    transition: background .25s, color .25s;
  }
  .sc-tabnav button .lbl { font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
  .sc-tabnav button .kpi { font-size: 10.5px; font-family: 'IBM Plex Mono', monospace; opacity: 0.85; }
  .sc-tabnav button .status { width: 6px; height: 6px; border-radius: 50%; background: var(--line); transition: background .25s; }
  .sc-tabnav button.active { background: var(--navy); color: #fff; }
  .sc-tabnav button.active .status { background: var(--emerald-light); box-shadow: 0 0 0 3px rgba(47,163,125,0.25); }
  .sc-tabnav button.active .kpi { color: var(--gold); opacity: 1; }

  /* ---------- EYEBROWS & HEADERS ---------- */
  .sc-eyebrow {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11.5px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--gold);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .sc-eyebrow::before { content: ""; width: 22px; height: 1px; background: var(--gold); }
  .sc-eyebrow.on-dark { color: #E7C766; }

  /* ---------- MARKETING ---------- */
  #sc-marketing { padding: 60px 32px 60px; background: var(--white); }
  .sc-m-head { max-width: 680px; margin-bottom: 48px; }
  .sc-m-head h2 { font-size: clamp(28px, 3.5vw, 40px); line-height: 1.15; font-weight: 600; }
  .sc-m-head p { margin-top: 14px; color: var(--muted); font-size: 15px; line-height: 1.6; }

  .sc-why-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 56px; }
  .sc-why-card { background: var(--tint); border-radius: 14px; padding: 24px 20px; border: 1px solid transparent; transition: border-color .2s, transform .2s; }
  .sc-why-card:hover { border-color: var(--navy); transform: translateY(-3px); }
  .sc-why-card .num { font-family: 'IBM Plex Mono'; font-size: 11px; color: var(--gold); margin-bottom: 12px; }
  .sc-why-card h3 { font-size: 16.5px; font-weight: 600; margin-bottom: 8px; }
  .sc-why-card p { font-size: 12.8px; color: var(--muted); line-height: 1.5; }

  .sc-block-title { font-size: 12px; font-family: 'IBM Plex Mono'; text-transform: uppercase; letter-spacing: 0.12em; color: var(--navy); margin-bottom: 20px; font-weight: 600; }

  .sc-expertise-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
  .sc-exp-card { border: 1px solid var(--line); border-radius: 14px; padding: 22px; transition: box-shadow .2s, transform .2s; background: #fff; }
  .sc-exp-card:hover { box-shadow: 0 14px 30px -14px rgba(18,41,75,0.2); transform: translateY(-2px); }
  .sc-exp-card h3 { font-size: 15.5px; font-weight: 700; margin-bottom: 12px; color: var(--navy); }
  .sc-exp-card ul { margin: 0; padding: 0; list-style: none; }
  .sc-exp-card li { font-size: 13px; color: var(--muted); padding: 4px 0 4px 16px; position: relative; }
  .sc-exp-card li::before { content: ""; position: absolute; left: 0; top: 11px; width: 5px; height: 5px; background: var(--emerald-light); border-radius: 1px; }

  .sc-cta-row { display: flex; gap: 14px; margin-top: 48px; }
  .sc-btn { padding: 13px 26px; border-radius: 10px; font-weight: 600; font-size: 13.5px; cursor: pointer; border: 1.5px solid transparent; transition: all .2s; }
  .sc-btn-primary { background: var(--emerald); color: #fff; }
  .sc-btn-primary:hover { background: var(--emerald-light); color: #fff; }
  .sc-btn-ghost { border-color: var(--navy); color: var(--navy); }
  .sc-btn-ghost:hover { background: var(--navy); color: #fff; }

  /* ---------- COMMUNITY ---------- */
  #sc-community { padding: 70px 32px; background: var(--navy-dark); color: #fff; }
  #sc-community h2, #sc-community h3 { color: #fff; }
  .sc-c-head { max-width: 680px; margin-bottom: 48px; }
  .sc-c-head h2 { font-size: clamp(28px, 3.5vw, 40px); font-weight: 600; line-height: 1.15; }
  .sc-c-head p { margin-top: 14px; color: #B7C3D6; font-size: 15px; line-height: 1.6; }

  .sc-platform-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 48px; }
  .sc-platform-chip {
    display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.12); border-radius: 999px; padding: 8px 18px 8px 10px;
  }
  .sc-platform-chip .ico { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; color: #fff; }
  .sc-platform-chip .name { font-size: 13px; font-weight: 600; color: #fff; }
  .sc-platform-chip .stat { font-family: 'IBM Plex Mono'; font-size: 11px; color: var(--gold); }

  .sc-comm-layout { display: grid; grid-template-columns: 1.1fr 1.4fr; gap: 32px; align-items: start; }
  .sc-pillars { display: flex; flex-direction: column; gap: 14px; }
  .sc-pillar { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 20px 22px; }
  .sc-pillar h3 { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
  .sc-pillar p { font-size: 13px; color: #AEBBCF; line-height: 1.5; margin: 0; }

  /* Calendar Widget */
  .sc-calendar-card { background: #fff; color: var(--ink); border-radius: 16px; padding: 24px; box-shadow: 0 30px 60px -20px rgba(0,0,0,0.5); }
  .sc-calendar-card .cal-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
  .sc-calendar-card .cal-head h3 { font-size: 14px; color: var(--navy); font-weight: 700; }
  .sc-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
  .sc-cal-day-label { font-size: 10px; text-align: center; color: var(--muted); font-weight: 600; padding-bottom: 4px; }
  .sc-cal-cell { height: 54px; border-radius: 8px; background: var(--tint); position: relative; display: flex; align-items: flex-end; padding: 6px; gap: 3px; }
  .sc-cal-cell .d { position: absolute; top: 5px; left: 6px; font-size: 9.5px; color: var(--muted); font-family: 'IBM Plex Mono'; }
  .sc-post-dot { width: 8px; height: 8px; border-radius: 50%; }
  .sc-legend { display: flex; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
  .sc-legend span { font-size: 11px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
  .sc-legend .sw { width: 8px; height: 8px; border-radius: 50%; }

  /* ---------- SERVICE CLIENT ---------- */
  #sc-service { padding: 70px 32px; background: var(--white); }
  .sc-s-head { max-width: 680px; margin-bottom: 48px; }
  .sc-s-head h2 { font-size: clamp(28px, 3.5vw, 40px); font-weight: 600; line-height: 1.15; }
  .sc-s-head p { margin-top: 14px; color: var(--muted); font-size: 15px; line-height: 1.6; font-style: italic; }

  .sc-eng-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 56px; }
  .sc-eng-card { display: flex; gap: 14px; padding: 20px; border-radius: 12px; background: var(--tint); }
  .sc-eng-ico { width: 40px; height: 40px; border-radius: 50%; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .sc-eng-card h3 { font-size: 14.5px; font-weight: 700; margin-bottom: 4px; }
  .sc-eng-card p { font-size: 12.5px; color: var(--muted); line-height: 1.45; margin: 0; }

  .sc-space-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 36px; margin-bottom: 64px; align-items: center; }
  .sc-space-mock { background: var(--navy); border-radius: 16px; padding: 26px; color: #fff; }
  .sc-space-mock .row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
  .sc-space-mock .row .ico { width: 36px; height: 36px; border-radius: 50%; background: var(--gold); color: var(--navy); display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; }
  .sc-field { background: rgba(255,255,255,0.06); border-radius: 8px; padding: 12px 14px; font-size: 12.5px; color: #9FB0C8; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); }
  .sc-space-mock .accede { background: var(--emerald); text-align: center; padding: 11px; border-radius: 8px; font-size: 13px; font-weight: 700; color: #fff; margin-top: 6px; cursor: pointer; transition: background .2s; }
  .sc-space-mock .accede:hover { background: var(--emerald-light); }
  
  .sc-cap-list { list-style: none; margin: 0; padding: 0; }
  .sc-cap-list li { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--line); font-size: 14px; }
  .sc-cap-list li:last-child { border-bottom: none; }
  .sc-cap-list .chk { width: 18px; height: 18px; border-radius: 50%; background: var(--emerald); color: #fff; font-size: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }

  .sc-assist-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 56px; }
  .sc-assist-card { text-align: center; padding: 24px 16px; border-radius: 14px; border: 1px solid var(--line); cursor: pointer; transition: all .2s; background: #fff; }
  .sc-assist-card:hover { background: var(--navy); color: #fff; border-color: var(--navy); }
  .sc-assist-card:hover h3 { color: #fff; }
  .sc-assist-card:hover .sc-assist-ico { background: var(--gold); color: var(--navy); }
  .sc-assist-ico { width: 44px; height: 44px; border-radius: 50%; background: var(--tint); color: var(--navy); margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; transition: all .2s; }
  .sc-assist-card h3 { font-size: 13.5px; font-weight: 700; color: var(--navy); }

  .sc-sat-crm-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 64px; }
  .sc-sat-card { background: var(--navy); color: #fff; border-radius: 16px; padding: 28px; }
  .sc-sat-card h3 { color: #fff; font-size: 18px; margin-bottom: 14px; }
  .sc-stars { font-size: 24px; letter-spacing: 4px; margin-bottom: 16px; cursor: pointer; color: #3C5680; }
  .sc-stars span.active { color: var(--gold); }
  .sc-sat-opts { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
  .sc-sat-opt { border: 1px solid #3C5680; border-radius: 999px; padding: 7px 15px; font-size: 12px; cursor: pointer; transition: all .2s; color: #E2E8F0; }
  .sc-sat-opt.sel { background: var(--emerald); border-color: var(--emerald); color: #fff; }
  .sc-sat-send { background: var(--gold); color: var(--navy-dark); font-weight: 700; padding: 10px 22px; border-radius: 8px; font-size: 13px; display: inline-block; cursor: pointer; border: none; }

  .sc-crm-card { background: var(--tint); border-radius: 16px; padding: 28px; }
  .sc-crm-card h3 { font-size: 18px; margin-bottom: 16px; }
  .sc-crm-tags { display: flex; flex-wrap: wrap; gap: 8px; }
  .sc-crm-tag { background: #fff; border: 1px solid var(--line); border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; color: var(--navy); }

  .sc-faq-wrap { max-width: 820px; }
  .sc-faq-item { border-bottom: 1px solid var(--line); }
  .sc-faq-q { display: flex; justify-content: space-between; align-items: center; padding: 18px 4px; cursor: pointer; font-weight: 600; font-size: 14.5px; color: var(--navy); }
  .sc-faq-q .plus { font-family: 'IBM Plex Mono'; color: var(--emerald); font-size: 16px; transition: transform .25s; }
  .sc-faq-item.open .plus { transform: rotate(45deg); }
  .sc-faq-a { max-height: 0; overflow: hidden; transition: max-height .3s ease; font-size: 13.5px; color: var(--muted); line-height: 1.6; }
  .sc-faq-item.open .sc-faq-a { max-height: 120px; padding-bottom: 16px; }

  @media (max-width: 900px) {
    .sc-why-row, .sc-expertise-grid, .sc-eng-grid { grid-template-columns: repeat(2, 1fr); }
    .sc-comm-layout, .sc-space-layout, .sc-sat-crm-layout { grid-template-columns: 1fr; }
    .sc-assist-row { grid-template-columns: repeat(2, 1fr); }
    .sc-tabnav { display: none; }
  }
  @media (max-width: 600px) {
    .sc-why-row, .sc-expertise-grid, .sc-eng-grid, .sc-assist-row { grid-template-columns: 1fr; }
    .sc-cta-row { flex-direction: column; }
  }
</style>
@endpush

@section('content')
<div class="sc-wrapper">

  <!-- Header Nav bar -->
  <header class="sc-topbar">
    <div class="sc-brand"><span class="dot"></span> SITIAME CAPITAL</div>
    <div class="d-flex align-items-center gap-3">
      <nav class="sc-tabnav" id="sc-tabnav">
        <button data-target="sc-marketing" class="active">
          <span class="lbl"><span class="status"></span> Marketing</span>
          <span class="kpi">+120% leads</span>
        </button>
        <button data-target="sc-community">
          <span class="lbl"><span class="status"></span> Community</span>
          <span class="kpi">4 réseaux actifs</span>
        </button>
        <button data-target="sc-service">
          <span class="lbl"><span class="status"></span> Service client</span>
          <span class="kpi">réponse 24–48h</span>
        </button>
      </nav>
      <a href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}" class="btn btn-primary rounded-pill px-3 py-2 fw-semibold btn-sm d-flex align-items-center gap-1">
        <i data-feather="plus-circle" style="width:16px; height:16px;"></i> Ajouter Client / Entreprise
      </a>
    </div>
  </header>

  <!-- ================= MARKETING ================= -->
  <section id="sc-marketing">
    <div class="sc-m-head">
      <div class="sc-eyebrow">Partie 1 · Marketing</div>
      <h2>Le marketing, un investissement stratégique.</h2>
      <p>SITIAME CAPITAL conçoit et déploie des stratégies marketing performantes pour les entreprises, PME, startups et institutions — au service d'une croissance mesurable et durable.</p>
    </div>

    <div class="sc-why-row">
      <div class="sc-why-card"><div class="num">01</div><h3>Plus de visibilité</h3><p>Développez votre notoriété auprès de vos marchés cibles.</p></div>
      <div class="sc-why-card"><div class="num">02</div><h3>Plus de clients</h3><p>Attirez des prospects qualifiés et augmentez votre portefeuille.</p></div>
      <div class="sc-why-card"><div class="num">03</div><h3>Plus de ventes</h3><p>Convertissez davantage de prospects en clients fidèles.</p></div>
      <div class="sc-why-card"><div class="num">04</div><h3>Plus de croissance</h3><p>Construisez une marque forte et durable.</p></div>
    </div>

    <div class="sc-block-title">Nos expertises</div>
    <div class="sc-expertise-grid">
      <div class="sc-exp-card">
        <h3>Marketing stratégique</h3>
        <ul>
          <li>Audit marketing</li>
          <li>Étude de marché</li>
          <li>Positionnement</li>
          <li>Plan marketing, business model</li>
        </ul>
      </div>
      <div class="sc-exp-card">
        <h3>Branding & identité</h3>
        <ul>
          <li>Création de marque, naming</li>
          <li>Logo</li>
          <li>Charte graphique</li>
          <li>Identité visuelle</li>
        </ul>
      </div>
      <div class="sc-exp-card">
        <h3>Marketing digital</h3>
        <ul>
          <li>SEO, SEA, Google Ads</li>
          <li>Facebook / LinkedIn Ads</li>
          <li>Email marketing</li>
        </ul>
      </div>
      <div class="sc-exp-card">
        <h3>Communication corporate</h3>
        <ul>
          <li>Communication institutionnelle</li>
          <li>Relations presse</li>
          <li>Communication financière & de crise</li>
        </ul>
      </div>
      <div class="sc-exp-card">
        <h3>Activation de marque</h3>
        <ul>
          <li>Lancement de produit, roadshow</li>
          <li>Évènementiel</li>
          <li>Animation commerciale</li>
        </ul>
      </div>
      <div class="sc-exp-card">
        <h3>Intelligence marketing</h3>
        <ul>
          <li>Data analytics, KPI</li>
          <li>Études concurrentielles</li>
          <li>Veille stratégique, reporting</li>
        </ul>
      </div>
    </div>

    <div class="sc-cta-row">
      <button class="sc-btn sc-btn-primary" data-bs-toggle="modal" data-bs-target="#diagnosticModal">Demander un diagnostic</button>
      <button class="sc-btn sc-btn-ghost" onclick="window.open('https://wa.me/22500000000', '_blank')">Parler à un expert</button>
    </div>
  </section>

  <!-- ================= COMMUNITY ================= -->
  <section id="sc-community">
    <div class="sc-c-head">
      <div class="sc-eyebrow on-dark">Partie 2 · Community management</div>
      <h2>Une présence sociale pensée comme un système.</h2>
      <p>Calendrier éditorial, création de contenus et animation des communautés : chaque publication s'inscrit dans un plan cohérent, réseau par réseau.</p>
    </div>

    <div class="sc-platform-row">
      <div class="sc-platform-chip"><span class="ico" style="background:#1877F2;">f</span><span class="name">Facebook</span><span class="stat">·&nbsp;quotidien</span></div>
      <div class="sc-platform-chip"><span class="ico" style="background:linear-gradient(45deg,#F58529,#DD2A7B,#8134AF);">◎</span><span class="name">Instagram</span><span class="stat">·&nbsp;5/sem</span></div>
      <div class="sc-platform-chip"><span class="ico" style="background:#0A66C2;">in</span><span class="name">LinkedIn</span><span class="stat">·&nbsp;3/sem</span></div>
      <div class="sc-platform-chip"><span class="ico" style="background:#111;">♪</span><span class="name">TikTok</span><span class="stat">·&nbsp;quotidien</span></div>
    </div>

    <div class="sc-comm-layout">
      <div class="sc-pillars">
        <div class="sc-pillar">
          <h3>Calendrier éditorial</h3>
          <p>Une planification hebdomadaire par réseau, alignée sur les temps forts de la marque.</p>
        </div>
        <div class="sc-pillar">
          <h3>Animation des communautés</h3>
          <p>Modération, réponses et engagement continu avec vos audiences.</p>
        </div>
        <div class="sc-pillar">
          <h3>Création de contenus</h3>
          <p>Vidéo, photographie, motion design, copywriting, podcast — produits en interne.</p>
        </div>
      </div>

      <div class="sc-calendar-card">
        <div class="cal-head">
          <h3>Calendrier éditorial — semaine type</h3>
          <span class="sc-mono text-muted">S. 32</span>
        </div>
        <div class="sc-cal-grid">
          <div class="sc-cal-day-label">Lun</div>
          <div class="sc-cal-day-label">Mar</div>
          <div class="sc-cal-day-label">Mer</div>
          <div class="sc-cal-day-label">Jeu</div>
          <div class="sc-cal-day-label">Ven</div>
          <div class="sc-cal-day-label">Sam</div>
          <div class="sc-cal-day-label">Dim</div>

          <div class="sc-cal-cell"><span class="d">03</span><span class="sc-post-dot" style="background:#111;"></span><span class="sc-post-dot" style="background:#1877F2;"></span></div>
          <div class="sc-cal-cell"><span class="d">04</span><span class="sc-post-dot" style="background:#DD2A7B;"></span></div>
          <div class="sc-cal-cell"><span class="d">05</span><span class="sc-post-dot" style="background:#111;"></span><span class="sc-post-dot" style="background:#0A66C2;"></span></div>
          <div class="sc-cal-cell"><span class="d">06</span><span class="sc-post-dot" style="background:#1877F2;"></span><span class="sc-post-dot" style="background:#DD2A7B;"></span></div>
          <div class="sc-cal-cell"><span class="d">07</span><span class="sc-post-dot" style="background:#111;"></span></div>
          <div class="sc-cal-cell"><span class="d">08</span><span class="sc-post-dot" style="background:#DD2A7B;"></span><span class="sc-post-dot" style="background:#111;"></span></div>
          <div class="sc-cal-cell"><span class="d">09</span><span class="sc-post-dot" style="background:#0A66C2;"></span></div>
        </div>
        <div class="sc-legend">
          <span><span class="sw" style="background:#1877F2;"></span>Facebook</span>
          <span><span class="sw" style="background:#DD2A7B;"></span>Instagram</span>
          <span><span class="sw" style="background:#0A66C2;"></span>LinkedIn</span>
          <span><span class="sw" style="background:#111;"></span>TikTok</span>
        </div>
      </div>
    </div>
  </section>

  <!-- ================= SERVICE CLIENT ================= -->
  <section id="sc-service">
    <div class="sc-s-head">
      <div class="sc-eyebrow">Partie 3 · Service client</div>
      <h2>Parce qu'une bonne stratégie mérite un excellent accompagnement.</h2>
      <p>Chez SITIAME CAPITAL, la qualité de la relation client est aussi importante que la qualité de nos prestations : un accompagnement personnalisé avant, pendant et après chaque mission.</p>
    </div>

    <div class="sc-eng-grid">
      <div class="sc-eng-card"><div class="sc-eng-ico">◐</div><div><h3>Conseiller dédié</h3><p>Un interlocuteur unique pour un suivi personnalisé.</p></div></div>
      <div class="sc-eng-card"><div class="sc-eng-ico">◷</div><div><h3>Réactivité</h3><p>Réponse à vos demandes sous 24 à 48 heures.</p></div></div>
      <div class="sc-eng-card"><div class="sc-eng-ico">✉</div><div><h3>Support multicanal</h3><p>Téléphone, e-mail, WhatsApp, visioconférence.</p></div></div>
      <div class="sc-eng-card"><div class="sc-eng-ico">▤</div><div><h3>Suivi régulier</h3><p>Points d'étape et rapports d'avancement.</p></div></div>
      <div class="sc-eng-card"><div class="sc-eng-ico">◈</div><div><h3>Confidentialité</h3><p>Protection et sécurisation de vos données.</p></div></div>
      <div class="sc-eng-card"><div class="sc-eng-ico">↻</div><div><h3>Amélioration continue</h3><p>Chaque retour client améliore nos services.</p></div></div>
    </div>

    <div class="sc-block-title">Espace client</div>
    <div class="sc-space-layout">
      <div class="sc-space-mock">
        <div class="row">
          <div class="ico">▦</div>
          <div><strong>Espace client</strong><br><span style="font-size:11.5px;color:#9FB0C8;">Connectez-vous à votre espace sécurisé</span></div>
        </div>
        <div class="sc-field">Identifiant</div>
        <div class="sc-field">Mot de passe</div>
        <div class="accede" onclick="window.location.href='{{ route('login') }}'">Accéder à mon espace</div>
      </div>
      <ul class="sc-cap-list">
        <li><span class="chk">✓</span> Suivre vos projets</li>
        <li><span class="chk">✓</span> Télécharger vos rapports</li>
        <li><span class="chk">✓</span> Accéder à vos documents</li>
        <li><span class="chk">✓</span> Communiquer avec votre consultant</li>
        <li><span class="chk">✓</span> Planifier un rendez-vous</li>
        <li><span class="chk">✓</span> Consulter l'historique de vos demandes</li>
      </ul>
    </div>

    <div class="sc-block-title">Besoin d'une assistance ?</div>
    <div class="sc-assist-row">
      <div class="sc-assist-card" onclick="window.location.href='tel:+22500000000'"><div class="sc-assist-ico">☎</div><h3>Appelez-nous</h3></div>
      <div class="sc-assist-card" onclick="window.location.href='mailto:contact@sitiame-capital.com'"><div class="sc-assist-ico">✉</div><h3>Envoyez un e-mail</h3></div>
      <div class="sc-assist-card" onclick="window.open('https://wa.me/22500000000', '_blank')"><div class="sc-assist-ico">◑</div><h3>WhatsApp</h3></div>
      <div class="sc-assist-card" data-bs-toggle="modal" data-bs-target="#diagnosticModal"><div class="sc-assist-ico">▤</div><h3>Prendre rendez-vous</h3></div>
    </div>

    <div class="sc-sat-crm-layout">
      <div class="sc-sat-card">
        <h3>Votre avis compte</h3>
        <div class="sc-stars" id="sc-stars">★★★★★</div>
        <p style="color:#B7C3D6; font-size:13px; margin-bottom:14px;">Comment évaluez-vous notre accompagnement ?</p>
        <div class="sc-sat-opts" id="sc-satopts">
          <div class="sc-sat-opt sel">Très satisfait</div>
          <div class="sc-sat-opt">Satisfait</div>
          <div class="sc-sat-opt">Moyen</div>
          <div class="sc-sat-opt">Peu satisfait</div>
        </div>
        <button type="button" class="sc-sat-send" onclick="alert('Merci pour votre évaluation ! Notre équipe en prend note.')">Envoyer mon avis</button>
      </div>

      <div class="sc-crm-card">
        <h3>CRM & fidélisation</h3>
        <div class="sc-crm-tags">
          <div class="sc-crm-tag">Gestion de la relation client</div>
          <div class="sc-crm-tag">Suivi personnalisé</div>
          <div class="sc-crm-tag">Newsletter</div>
          <div class="sc-crm-tag">Alertes personnalisées</div>
          <div class="sc-crm-tag">Programme de fidélisation</div>
          <div class="sc-crm-tag">Enquêtes de satisfaction</div>
          <div class="sc-crm-tag">Tableau de bord des performances</div>
          <div class="sc-crm-tag">Historique des échanges</div>
          <div class="sc-crm-tag">Recommandations personnalisées</div>
        </div>
      </div>
    </div>

    <div class="sc-block-title">Centre d'aide</div>
    <div class="sc-faq-wrap" id="sc-faq">
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Comment suivre mon dossier ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Depuis votre espace client, la section « Projets » affiche l'avancement en temps réel de votre dossier.</div>
      </div>
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Comment modifier un rendez-vous ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Votre conseiller dédié peut reprogrammer un rendez-vous directement depuis l'espace client ou par téléphone.</div>
      </div>
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Comment déposer une réclamation ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Un formulaire dédié est disponible dans le centre d'aide, avec réponse garantie sous 24 à 48 heures.</div>
      </div>
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Quels sont vos délais de réponse ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Nous nous engageons à répondre à toute demande sous 24 à 48 heures ouvrées.</div>
      </div>
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Où télécharger mes documents ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Tous vos documents et rapports sont centralisés dans votre espace client sécurisé.</div>
      </div>
      <div class="sc-faq-item">
        <div class="sc-faq-q"><span>Comment contacter mon conseiller ?</span><span class="plus">+</span></div>
        <div class="sc-faq-a">Par téléphone, e-mail, WhatsApp ou visioconférence — au choix, via votre espace client.</div>
      </div>
    </div>
  </section>

</div>

<!-- Diagnostic Modal -->
<div class="modal fade" id="diagnosticModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">Demander un Diagnostic Marketing</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form onsubmit="event.preventDefault(); alert('Votre demande de diagnostic a été enregistrée avec succès. Un expert SITIAME CAPITAL vous recontactera sous 24h.'); bootstrap.Modal.getInstance(document.getElementById('diagnosticModal')).hide();">
        <div class="modal-body py-4">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nom & Prénom</label>
            <input type="text" class="form-control rounded-3" required placeholder="ex: Marc Kouassi">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Adresse Email pro</label>
            <input type="email" class="form-control rounded-3" required placeholder="ex: contact@societe.ci">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Téléphone / WhatsApp</label>
            <input type="text" class="form-control rounded-3" required placeholder="ex: +225 07000000">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Besoin principal</label>
            <select class="form-select rounded-3">
              <option>Marketing Stratégique & Audit</option>
              <option>Branding & Identité Visuelle</option>
              <option>Marketing Digital (Ads, SEO)</option>
              <option>Community Management</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Annuler</button>
          <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" style="background:var(--emerald); border-color:var(--emerald);">Envoyer la demande</button>
        </div>
      </form>
    </div>
  </div>
</div>

@push('scripts')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Scrollspy for tabnav buttons
    const tabs = document.querySelectorAll('#sc-tabnav button');
    const sections = ['sc-marketing','sc-community','sc-service'].map(id => document.getElementById(id));
    
    tabs.forEach(t => t.addEventListener('click', () => {
      const targetEl = document.getElementById(t.dataset.target);
      if(targetEl) {
        targetEl.scrollIntoView({behavior: 'smooth'});
        tabs.forEach(tab => tab.classList.remove('active'));
        t.classList.add('active');
      }
    }));

    // Interactive Star Rating
    const starsEl = document.getElementById('sc-stars');
    if(starsEl) {
      starsEl.addEventListener('click', (e) => {
        const rect = starsEl.getBoundingClientRect();
        const pct = (e.clientX - rect.left) / rect.width;
        const n = Math.max(1, Math.ceil(pct * 5));
        starsEl.innerHTML = '★★★★★'.split('').map((s,i) => `<span class="${i < n ? 'active':''}">★</span>`).join('');
      });
    }

    // Satisfaction selector
    document.querySelectorAll('.sc-sat-opt').forEach(opt => {
      opt.addEventListener('click', () => {
        document.querySelectorAll('.sc-sat-opt').forEach(o => o.classList.remove('sel'));
        opt.classList.add('sel');
      });
    });

    // FAQ Accordion
    document.querySelectorAll('.sc-faq-item').forEach(item => {
      const q = item.querySelector('.sc-faq-q');
      if(q) {
        q.addEventListener('click', () => {
          const wasOpen = item.classList.contains('open');
          document.querySelectorAll('.sc-faq-item').forEach(i => i.classList.remove('open'));
          if(!wasOpen) item.classList.add('open');
        });
      }
    });
  });
</script>
@endpush
@endsection
