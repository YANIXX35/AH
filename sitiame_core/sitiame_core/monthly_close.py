"""
Rapport et alerte de clôture mensuelle Sitiame Core.
Appelé le 1er de chaque mois à 06:00 via hooks.py → scheduler_events.
"""

import frappe
from frappe.utils import today, getdate, add_months, flt, fmt_money
from frappe import _


def run_monthly_task():
    """
    Le 1er de chaque mois : envoie à chaque société un rapport récapitulatif
    du mois précédent (CA, achats, résultat net, trésorerie).
    """
    aujourd_hui = getdate(today())
    # Mois précédent
    debut_mois_courant = aujourd_hui.replace(day=1)
    fin_mois_precedent = getdate(add_months(str(debut_mois_courant), -1).replace(day=1))
    # premier jour du mois précédent
    debut_mois_precedent = fin_mois_precedent.replace(day=1)
    # dernier jour du mois précédent
    import calendar
    _, dernier_jour = calendar.monthrange(fin_mois_precedent.year, fin_mois_precedent.month)
    fin_mois_precedent = fin_mois_precedent.replace(day=dernier_jour)

    mois_libelle = debut_mois_precedent.strftime("%B %Y")

    societes = frappe.get_all("Company", fields=["name"])

    for societe in societes:
        company = societe.name
        try:
            _envoyer_rapport_mensuel(company, debut_mois_precedent, fin_mois_precedent, mois_libelle)
        except Exception:
            frappe.log_error(frappe.get_traceback(), f"monthly_close: {company}")


def _envoyer_rapport_mensuel(company, date_debut, date_fin, mois_libelle):
    """Calcule les indicateurs du mois et envoie le rapport par email."""
    from sitiame_core.tasks import _get_company_managers

    destinataires = _get_company_managers(company)
    if not destinataires:
        return

    date_debut_str = str(date_debut)
    date_fin_str = str(date_fin)

    # Chiffre d'affaires (compte 70x)
    ca = _solde_comptes(company, "7%", date_debut_str, date_fin_str, sens="credit")

    # Total achats (compte 60x)
    achats = _solde_comptes(company, "6%", date_debut_str, date_fin_str, sens="debit")

    # Résultat approximatif
    resultat = ca - achats

    # Trésorerie : solde compte 57x à la date de fin
    tresorerie = _solde_comptes_bilan(company, "57%", date_fin_str)

    # Factures clients impayées à ce jour
    nb_impayees, total_impaye = _factures_impayees(company)

    couleur_resultat = "#28a745" if resultat >= 0 else "#dc3545"
    signe = "+" if resultat >= 0 else ""

    message = f"""
    <h2 style="color:#1a4a7a">Rapport mensuel — {company}</h2>
    <h3 style="color:#555">Période : {mois_libelle}</h3>

    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:20px">
      <tr style="background:#e8f0fe">
        <td style="padding:10px;border:1px solid #ccc"><strong>📈 Chiffre d'affaires</strong></td>
        <td style="padding:10px;border:1px solid #ccc;text-align:right">
          <strong>{fmt_money(ca, currency='XOF')}</strong>
        </td>
      </tr>
      <tr>
        <td style="padding:10px;border:1px solid #ccc">🛒 Total achats & charges</td>
        <td style="padding:10px;border:1px solid #ccc;text-align:right">
          {fmt_money(achats, currency='XOF')}
        </td>
      </tr>
      <tr style="background:#f8f9fa">
        <td style="padding:10px;border:1px solid #ccc"><strong>💹 Résultat net estimé</strong></td>
        <td style="padding:10px;border:1px solid #ccc;text-align:right;color:{couleur_resultat}">
          <strong>{signe}{fmt_money(resultat, currency='XOF')}</strong>
        </td>
      </tr>
      <tr>
        <td style="padding:10px;border:1px solid #ccc">💰 Trésorerie (solde caisse/banque)</td>
        <td style="padding:10px;border:1px solid #ccc;text-align:right">
          {fmt_money(tresorerie, currency='XOF')}
        </td>
      </tr>
      <tr style="background:#fff3cd">
        <td style="padding:10px;border:1px solid #ccc">⚠️ Créances clients impayées</td>
        <td style="padding:10px;border:1px solid #ccc;text-align:right">
          {nb_impayees} facture(s) · {fmt_money(total_impaye, currency='XOF')}
        </td>
      </tr>
    </table>

    <p style="font-size:12px;color:#888;border-top:1px solid #eee;padding-top:8px">
      Sitiame Capital · Rapport automatique généré le {today()}<br>
      Ces chiffres sont indicatifs. Pour la liasse officielle, consultez votre expert-comptable.
    </p>
    """

    frappe.sendmail(
        recipients=destinataires,
        subject=f"[{company}] Rapport mensuel {mois_libelle}",
        message=message,
    )
    frappe.logger("monthly_close").info(f"Rapport {mois_libelle} envoyé → {company}")


def _solde_comptes(company, account_pattern, date_debut, date_fin, sens="credit"):
    """Somme des mouvements débit ou crédit sur les comptes correspondant au pattern, sur la période."""
    champ = "credit" if sens == "credit" else "debit"
    result = frappe.db.sql(
        f"""
        SELECT COALESCE(SUM(gle.{champ}), 0) AS total
        FROM `tabGL Entry` gle
        INNER JOIN `tabAccount` a ON a.name = gle.account
        WHERE gle.company = %(company)s
          AND gle.posting_date BETWEEN %(debut)s AND %(fin)s
          AND gle.is_cancelled = 0
          AND a.account_number LIKE %(pattern)s
        """,
        {"company": company, "debut": date_debut, "fin": date_fin, "pattern": account_pattern},
    )
    return flt(result[0][0]) if result else 0.0


def _solde_comptes_bilan(company, account_pattern, date_fin):
    """Solde cumulé (débit - crédit) d'un compte de bilan à une date donnée."""
    result = frappe.db.sql(
        """
        SELECT COALESCE(SUM(gle.debit - gle.credit), 0) AS solde
        FROM `tabGL Entry` gle
        INNER JOIN `tabAccount` a ON a.name = gle.account
        WHERE gle.company = %(company)s
          AND gle.posting_date <= %(fin)s
          AND gle.is_cancelled = 0
          AND a.account_number LIKE %(pattern)s
        """,
        {"company": company, "fin": date_fin, "pattern": account_pattern},
    )
    return flt(result[0][0]) if result else 0.0


def _factures_impayees(company):
    """Retourne (nombre, montant total) des factures clients impayées."""
    result = frappe.db.sql(
        """
        SELECT COUNT(*) AS nb, COALESCE(SUM(outstanding_amount), 0) AS total
        FROM `tabSales Invoice`
        WHERE company = %(company)s
          AND docstatus = 1
          AND outstanding_amount > 0
        """,
        {"company": company},
    )
    if result:
        return cint(result[0][0]), flt(result[0][1])
    return 0, 0.0
