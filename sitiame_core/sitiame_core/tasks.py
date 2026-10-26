"""
Tâches planifiées Sitiame Core.

Référencées dans hooks.py → scheduler_events.
Chaque fonction est appelée par le scheduler Frappe/ERPNext.
"""

import frappe
from frappe.utils import today, add_days, flt, nowdate, getdate, cint
from frappe import _


# ---------------------------------------------------------------------------
# Sauvegarde
# ---------------------------------------------------------------------------

def run_scheduled_backup():
    """Sauvegarde quotidienne de la base de données (sans fichiers)."""
    from frappe.utils.backups import scheduled_backup
    scheduled_backup(ignore_files=True)


def run_daily_files_backup():
    """Sauvegarde quotidienne des fichiers uploadés (sans DB)."""
    from frappe.utils.backups import scheduled_backup
    scheduled_backup(ignore_db=True)


# ---------------------------------------------------------------------------
# Abonnements
# ---------------------------------------------------------------------------

def block_expired_trials():
    """
    Désactive les comptes PME dont la date de fin d'essai est dépassée.
    Met à jour le champ sitiame_offer → 'Expired' et retire le rôle actif.
    """
    expired = frappe.get_all(
        "Company",
        filters={
            "sitiame_offer": ["not in", ["", "Expired", None]],
            "sitiame_trial_end": ["<", today()],
        },
        fields=["name", "sitiame_offer", "sitiame_trial_end"],
    )
    for company in expired:
        try:
            frappe.db.set_value("Company", company.name, "sitiame_offer", "Expired")
            frappe.logger("tasks").info(
                f"[block_expired_trials] {company.name} → Expired (fin {company.sitiame_trial_end})"
            )
        except Exception:
            frappe.log_error(frappe.get_traceback(), f"block_expired_trials: {company.name}")
    if expired:
        frappe.db.commit()


# ---------------------------------------------------------------------------
# Alertes factures échues
# ---------------------------------------------------------------------------

def alerter_factures_echues():
    """
    Chaque matin : envoie un email récapitulatif aux gérants PME
    ayant des factures clients impayées depuis plus de 30 jours.
    """
    seuil_jours = cint(
        frappe.db.get_single_value("Sitiame Settings", "alerte_facture_jours") or 30
    )
    date_limite = add_days(today(), -seuil_jours)

    # Factures clients en retard, groupées par société
    factures = frappe.db.sql(
        """
        SELECT
            si.company,
            si.name,
            si.customer,
            si.grand_total,
            si.outstanding_amount,
            si.due_date,
            DATEDIFF(CURDATE(), si.due_date) AS retard_jours
        FROM `tabSales Invoice` si
        WHERE si.docstatus = 1
          AND si.outstanding_amount > 0
          AND si.due_date <= %(date_limite)s
        ORDER BY si.company, si.due_date
        """,
        {"date_limite": date_limite},
        as_dict=True,
    )

    if not factures:
        return

    # Grouper par société
    par_societe = {}
    for f in factures:
        par_societe.setdefault(f.company, []).append(f)

    for company, lignes in par_societe.items():
        destinataires = _get_company_managers(company)
        if not destinataires:
            continue

        total_en_retard = sum(flt(l.outstanding_amount) for l in lignes)
        nb = len(lignes)

        rows_html = "".join(
            f"<tr><td>{l.name}</td><td>{l.customer}</td>"
            f"<td style='text-align:right'>{frappe.utils.fmt_money(l.outstanding_amount, currency='XOF')}</td>"
            f"<td>{l.due_date}</td><td style='color:red'>{l.retard_jours} j</td></tr>"
            for l in lignes
        )

        message = f"""
        <h3>⚠️ Factures clients en retard — {company}</h3>
        <p><strong>{nb} facture(s)</strong> non réglée(s) depuis plus de {seuil_jours} jours.<br>
        Montant total en souffrance : <strong>{frappe.utils.fmt_money(total_en_retard, currency='XOF')}</strong></p>
        <table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:#f0f0f0">
              <th>Facture</th><th>Client</th><th>Solde (FCFA)</th>
              <th>Échéance</th><th>Retard</th>
            </tr>
          </thead>
          <tbody>{rows_html}</tbody>
        </table>
        <p style="font-size:12px;color:#888">Sitiame Capital · Rapport automatique du {today()}</p>
        """

        try:
            frappe.sendmail(
                recipients=destinataires,
                subject=f"[{company}] {nb} facture(s) en retard — {today()}",
                message=message,
            )
        except Exception:
            frappe.log_error(frappe.get_traceback(), f"alerter_factures_echues: {company}")


# ---------------------------------------------------------------------------
# Alertes stock bas
# ---------------------------------------------------------------------------

def alerter_stock_bas():
    """
    Chaque matin : envoie un email récapitulatif aux gérants PME
    ayant des articles dont le stock actuel est inférieur au seuil de réapprovisionnement.
    """
    articles = frappe.db.sql(
        """
        SELECT
            b.company,
            b.item_code,
            i.item_name,
            SUM(b.actual_qty) AS stock_actuel,
            i.reorder_level,
            i.reorder_qty
        FROM `tabBin` b
        INNER JOIN `tabItem` i ON i.name = b.item_code
        WHERE i.reorder_level > 0
          AND i.disabled = 0
          AND i.is_stock_item = 1
        GROUP BY b.company, b.item_code
        HAVING SUM(b.actual_qty) < i.reorder_level
        ORDER BY b.company, b.item_code
        """,
        as_dict=True,
    )

    if not articles:
        return

    par_societe = {}
    for a in articles:
        par_societe.setdefault(a.company, []).append(a)

    for company, lignes in par_societe.items():
        destinataires = _get_company_managers(company)
        if not destinataires:
            continue

        rows_html = "".join(
            f"<tr><td>{l.item_code}</td><td>{l.item_name}</td>"
            f"<td style='text-align:right;color:red'>{flt(l.stock_actuel, 2)}</td>"
            f"<td style='text-align:right'>{flt(l.reorder_level, 2)}</td>"
            f"<td style='text-align:right'>{flt(l.reorder_qty, 2)}</td></tr>"
            for l in lignes
        )

        message = f"""
        <h3>📦 Stock bas — {company}</h3>
        <p><strong>{len(lignes)} article(s)</strong> en dessous du seuil de réapprovisionnement.</p>
        <table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:13px">
          <thead>
            <tr style="background:#f0f0f0">
              <th>Code</th><th>Article</th><th>Stock actuel</th>
              <th>Seuil réappro</th><th>Qté à commander</th>
            </tr>
          </thead>
          <tbody>{rows_html}</tbody>
        </table>
        <p style="font-size:12px;color:#888">Sitiame Capital · Rapport automatique du {today()}</p>
        """

        try:
            frappe.sendmail(
                recipients=destinataires,
                subject=f"[{company}] {len(lignes)} article(s) en rupture de stock — {today()}",
                message=message,
            )
        except Exception:
            frappe.log_error(frappe.get_traceback(), f"alerter_stock_bas: {company}")


# ---------------------------------------------------------------------------
# Utilitaires internes
# ---------------------------------------------------------------------------

def _get_company_managers(company):
    """
    Retourne la liste des emails des utilisateurs gérant cette société.
    Cherche : User Permissions Company → user → email.
    """
    users = frappe.db.sql(
        """
        SELECT DISTINCT u.email
        FROM `tabUser Permission` up
        INNER JOIN `tabUser` u ON u.name = up.user
        WHERE up.allow = 'Company'
          AND up.for_value = %(company)s
          AND u.enabled = 1
          AND u.email NOT LIKE '%%@example.com'
        """,
        {"company": company},
        as_dict=True,
    )
    return [u.email for u in users if u.email]
