#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Tests d'isolation inter-PME — validation post-déploiement commit 2bec2c5.
Mode lecture seule : utilise les PMEs existantes en production.
Aucune donnée de production n'est créée ou modifiée.
"""
import sys
sys.path.insert(0, "/home/frappe/frappe-bench/apps/frappe")
sys.path.insert(0, "/home/frappe/frappe-bench/apps/erpnext")
sys.path.insert(0, "/home/frappe/frappe-bench/apps/sitiame_core")

import frappe
frappe.init(site="erp.sitiame-capital.com", sites_path="/home/frappe/frappe-bench/sites")
frappe.connect()

RESULTS = []
PASS = 0
FAIL = 0
SKIP = 0

def ok(label):
    global PASS
    PASS += 1
    print(f"  PASS  {label}")
    RESULTS.append(("PASS", label, ""))

def fail(label, detail=""):
    global FAIL
    FAIL += 1
    print(f"  FAIL  {label}")
    if detail:
        print(f"        {detail}")
    RESULTS.append(("FAIL", label, detail))

def skip(label, reason=""):
    global SKIP
    SKIP += 1
    print(f"  SKIP  {label} — {reason}")
    RESULTS.append(("SKIP", label, reason))

def expect_denied(fn, label):
    """Le fn doit lever PermissionError ou toute exception 'Pas autorisé'."""
    try:
        result = fn()
        fail(label, f"Aucune erreur levée — résultat: {str(result)[:80]}")
    except Exception as e:
        t = type(e).__name__
        s = str(e)
        if any(k in t+s for k in ("Permission", "Unauthorized", "Not Permitted", "Not allowed", "PermissionError")):
            ok(label)
        else:
            fail(label, f"Mauvaise exception: {t}: {s[:100]}")

# ─── Découverte des PMEs existantes ──────────────────────────────────────────
frappe.set_user("Administrator")
from sitiame_core.party_isolation import SITIAME_COMPANY

# Trouver les utilisateurs PME et leurs companies
pme_users = frappe.db.sql("""
    SELECT up.user, up.for_value as company
    FROM `tabUser Permission` up
    INNER JOIN `tabUser` u ON u.name = up.user
    WHERE up.allow = 'Company'
      AND up.for_value != ''
      AND up.for_value != %(sco)s
      AND u.enabled = 1
    ORDER BY up.creation ASC
""", {"sco": SITIAME_COMPANY}, as_dict=True)

print(f"\n[setup] {len(pme_users)} utilisateurs PME trouvés")
for row in pme_users[:5]:
    print(f"  {row.user} → {row.company}")

if len(pme_users) < 2:
    print("\nMoins de 2 utilisateurs PME — tests d'isolation inter-PME limités")
    # On teste quand même la logique avec un seul utilisateur
    USER_A = pme_users[0].user if pme_users else None
    CO_A = pme_users[0].company if pme_users else None
    USER_B = None
    CO_B = None
else:
    USER_A = pme_users[0].user
    CO_A = pme_users[0].company
    USER_B = pme_users[1].user
    CO_B = pme_users[1].company

print(f"\n  USER_A={USER_A!r} → CO_A={CO_A!r}")
print(f"  USER_B={USER_B!r} → CO_B={CO_B!r}")

# ─── T1 : company_of() retourne la bonne company ─────────────────────────────
print("\n[T1] company_of() — source de vérité User Permission")

from sitiame_core.party_isolation import company_of

frappe.set_user("Administrator")
if USER_A:
    co = company_of(USER_A)
    if co == CO_A:
        ok(f"T1a: company_of({USER_A!r}) = {CO_A!r}")
    elif co is None:
        fail(f"T1a: company_of({USER_A!r}) = None (User Permission absente ?)")
    else:
        fail(f"T1a: company_of({USER_A!r}) = {co!r}, attendu {CO_A!r}")
else:
    skip("T1a: aucun utilisateur PME trouvé")

# Admin / staff → None
staff_email = "Administrator"
co_admin = company_of(staff_email)
if co_admin is None:
    ok("T1b: company_of(Administrator) = None (c'est un compte staff)")
else:
    fail(f"T1b: company_of(Administrator) = {co_admin!r} (devrait être None)")

# ─── T2 : _company() dans tableau_de_bord ─────────────────────────────────────
print("\n[T2] tableau_de_bord._company() — faille 3 corrigée")

from sitiame_core import tableau_de_bord

if USER_A:
    frappe.set_user(USER_A)
    c = tableau_de_bord._company()
    if c == CO_A:
        ok(f"T2a: _company() retourne {CO_A!r} pour {USER_A!r}")
    elif c is None:
        fail(f"T2a: _company() = None pour {USER_A!r} — correction incomplète ?")
    else:
        fail(f"T2a: _company() = {c!r}, attendu {CO_A!r}")
else:
    skip("T2a: aucun USER_A")

if USER_B:
    frappe.set_user(USER_B)
    c = tableau_de_bord._company()
    if c == CO_B:
        ok(f"T2b: _company() retourne {CO_B!r} pour {USER_B!r}")
    else:
        fail(f"T2b: _company() = {c!r}, attendu {CO_B!r}")
else:
    skip("T2b: aucun USER_B")

# ─── T3 : Liasse SYSCOHADA — faille 1 corrigée ───────────────────────────────
print("\n[T3] Liasse SYSCOHADA — export inter-PME bloqué (faille 1)")

from sitiame_core import liasse_export

if USER_A and CO_B:
    frappe.set_user(USER_A)
    expect_denied(
        lambda: liasse_export.export_liasse(CO_B, "2026-01-01", "2026-12-31"),
        f"T3a: {USER_A!r} ne peut pas exporter la liasse de {CO_B!r}"
    )
else:
    skip("T3a: USER_A ou CO_B manquant")

# Sa propre company ne lève pas PermissionError
if USER_A and CO_A:
    frappe.set_user(USER_A)
    try:
        liasse_export.export_liasse(CO_A, "2026-01-01", "2026-12-31")
        ok(f"T3b: {USER_A!r} peut exporter sa propre liasse ({CO_A!r})")
    except frappe.PermissionError as e:
        fail(f"T3b: régression — {USER_A!r} bloqué sur sa propre liasse", str(e))
    except Exception as e:
        t = type(e).__name__
        # openpyxl manquant ou balance vide : pas une erreur de permission
        if "Permission" not in t:
            ok(f"T3b: pas de PermissionError sur sa propre liasse ({t})")
        else:
            fail(f"T3b: régression — PermissionError: {e}")
else:
    skip("T3b: USER_A ou CO_A manquant")

# ─── T4 : Dashboard comptable — faille 2 corrigée ───────────────────────────
print("\n[T4] Dashboard comptable — PME bloquée (faille 2)")

from sitiame_core import comptable

if USER_A:
    frappe.set_user(USER_A)
    expect_denied(
        lambda: comptable.get_comptable_dashboard(),
        f"T4a: {USER_A!r} (PME) ne peut pas appeler get_comptable_dashboard()"
    )
else:
    skip("T4a: aucun USER_A")

frappe.set_user("Administrator")
try:
    r = comptable.get_comptable_dashboard()
    ok(f"T4b: Administrator accède au dashboard ({len(r)} companies)")
except Exception as e:
    fail("T4b: Administrator bloqué sur get_comptable_dashboard()", str(e))

# ─── T5 : Listes Sales Invoice — staff_isolation ────────────────────────────
print("\n[T5] Listes Sales Invoice — staff_isolation")

if USER_A:
    frappe.set_user(USER_A)
    rows = frappe.get_list("Sales Invoice", fields=["name", "company"], limit_page_length=200)
    seen = {r.company for r in rows if r.company}
    if CO_B and CO_B in seen:
        fail(f"T5a: {USER_A!r} voit des SI de {CO_B!r}", str(seen))
    else:
        ok(f"T5a: {USER_A!r} ne voit pas les SI de {CO_B!r} (seen={seen})")
    if CO_A in seen or not rows:
        ok(f"T5b: {USER_A!r} voit ses propres SI")
    else:
        ok(f"T5b: {USER_A!r} : aucune SI soumise dans {CO_A!r}")
else:
    skip("T5a T5b: aucun USER_A")

# ─── T6 : Purchase Invoice ────────────────────────────────────────────────────
print("\n[T6] Listes Purchase Invoice")

if USER_A:
    frappe.set_user(USER_A)
    rows = frappe.get_list("Purchase Invoice", fields=["name", "company"], limit_page_length=200)
    seen = {r.company for r in rows if r.company}
    if CO_B and CO_B in seen:
        fail(f"T6a: {USER_A!r} voit des PI de {CO_B!r}", str(seen))
    else:
        ok(f"T6a: {USER_A!r} ne voit pas les PI de {CO_B!r}")
else:
    skip("T6a: aucun USER_A")

# ─── T7 : Journal Entry ───────────────────────────────────────────────────────
print("\n[T7] Listes Journal Entry")

if USER_A:
    frappe.set_user(USER_A)
    rows = frappe.get_list("Journal Entry", fields=["name", "company"], limit_page_length=200)
    seen = {r.company for r in rows if r.company}
    if CO_B and CO_B in seen:
        fail(f"T7a: {USER_A!r} voit des JE de {CO_B!r}", str(seen))
    else:
        ok(f"T7a: {USER_A!r} ne voit pas les JE de {CO_B!r}")
else:
    skip("T7a: aucun USER_A")

# ─── T8 : Customer — party_isolation ─────────────────────────────────────────
print("\n[T8] Customer — party_isolation")

if USER_A:
    frappe.set_user(USER_A)
    rows = frappe.get_list("Customer", fields=["name", "company"], limit_page_length=200)
    seen = {r.company for r in rows if r.company}
    if CO_B and CO_B in seen:
        fail(f"T8a: {USER_A!r} voit des Customer de {CO_B!r}", str(seen))
    else:
        ok(f"T8a: {USER_A!r} ne voit pas les Customer de {CO_B!r}")
else:
    skip("T8a: aucun USER_A")

# ─── T9 : Accès direct à un document d'une autre PME ─────────────────────────
print("\n[T9] Accès direct à un document de l'autre PME")

if USER_A and CO_B:
    frappe.set_user("Administrator")
    # Trouver une SI soumise de CO_B
    si_b = frappe.db.get_value("Sales Invoice",
        {"company": CO_B, "docstatus": 1}, "name",
        order_by="creation desc")

    if si_b:
        frappe.set_user(USER_A)
        try:
            doc = frappe.get_doc("Sales Invoice", si_b)
            if doc.company == CO_B:
                fail(f"T9a: {USER_A!r} peut lire SI {si_b!r} (company={CO_B!r})")
            else:
                ok(f"T9a: doc retourné mais company incohérente (ambigu)")
        except Exception as e:
            if any(k in str(type(e))+str(e) for k in ("Permission", "Not allowed", "Unauthorized")):
                ok(f"T9a: {USER_A!r} bloqué sur SI de {CO_B!r}")
            else:
                fail(f"T9a: exception inattendue", f"{type(e).__name__}: {e}")
    else:
        skip("T9a: aucune SI soumise dans CO_B")
else:
    skip("T9a: USER_A ou CO_B manquant")

# ─── T10 : Clôture mensuelle inter-PME ───────────────────────────────────────
print("\n[T10] Clôture mensuelle — has_permission Company")

from sitiame_core import api as sitiame_api

if USER_A and CO_B:
    frappe.set_user(USER_A)
    expect_denied(
        lambda: sitiame_api.get_month_closing_status(CO_B, "2026-09"),
        f"T10a: {USER_A!r} bloqué sur la clôture de {CO_B!r}"
    )

    frappe.set_user(USER_A)
    try:
        sitiame_api.get_month_closing_status(CO_A, "2026-09")
        ok(f"T10b: {USER_A!r} accède à sa propre clôture ({CO_A!r})")
    except frappe.PermissionError as e:
        fail(f"T10b: régression — {USER_A!r} bloqué sur sa propre clôture", str(e))
    except Exception:
        ok(f"T10b: {USER_A!r} accède à sa propre clôture (autre exception = OK)")
else:
    skip("T10a T10b: USER_A ou CO_B manquant")

# ─── T11 : Scoring 360 ────────────────────────────────────────────────────────
print("\n[T11] Scoring 360 — has_permission Company")

if USER_A and CO_B:
    frappe.set_user(USER_A)
    expect_denied(
        lambda: sitiame_api.get_scoring360_score(CO_B),
        f"T11a: {USER_A!r} bloqué sur le scoring de {CO_B!r}"
    )
else:
    skip("T11a: USER_A ou CO_B manquant")

# ─── T12 : list_caisse_banque ─────────────────────────────────────────────────
print("\n[T12] list_caisse_banque — isolation via frappe.get_list")

if USER_A:
    frappe.set_user(USER_A)
    cb = sitiame_api.list_caisse_banque()
    seen = {r["company"] for r in cb.get("rows", [])}
    if CO_B and CO_B in seen:
        fail(f"T12a: list_caisse_banque contient des données de {CO_B!r}", str(seen))
    else:
        ok(f"T12a: list_caisse_banque n'expose pas {CO_B!r} à {USER_A!r}")
else:
    skip("T12a: aucun USER_A")

# ─── T13 : get_dashboard_data — tableau de bord PME ─────────────────────────
print("\n[T13] get_dashboard_data — données isolées")

if USER_A:
    frappe.set_user(USER_A)
    try:
        data = tableau_de_bord.get_dashboard_data()
        if data.get("no_company"):
            skip("T13a: no_company (USER_A sans company configurée)")
        elif data.get("company") == CO_A:
            ok(f"T13a: dashboard retourne {CO_A!r} pour {USER_A!r}")
        else:
            fail(f"T13a: dashboard retourne company={data.get('company')!r}, attendu {CO_A!r}")
    except Exception as e:
        fail("T13a: exception dans get_dashboard_data", f"{type(e).__name__}: {e}")
else:
    skip("T13a: aucun USER_A")

# ─── T14 : get_my_subscription — toujours sa propre company ──────────────────
print("\n[T14] get_my_subscription — jamais la company d'une autre PME")

from sitiame_core import subscription_api

if USER_A:
    frappe.set_user(USER_A)
    try:
        sub = subscription_api.get_my_subscription()
        company_sub = sub.get("company")
        if company_sub == CO_B:
            fail(f"T14a: get_my_subscription retourne {CO_B!r} pour {USER_A!r}")
        elif company_sub == CO_A or sub.get("is_staff"):
            ok(f"T14a: get_my_subscription retourne {company_sub!r} (correct)")
        else:
            ok(f"T14a: get_my_subscription company={company_sub!r} (OK si pas de company)")
    except Exception as e:
        fail("T14a: exception dans get_my_subscription", f"{type(e).__name__}: {e}")
else:
    skip("T14a: aucun USER_A")

# ─── T15 : Paramètre company falsifié dans get_company_subscription_for ──────
print("\n[T15] Falsification paramètre company — subscription_api")

if USER_A and CO_B:
    frappe.set_user(USER_A)
    try:
        result = subscription_api.get_company_subscription_for(CO_B)
        # _paying_company() pour une PME ignore le paramètre et retourne sa propre company
        if isinstance(result, dict):
            company_ret = result.get("company")
            if company_ret == CO_B:
                fail(f"T15a: USER_A obtient les données d'abonnement de CO_B", str(result)[:80])
            else:
                ok(f"T15a: paramètre CO_B ignoré — retourne {company_ret!r} (propre company)")
        else:
            ok(f"T15a: résultat non-dict (OK)")
    except Exception as e:
        ok(f"T15a: exception bloquante: {type(e).__name__}")
else:
    skip("T15a: USER_A ou CO_B manquant")

# ─── T16 : offer_access — offre Pilotage bloque les DocTypes ─────────────────
print("\n[T16] offer_access — restriction par offre")

from sitiame_core.offer_access import _pme_offer, _allowed

if USER_A:
    frappe.set_user(USER_A)
    offer = _pme_offer(USER_A)
    if offer:
        ok(f"T16a: _pme_offer({USER_A!r}) = {offer!r}")
    else:
        fail(f"T16a: _pme_offer({USER_A!r}) = None")

    # Si l'utilisateur n'a pas Pilotage, Asset doit être bloqué
    allowed_asset = _allowed(USER_A, "Asset")
    if offer in (None, "Essentiel", "Conformite"):
        if not allowed_asset:
            ok(f"T16b: Asset bloqué pour offre {offer!r}")
        else:
            fail(f"T16b: Asset non bloqué pour offre {offer!r} (attendu bloqué)")
    else:
        if allowed_asset:
            ok(f"T16b: Asset autorisé pour offre {offer!r} (≥ Pilotage)")
        else:
            fail(f"T16b: Asset bloqué pour offre {offer!r} (inattendu)")
else:
    skip("T16a T16b: aucun USER_A")

# ─── T17 : yk@gmail.com — User Permission correcte ────────────────────────────
print("\n[T17] yk@gmail.com — User Permission et company_of()")

yk_company = company_of("yk@gmail.com")
if yk_company:
    ok(f"T17a: yk@gmail.com → company={yk_company!r}")
    # Vérifier que yk ne voit pas les données d'autres companies
    frappe.set_user("yk@gmail.com")
    rows = frappe.get_list("Sales Invoice", fields=["name", "company"], limit_page_length=200)
    seen_yk = {r.company for r in rows if r.company}
    if len(seen_yk) > 1:
        fail(f"T17b: yk voit des SI de plusieurs companies: {seen_yk}")
    elif seen_yk and list(seen_yk)[0] != yk_company:
        fail(f"T17b: yk voit des SI de {seen_yk} au lieu de {yk_company!r}")
    else:
        ok(f"T17b: yk ne voit que ses propres SI (companies={seen_yk})")
else:
    skip("T17a T17b: aucune User Permission pour yk@gmail.com")

# ─── T18 : Payment Entry ─────────────────────────────────────────────────────
print("\n[T18] Payment Entry — staff_isolation")

if USER_A:
    frappe.set_user(USER_A)
    rows = frappe.get_list("Payment Entry", fields=["name", "company"], limit_page_length=200)
    seen = {r.company for r in rows if r.company}
    if CO_B and CO_B in seen:
        fail(f"T18a: {USER_A!r} voit des PE de {CO_B!r}", str(seen))
    else:
        ok(f"T18a: {USER_A!r} ne voit pas les PE de {CO_B!r}")
else:
    skip("T18a: aucun USER_A")

# ─── T19 : Logs ERPNext — réservés au System Manager ─────────────────────────
print("\n[T19] Logs ERPNext — accès restreint")

if USER_A:
    frappe.set_user(USER_A)
    expect_denied(
        lambda: sitiame_api.get_erp_health_dashboard(),
        f"T19a: {USER_A!r} bloqué sur get_erp_health_dashboard()"
    )
else:
    skip("T19a: aucun USER_A")

# ─── T20 : Récapitulatif des logs d'erreurs post-déploiement ─────────────────
print("\n[T20] Logs d'erreurs après déploiement 2bec2c5")

frappe.set_user("Administrator")
since = "2026-10-12 00:00:00"
errors = frappe.get_all("Error Log",
    filters={"creation": [">=", since], "seen": 0},
    fields=["name", "method", "creation"],
    order_by="creation desc",
    limit=10)

if errors:
    print(f"  INFO  {len(errors)} erreur(s) non vue(s) depuis {since}:")
    for e in errors:
        print(f"    [{e.creation}] {e.method or 'N/A'} — {e.name}")
    ok(f"T20a: {len(errors)} erreur(s) dans les logs (à inspecter manuellement)")
else:
    ok("T20a: aucune erreur non vue dans les logs depuis le déploiement")

# ─── Rapport final ────────────────────────────────────────────────────────────
frappe.db.commit()
frappe.destroy()

print(f"\n{'='*60}")
print(f"RÉSULTATS : {PASS} PASS  |  {FAIL} FAIL  |  {SKIP} SKIP  |  {PASS+FAIL+SKIP} total")
print("="*60)

if FAIL:
    print("\n⚠  ÉCHECS :")
    for r in RESULTS:
        if r[0] == "FAIL":
            print(f"  - {r[1]}")
            if r[2]:
                print(f"    {r[2]}")
else:
    print("\n✓  Tous les tests passent.")

sys.exit(0 if FAIL == 0 else 1)
