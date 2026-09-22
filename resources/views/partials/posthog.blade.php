{{-- Analytics produit — PostHog. Inclus à la fois dans layouts/app.blade.php (app connectée)
     et sur les pages autonomes (login, register, accueil, tarifs, à propos, documentation)
     qui n'utilisent pas ce layout, pour couvrir tout le tunnel visiteur → inscription.

     Audit F-36 : PostHog ne doit être chargé qu'après consentement explicite du visiteur.
     Le script n'est initialisé qu'au clic sur "Accepter" du bandeau, ou automatiquement
     si un consentement a déjà été enregistré lors d'une visite précédente. --}}
@if(config('services.posthog.key'))
<div id="cookie-consent-banner" class="fixed inset-x-0 bottom-0 z-[9999] hidden border-t border-slate-800 bg-slate-950/98 px-4 py-4 text-slate-200 shadow-2xl backdrop-blur sm:px-6">
    <div class="mx-auto flex max-w-5xl flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm leading-relaxed text-slate-300">
            Nous utilisons un outil de mesure d'audience (PostHog) pour comprendre l'utilisation de la plateforme et l'améliorer. Ces données ne sont chargées qu'avec votre accord.
            <a href="{{ route('legal.confidentialite') }}" class="font-semibold text-brand-300 underline hover:text-brand-200">En savoir plus</a>
        </p>
        <div class="flex shrink-0 gap-3">
            <button type="button" id="cookie-consent-decline" class="rounded-lg border border-slate-600 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">Refuser</button>
            <button type="button" id="cookie-consent-accept" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-500">Accepter</button>
        </div>
    </div>
</div>

<script>
(function () {
    var CONSENT_KEY = 'ph_consent';

    function getConsent() {
        try {
            return window.localStorage.getItem(CONSENT_KEY);
        } catch (e) {
            return null;
        }
    }

    function setConsent(value) {
        try {
            window.localStorage.setItem(CONSENT_KEY, value);
        } catch (e) {
            /* stockage indisponible (navigation privée, etc.) : le bandeau réapparaîtra */
        }
    }

    function initPostHog() {
        if (window.posthog && window.posthog.__loaded) {
            return;
        }

        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.uploadQueue?t.uploadQueue.push([e].concat(Array.prototype.slice.call(arguments,0))):t.uploadQueue=[[e].concat(Array.prototype.slice.call(arguments,0))]}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="init capture register register_once register_for_session unregister unregister_for_session getFeatureFlag getFeatureFlagPayload isFeatureEnabled reloadFeatureFlags updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures on onFeatureFlags onSessionId getSurveys getActiveMatchingSurveys renderSurvey canRenderSurvey getNextSurveyStep identify setPersonProperties group resetGroups setPersonPropertiesForFlags resetPersonPropertiesForFlags setGroupPropertiesForFlags resetGroupPropertiesForFlags reset get_distinct_id getGroups get_session_id get_session_replay_url alias set_config startSessionRecording stopSessionRecording sessionRecordingStarted captureException loadToolbar get_property getSessionProperty createPersonProfile opt_in_capturing opt_out_capturing has_opted_in_capturing has_opted_out_capturing clear_opt_in_out_capturing debug".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);

        posthog.init('{{ config('services.posthog.key') }}', {
            api_host: '{{ config('services.posthog.host') }}',
            person_profiles: 'identified_only',
            capture_pageview: true,
            capture_pageleave: true,
            session_recording: {
                // Donnees financieres affichees en clair a l'ecran (montants, factures,
                // infos PME clientes) : on masque tout le texte par defaut plutot que
                // de retrofitter une classe de masquage sur chaque vue existante.
                // A assouplir au cas par cas (nav, boutons...) si besoin plus tard.
                maskTextSelector: '*',
                maskAllInputs: true,
            },
        });

        @auth
        posthog.identify('{{ auth()->id() }}', {
            role: '{{ auth()->user()->role_key ?? (auth()->user()->is_platform_admin ? "platform_admin" : (auth()->user()->is_accountant ? "accountant" : "client")) }}',
            is_premium: {{ auth()->user()->is_premium ? 'true' : 'false' }},
        });
        @endauth
    }

    document.addEventListener('DOMContentLoaded', function () {
        var consent = getConsent();
        var banner = document.getElementById('cookie-consent-banner');

        if (consent === 'granted') {
            initPostHog();
            return;
        }

        if (consent === 'denied') {
            return;
        }

        if (banner) {
            banner.classList.remove('hidden');
        }

        var acceptBtn = document.getElementById('cookie-consent-accept');
        var declineBtn = document.getElementById('cookie-consent-decline');

        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                setConsent('granted');
                if (banner) banner.classList.add('hidden');
                initPostHog();
            });
        }

        if (declineBtn) {
            declineBtn.addEventListener('click', function () {
                setConsent('denied');
                if (banner) banner.classList.add('hidden');
            });
        }
    });
})();
</script>
@endif
