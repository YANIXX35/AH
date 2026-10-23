# -*- coding: utf-8 -*-
"""
PME360 — Script 3 : référentiel SYSCOHADA des articles et inventaire permanent
===============================================================================
Version 2.2 — 22 octobre 2026

Remplace la version 1.0 après trois décisions de la Direction :

  1. Les familles d'articles suivent STRICTEMENT le SYSCOHADA. Aucune
     famille inventée : chaque famille porte le numéro et le libellé d'une
     rubrique du plan comptable.
  2. PME360 reste en INVENTAIRE PERMANENT. Le script ne propose plus
     l'alternative intermittente : il configure l'inventaire permanent
     pour qu'il produise des états SYSCOHADA justes.
  3. Le choix du compte d'achat ne se décide pas article par article : il
     découle de la NATURE D'ACTIVITÉ déclarée au registre du commerce,
     saisie à l'ouverture du compte dans PME360.

LE PROFIL D'ACTIVITÉ COMMANDE LE PLAN DE COMPTES
------------------------------------------------
La question « la viande fraîche est-elle du 6011 ou du 6021 ? » n'a pas de
réponse dans l'absolu. Elle en a une dès qu'on connaît l'activité inscrite
au RCCM :

  * « Commerce de produits alimentaires » — l'entreprise revend en l'état.
    Achat 6011 Achats de marchandises, vente 7011, stock 3111.
  * « Restauration, maquis, traiteur » — l'entreprise transforme.
    Achat 6021 Achats de matières premières, vente 7061 Services vendus,
    stock 321, TVA facturée 4432.

Le même article, le même fournisseur, le même prix : deux plans de comptes
différents, parce que deux métiers différents. C'est pourquoi le profil est
porté par la SOCIÉTÉ et non par l'article, et qu'il est saisi une fois, à
l'ouverture du compte, à partir d'une pièce officielle.

LA PLACE DE L'IA EMBARQUÉE
--------------------------
`proposer_profil()` lit le libellé d'activité du RCCM et propose un profil,
avec les termes qui ont déclenché la proposition et un indice de certitude.
Deux implémentations :

  * un rapprochement lexical déterministe, présent dans ce fichier, qui
    fonctionne sans aucune dépendance ;
  * l'IA embarquée de PME360, branchée par `PROPOSITION_IA`, qui lit le
    libellé en langue naturelle et sait traiter les formulations que le
    rapprochement lexical manque.

Dans les deux cas la proposition est ÉCRITE DANS UN CHAMP SÉPARÉ et n'est
jamais appliquée seule. Un humain la valide, et la validation est horodatée
et signée. Une IA qui choisirait seule le plan de comptes d'un client
engagerait la responsabilité de SITIAME sur sa liasse fiscale.

LE SCHÉMA VISÉ, ET COMMENT ERPNEXT LE PRODUIT
---------------------------------------------
En inventaire permanent, ERPNext porte par défaut l'achat directement au
compte de stock : D 31 Marchandises / C 408. Le compte 601 « Achats de
marchandises » n'est jamais touché, donc la ligne RA du compte de résultat
SYSCOHADA reste vide et la marge commerciale n'est pas lisible.

ERPNext 16 sait produire le schéma SYSCOHADA, par un réglage global des
Paramètres comptables nommé `book_stock_expense_gl_entries`. Quand il est
actif, chaque réception de marchandises produit DEUX écritures :

    D 3111 Marchandises          /  C 4081 Fournisseurs, fact. non parvenues
    D 6011 Achats de marchandises /  C 6031 Variations des stocks

C'est exactement la double écriture SYSCOHADA. À la vente, le compte
`default_cogs_account` de la famille produit la sortie D 6031 / C 3111.

ÉTAT CONSTATÉ LE 22 OCTOBRE, ET POURQUOI IL BLOQUE
--------------------------------------------------
`book_stock_expense_gl_entries` est DÉJÀ actif sur le site. Mais les
comptes qu'il exige — `purchase_expense_account` et
`purchase_expense_contra_account` — sont vides dans les DIX sociétés.

Or ces comptes deviennent obligatoires dès que le réglage est actif :
ERPNext refuse alors toute réception de marchandises, toute facture
d'achat avec mise à jour du stock, tout mouvement et tout inventaire de
stock portant un article stockable. C'est, selon toute vraisemblance,
l'explication du fait relevé dans l'audit : zéro bon de réception sur
l'ensemble du site, et « mettre à jour le stock » décoché sur toutes les
factures. Les utilisateurs ont rencontré le mur et l'ont contourné.

Ce script remplit ces comptes. C'est le geste qui débloque l'inventaire
permanent ET qui rend le compte de résultat SYSCOHADA juste.

RÉSERVE DE VÉRIFICATION
-----------------------
Le comportement décrit ci-dessus a été établi en lisant le code source
d'ERPNext 16.52.1. L'instance tourne en 16.35.0. Le réglage et les champs
existent bien en 16.35.0 — c'est vérifié — mais le détail du contrôle
peut différer. L'étape `controle_stock` vérifie l'état réel, et la
première réception de marchandises doit être faite en présence d'un
comptable, sur une seule société, avant de généraliser.

CE QUE CE SCRIPT NE FAIT JAMAIS
-------------------------------
  * Il ne supprime aucune ligne de `tabGL Entry`.
  * Il ne modifie, n'annule ni ne valide aucune facture.
  * Il ne touche à aucun fichier de apps/frappe, apps/erpnext, apps/hrms.
  * Il ne reclasse aucun article tant que APPLIQUER_RECLASSEMENT est False.
  * Il ne change la nature d'un compte que si ce compte ne porte aucune
    écriture, dans aucune société.

EMPLACEMENT
-----------
  apps/sitiame_core/sitiame_core/scripts/correctif_affectation_articles.py

UTILISATION
-----------
  # Diagnostic complet, aucune écriture
  bench --site erp.sitiame-capital.com execute \
      sitiame_core.scripts.correctif_affectation_articles.main

  # Socle du stock, société par société
  bench --site erp.sitiame-capital.com execute \
      sitiame_core.scripts.correctif_affectation_articles.main \
      --kwargs "{'apply': True, 'societe': 'SITIAME', \
                 'etapes': ['comptes','typer_stock','entrepots','societe_stock','familles']}"

  # Reclassement : seulement après validation OEC-CI
  bench --site erp.sitiame-capital.com execute \
      sitiame_core.scripts.correctif_affectation_articles.main \
      --kwargs "{'apply': True, 'etapes': ['reclasser','purger_defauts']}"

À AJOUTER DANS hooks.py
-----------------------
    doc_events = {
        "Purchase Invoice": {
            "before_submit": [
                "sitiame_core.scripts.correctif_affectation_articles.bloquer_compte_attente",
                "sitiame_core.scripts.correctif_affectation_articles.exiger_mouvement_stock",
            ],
        },
        "Sales Invoice": {
            "before_submit":
                "sitiame_core.scripts.correctif_affectation_articles.bloquer_compte_attente",
        },
        "Company": {
            "after_insert":
                "sitiame_core.scripts.correctif_affectation_articles.provisionner_profil_societe",
            "on_update":
                "sitiame_core.scripts.correctif_affectation_articles.consigner_validation_profil",
        },
    }

Le script 2 pose déjà un `after_insert` sur Company : réunissez les deux
dans une liste, Frappe les appelle dans l'ordre.

Les deux gardes bloquent la VALIDATION, jamais la saisie. Le brouillon
reste possible ; c'est la comptabilisation qui attend que la pièce soit
juste. C'est le comportement des logiciels du marché.
"""

import json
import os

import frappe
from frappe.utils import nowdate


# =============================================================================
# LE RÉFÉRENTIEL — aucune famille inventée
# =============================================================================
#
# Chaque famille porte le numéro et le libellé d'une rubrique SYSCOHADA.
# Les comptes désignés sont des comptes de DERNIER NIVEAU (is_group = 0) :
# un compte de regroupement ne peut pas recevoir d'écriture, et c'est
# l'erreur qui rendait inopérante la table du script 1.
#
# Le compte d'achat retenu est le plus courant de sa rubrique. Un article
# particulier peut toujours le surcharger — exactement comme une fiche
# article surcharge sa famille dans Sage ou Cegid.
#
#   achat      : compte de charge (601x, 602x, 604x, 605x, 61x, 62x, 63x)
#   vente      : compte de produit (70xx)
#   stock      : compte de stock de classe 3, pour l'inventaire permanent
#   variation  : compte de variation de stocks (6031/6032/6033/736)
#   tva        : titre du modèle de taxe créé par le script 1
#   stockable  : les articles de cette famille transitent-ils par un magasin
#
# Où chaque compte est posé, selon que la famille est stockée ou non :
#
#   Famille STOCKÉE                      Famille NON STOCKÉE
#   ------------------------------------ ------------------------------
#   purchase_expense_account  = achat    expense_account = achat
#   purchase_expense_contra   = variation
#   default_cogs_account      = variation
#   default_inventory_account = stock
#   income_account            = vente    income_account  = vente
#
# Pour un article stockable en inventaire permanent, ERPNext RÉÉCRIT de
# force le champ `expense_account` de la ligne de facture avec le compte
# de magasin ou le compte de réceptions non facturées. Le renseigner sur
# la famille est donc sans effet sur l'écriture ; il n'est conservé que
# comme repli si l'article cessait un jour d'être stockable. Le compte qui
# porte réellement le 601, c'est `purchase_expense_account`.
#
#   profils    : profils d'activité qui utilisent cette famille
#
FAMILLES = {
    # ---- Familles stockées -------------------------------------------------
    "31 - Marchandises": {
        "achat": "6011", "vente": "7011",
        "stock": "3111", "variation": "6031",
        "tva": "TVA 18% Achats", "stockable": True,
        "profils": {"Negoce", "Agriculture"},
        "aide": "Biens achetés pour être revendus en l'état.",
    },
    "32 - Matieres premieres et fournitures liees": {
        "achat": "6021", "vente": None,
        "stock": "321", "variation": "6032",
        "tva": "TVA 18% Achats", "stockable": True,
        "profils": {"Production", "Restauration", "BTP", "Agriculture"},
        "aide": "Biens achetés pour être transformés.",
    },
    "33 - Autres approvisionnements": {
        "achat": "6041", "vente": None,
        "stock": "331", "variation": "6033",
        "tva": "TVA 18% Achats", "stockable": True,
        "profils": {"Negoce", "Production", "Restauration", "BTP",
                    "Services", "Agriculture"},
        "aide": "Matières et fournitures consommables stockées.",
    },
    "335 - Emballages": {
        "achat": "6081", "vente": None,
        "stock": "3351", "variation": "6033",
        "tva": "TVA 18% Achats", "stockable": True,
        "profils": {"Negoce", "Production", "Restauration", "Agriculture"},
        "aide": "Emballages perdus, récupérables ou à usage mixte.",
    },
    "36 - Produits finis": {
        "achat": None, "vente": "7021",
        "stock": "361", "variation": "736",
        "tva": None, "stockable": True,
        "profils": {"Production", "Agriculture"},
        "aide": "Produits issus de la transformation. Non achetés.",
    },
    # ---- Familles non stockées ---------------------------------------------
    "605 - Autres achats": {
        "achat": "6057", "vente": "7061",
        "stock": None, "variation": None,
        "tva": "TVA 18% Achats", "stockable": False,
        "profils": {"Negoce", "Production", "Restauration", "BTP",
                    "Services", "Agriculture"},
        "aide": "Achats non stockés : études, prestations, petit matériel.",
    },
    "61 - Transports": {
        "achat": "6181", "vente": "7061",
        "stock": None, "variation": None,
        "tva": "TVA 18% Transport", "stockable": False,
        "profils": {"Negoce", "Production", "Restauration", "BTP",
                    "Services", "Agriculture"},
        "aide": "Transports sur ventes, du personnel, déplacements.",
    },
    "62 - Services exterieurs A": {
        "achat": "6243", "vente": "7061",
        "stock": None, "variation": None,
        "tva": "TVA 18% Services extérieurs", "stockable": False,
        "profils": {"Negoce", "Production", "Restauration", "BTP",
                    "Services", "Agriculture"},
        "aide": "Sous-traitance, locations, entretien, assurances, publicité.",
    },
    "63 - Services exterieurs B": {
        "achat": "6324", "vente": "7061",
        "stock": None, "variation": None,
        "tva": "TVA 18% Services extérieurs", "stockable": False,
        "profils": {"Negoce", "Production", "Restauration", "BTP",
                    "Services", "Agriculture"},
        "aide": "Honoraires, commissions, formation, redevances.",
    },
}


# =============================================================================
# LES PROFILS D'ACTIVITÉ — la nature d'activité commande le plan de comptes
# =============================================================================
#
# Le profil est déclaré à l'ouverture du compte, à partir du libellé
# d'activité porté au registre du commerce. Il détermine :
#
#   * la famille par défaut des biens achetés (6011 ou 6021 : revente en
#     l'état ou transformation) ;
#   * le compte de vente principal et la TVA facturée correspondante ;
#   * les familles ouvertes à la société, les autres restant inutilisées.
#
#   famille_biens : famille attribuée par défaut à un bien acheté stockable
#   vente         : compte de produit principal de l'activité
#   tva_vente     : compte de TVA facturée SYSCOHADA correspondant
#   indices       : termes du RCCM qui orientent vers ce profil
#
PROFILS = {
    "Negoce": {
        "libelle": "Négoce — achat de biens pour revente en l'état",
        "famille_biens": "31 - Marchandises",
        "vente": "7011", "tva_vente": "4431",
        "indices": ["commerce", "negoce", "achat-revente", "achat et revente",
                    "revente", "distribution", "vente en gros", "demi-gros",
                    "vente au detail", "detail", "import", "export",
                    "alimentation generale", "boutique", "supermarche",
                    "quincaillerie", "librairie", "pharmacie", "droguerie",
                    "concessionnaire", "grossiste", "depot"],
    },
    "Production": {
        "libelle": "Production — transformation de matières en produits finis",
        "famille_biens": "32 - Matieres premieres et fournitures liees",
        "vente": "7021", "tva_vente": "4431",
        "indices": ["production", "fabrication", "fabrique", "transformation",
                    "industrie", "industriel", "usine", "manufacture",
                    "agroalimentaire", "minoterie", "huilerie", "savonnerie",
                    "menuiserie", "ebenisterie", "metallurgie", "confection",
                    "couture", "imprimerie", "serigraphie", "conditionnement"],
    },
    "Restauration": {
        "libelle": "Restauration — transformation de denrées et vente de repas",
        "famille_biens": "32 - Matieres premieres et fournitures liees",
        "vente": "7061", "tva_vente": "4432",
        "indices": ["restauration", "restaurant", "maquis", "traiteur",
                    "cantine", "snack", "fast-food", "boulangerie",
                    "patisserie", "glacier", "bar", "cafeteria", "hotellerie",
                    "hotel", "debit de boissons"],
    },
    "BTP": {
        "libelle": "BTP — travaux de construction et de génie civil",
        "famille_biens": "32 - Matieres premieres et fournitures liees",
        "vente": "7051", "tva_vente": "4433",
        "indices": ["batiment", "btp", "construction", "travaux publics",
                    "genie civil", "entreprise generale", "maconnerie",
                    "electricite", "plomberie", "etancheite", "voirie",
                    "terrassement", "cablage", "installation", "renovation"],
    },
    "Services": {
        "libelle": "Prestation de services — aucun bien acheté pour revente",
        "famille_biens": None,
        "vente": "7061", "tva_vente": "4432",
        "indices": ["service", "services", "prestation", "conseil",
                    "consultance", "ingenierie", "etude", "bureau d'etudes",
                    "formation", "informatique", "logiciel", "communication",
                    "publicite", "marketing", "transport", "transit",
                    "nettoyage", "gardiennage", "securite", "immobilier",
                    "agence", "courtage", "comptabilite", "audit", "juridique"],
    },
    "Agriculture": {
        "libelle": "Agriculture et élevage — production du vivant",
        "famille_biens": "32 - Matieres premieres et fournitures liees",
        "vente": "7021", "tva_vente": "4431",
        "indices": ["agriculture", "agricole", "elevage", "ferme", "plantation",
                    "culture", "maraichage", "peche", "pisciculture",
                    "aviculture", "exploitation agricole", "cacao", "hevea",
                    "palmier", "anacarde"],
    },
}

# Profil réservé aux sociétés dont le RCCM mentionne plusieurs métiers.
# Aucune famille par défaut : l'arbitrage est explicite, article par article.
PROFIL_MIXTE = "Mixte"

# Branche la proposition sur l'IA embarquée de PME360 au lieu du
# rapprochement lexical. Laisser à False tant que le service n'est pas en
# production : le rapprochement lexical, lui, ne tombe jamais en panne.
PROPOSITION_IA = False

# Exceptions explicites, article par article, quand le profil de la société
# ne suffit pas — un négociant qui transforme une partie de ses achats, par
# exemple. Vide par défaut : le profil doit suffire dans la quasi-totalité
# des cas, et une exception qui devient la règle signale un profil mal choisi.
RECLASSEMENT = {}

APPLIQUER_RECLASSEMENT = False   # True seulement après validation OEC-CI

# Champs ajoutés à la fiche Société, à côté de sitiame_rccm qui existe déjà.
CHAMPS_RCCM = [
    {"fieldname": "sitiame_activite_rccm", "label": "Activité déclarée au RCCM",
     "fieldtype": "Small Text", "insert_after": "sitiame_rccm",
     "description": "Libellé exact porté au registre du commerce. "
                    "Il détermine le plan de comptes de la société."},
    {"fieldname": "sitiame_profil_comptable", "label": "Profil comptable (validé)",
     "fieldtype": "Select", "insert_after": "sitiame_activite_rccm",
     "description": "Commande le compte d'achat des biens, le compte de "
                    "vente et la TVA facturée. Ne se change pas sans "
                    "arbitrage : des écritures en dépendent."},
    {"fieldname": "sitiame_profil_propose", "label": "Profil proposé",
     "fieldtype": "Data", "insert_after": "sitiame_profil_comptable",
     "read_only": 1,
     "description": "Proposition automatique. Sans valeur tant qu'un humain "
                    "ne l'a pas reprise dans le champ ci-dessus."},
    {"fieldname": "sitiame_profil_justification", "label": "Motif de la proposition",
     "fieldtype": "Small Text", "insert_after": "sitiame_profil_propose",
     "read_only": 1},
    {"fieldname": "sitiame_profil_valide_par", "label": "Profil validé par",
     "fieldtype": "Link", "options": "User",
     "insert_after": "sitiame_profil_justification", "read_only": 1},
    {"fieldname": "sitiame_profil_valide_le", "label": "Profil validé le",
     "fieldtype": "Date", "insert_after": "sitiame_profil_valide_par",
     "read_only": 1},
]

# Rattachement des entrepôts à leur compte de stock SYSCOHADA.
# Aujourd'hui les quatre entrepôts pointent tous vers 3111, y compris
# « Produits finis » et « Travaux en cours ». La clé est cherchée dans le
# nom de l'entrepôt, sans tenir compte de la casse.
ENTREPOTS = {
    "magasin":       "3111",   # Marchandises A1
    "produits finis": "361",   # Produits finis A
    "travaux en cours": "3421",  # Travaux en cours T1
    "transit":       "381",    # Marchandises en cours de route
}

# Comptes de stock à typer « Stock » dans ERPNext. Sans ce type, ERPNext
# refuse de rattacher le compte à un entrepôt et l'inventaire permanent
# ne démarre pas.
COMPTES_STOCK = ["3111", "321", "331", "3351", "361", "3421", "381"]

COMPTE_ATTENTE_NUM = "6589"
COMPTE_VARIATION_DEFAUT = "6031"   # stock_adjustment_account de la société
COMPTE_RECEPTION_NON_FACTUREE = "4081"   # Fournisseurs, factures non parvenues

# Unités de mesure manifestement fausses, et leur remplacement.
UOM_SUSPECTES = {"Kilogram-Force": "Kg"}

# Rubriques placées du mauvais côté du bilan par le modèle de plan
# comptable « Syscohada - Plan Comptable avec code ». La TVA facturée est
# une dette envers l'État : elle ne peut pas figurer à l'actif.
BRANCHE_PASSIF = "44-État et collectivités publiques (PASSIF)"
RUBRIQUES_A_DEPLACER = {
    "443": BRANCHE_PASSIF,   # État, TVA facturée
}

ETAPES = [
    "champs_rccm",      # champs du profil d'activité sur la fiche Société
    "profil",           # lire le profil validé, ou en proposer un
    "comptes",          # les comptes cibles existent-ils et sont-ils imputables
    "typer_stock",      # account_type = Stock sur les comptes de classe 3
    "entrepots",        # rattacher chaque entrepôt à son compte SYSCOHADA
    "societe_stock",    # réglages société de l'inventaire permanent
    "familles",         # créer les familles et leurs comptes
    "reclasser",        # ranger les articles dans leur famille
    "purger_defauts",   # retirer les affectations par article périmées
    "tva_facturee",     # remettre 443 au passif
    "inventaire_6589",  # ce qui dort dans le compte d'attente
    "regulariser_6589", # brouillons de reclassement, jamais validés
    "controle_stock",   # la chaîne d'inventaire permanent est-elle complète
]


# =============================================================================
# SOCLE — journalisation, simulation, garde-fous
# =============================================================================

class Journal:
    """Trace tout ce que le script fait ou ferait. Rien n'est silencieux."""

    def __init__(self, apply_mode):
        self.apply = apply_mode
        self.lignes = []
        self.anomalies = []

    def _log(self, niveau, message, detail=None):
        prefixe = "   APPLIQUÉ " if self.apply else "   SIMULÉ   "
        if niveau in ("info", "titre", "alerte"):
            prefixe = "            "
        self.lignes.append({"niveau": niveau, "message": message, "detail": detail})
        if niveau == "titre":
            print("\n" + "=" * 78)
            print(message)
            print("=" * 78)
        elif niveau == "alerte":
            print("   /!\\  " + message)
            self.anomalies.append(message)
        else:
            print(prefixe + message)
        if detail:
            print("              " + str(detail))

    def titre(self, m):            self._log("titre", m)
    def info(self, m, d=None):     self._log("info", m, d)
    def action(self, m, d=None):   self._log("action", m, d)
    def alerte(self, m, d=None):   self._log("alerte", m, d)

    def rapport(self, chemin):
        with open(chemin, "w", encoding="utf-8") as f:
            json.dump({"mode": "apply" if self.apply else "simulation",
                       "date": nowdate(), "lignes": self.lignes,
                       "anomalies": self.anomalies}, f,
                      ensure_ascii=False, indent=2)
        print(f"\nRapport écrit dans {chemin}")


def garde_fous(jrn):
    if frappe.session.user != "Administrator" and "System Manager" not in frappe.get_roles():
        frappe.throw("Ce script doit être exécuté par Administrator ou un System Manager.")

    if not frappe.db.get_single_value("Accounts Settings", "enable_immutable_ledger"):
        frappe.throw(
            "Le grand livre immuable est désactivé. Appliquez d'abord l'étape "
            "`parametres` du script 1."
        )
    jrn.info("Grand livre immuable : actif.")


def societes(filtre=None):
    if filtre:
        return [filtre] if frappe.db.exists("Company", filtre) else []
    return frappe.get_all("Company", pluck="name", order_by="name")


def compte_imputable(numero, societe):
    """Nom complet d'un compte de dernier niveau. None s'il est collectif."""
    if not numero:
        return None
    return frappe.db.get_value(
        "Account", {"account_number": numero, "company": societe, "is_group": 0}, "name"
    )


def compte_collectif(numero, societe):
    if not numero:
        return False
    return bool(frappe.db.get_value(
        "Account", {"account_number": numero, "company": societe, "is_group": 1}, "name"
    ))


def modele_tva(titre, societe):
    if not titre:
        return None
    return frappe.db.get_value(
        "Item Tax Template", {"title": titre, "company": societe}, "name"
    )


def a_des_ecritures(nom_compte):
    return bool(frappe.db.exists("GL Entry", {"account": nom_compte}))


def champ_existe(doctype, fieldname):
    """Vrai si le doctype porte ce champ dans cette version d'ERPNext.

    Les réglages de l'inventaire permanent ont changé de nom entre les
    versions. Plutôt que de supposer, on regarde.
    """
    return bool(frappe.get_meta(doctype).get_field(fieldname))


def _sans_accents(texte):
    """Rabat les accents et la casse, pour comparer des libellés saisis
    à la main. « Pâtisserie » et « patisserie » doivent se rencontrer."""
    import unicodedata
    plat = unicodedata.normalize("NFD", texte or "")
    return "".join(c for c in plat if unicodedata.category(c) != "Mn").lower()


# =============================================================================
# LA PROPOSITION DE PROFIL — rapprochement lexical, ou IA embarquée
# =============================================================================

def proposer_profil(libelle_activite, utiliser_ia=None):
    """Propose un profil comptable à partir du libellé d'activité du RCCM.

    Retourne un dictionnaire : profil, certitude, motif, termes.
    Ne retourne JAMAIS une décision : la valeur est écrite dans un champ
    séparé, qu'un humain reprend ou non.

    Deux chemins. L'IA embarquée de PME360 quand elle est branchée, sinon
    un rapprochement lexical déterministe qui n'a besoin de rien.
    """
    if libelle_activite is None or not str(libelle_activite).strip():
        return {"profil": None, "certitude": 0.0, "termes": [],
                "motif": "Aucune activité déclarée au RCCM. Le profil ne "
                         "peut pas être proposé : il faut saisir le libellé "
                         "exact porté au registre du commerce."}

    recours_ia = PROPOSITION_IA if utiliser_ia is None else utiliser_ia
    if recours_ia:
        resultat = _proposer_profil_ia(libelle_activite)
        if resultat:
            return resultat
        # L'IA n'a pas répondu : on ne bloque pas l'ouverture d'un compte
        # parce qu'un service est indisponible.

    return _proposer_profil_lexical(libelle_activite)


def _proposer_profil_lexical(libelle_activite):
    """Rapprochement lexical entre le libellé du RCCM et les profils.

    Volontairement simple et lisible : on compte les termes reconnus, on
    privilégie les termes longs — « vente au detail » pèse plus que
    « vente » — et on signale toute ambiguïté au lieu de la trancher.
    """
    texte = _sans_accents(libelle_activite)
    scores, trouves = {}, {}

    for nom, p in PROFILS.items():
        for terme in p["indices"]:
            t = _sans_accents(terme)
            if t in texte:
                scores[nom] = scores.get(nom, 0) + len(t)
                trouves.setdefault(nom, []).append(terme)

    if not scores:
        return {"profil": None, "certitude": 0.0, "termes": [],
                "motif": f"Aucun terme reconnu dans « {libelle_activite} ». "
                         f"Le profil doit être choisi à la main parmi : "
                         f"{', '.join(PROFILS)}."}

    classement = sorted(scores.items(), key=lambda kv: kv[1], reverse=True)
    gagnant, meilleur = classement[0]
    second = classement[1][1] if len(classement) > 1 else 0
    certitude = round((meilleur - second) / float(meilleur), 2) if meilleur else 0.0

    # Deux métiers substantiellement présents : c'est une société mixte, et
    # le dire vaut mieux que trancher à pile ou face. Le seuil est délibéré :
    # « Commerce général et restauration » doit ressortir mixte, pas
    # restaurateur parce que le mot est plus long.
    if len(classement) > 1 and certitude < 0.40:
        autres = ", ".join(n for n, _ in classement[:3])
        return {
            "profil": PROFIL_MIXTE, "certitude": certitude,
            "termes": sum((trouves[n] for n, _ in classement[:3]), []),
            "motif": f"Plusieurs métiers reconnus dans « {libelle_activite} » "
                     f"({autres}). Profil mixte : chaque famille d'articles "
                     f"devra être arbitrée explicitement.",
        }

    return {
        "profil": gagnant, "certitude": certitude, "termes": trouves[gagnant],
        "motif": f"Termes reconnus dans le RCCM : "
                 f"{', '.join(sorted(set(trouves[gagnant])))}. "
                 f"{PROFILS[gagnant]['libelle']}.",
    }


def _proposer_profil_ia(libelle_activite):
    """Point de branchement de l'IA embarquée de PME360.

    Attendu en retour : le même dictionnaire que le rapprochement lexical,
    avec un profil figurant dans PROFILS ou valant PROFIL_MIXTE. Toute
    autre valeur est rejetée — une IA ne crée pas de profil comptable qui
    n'existe pas au référentiel.

    Retourne None si le service est absent ou en échec : l'appelant
    retombe alors sur le rapprochement lexical, qui ne dépend de rien.
    """
    try:
        from sitiame_core.ia import classer_activite   # noqa: WPS433
    except ImportError:
        return None

    try:
        brut = classer_activite(libelle_activite, sorted(PROFILS))
    except Exception:
        frappe.log_error(title="PME360 — proposition de profil par IA",
                         message=frappe.get_traceback())
        return None

    if not isinstance(brut, dict):
        return None
    profil = brut.get("profil")
    if profil not in PROFILS and profil != PROFIL_MIXTE:
        return None

    return {
        "profil": profil,
        "certitude": float(brut.get("certitude") or 0.0),
        "termes": list(brut.get("termes") or []),
        "motif": (brut.get("motif") or "Proposition de l'IA embarquée.")
                 + " [proposé par l'IA, à valider par un humain]",
    }


def profil_societe(societe):
    """Profil VALIDÉ d'une société. None si personne ne l'a encore validé.

    La proposition automatique n'est délibérément pas lue ici : tant qu'un
    humain ne l'a pas reprise, elle ne commande aucun compte.
    """
    if not champ_existe("Company", "sitiame_profil_comptable"):
        return None
    valeur = frappe.db.get_value("Company", societe, "sitiame_profil_comptable")
    return valeur or None


def familles_du_profil(profil):
    """Familles ouvertes à une société, d'après son profil.

    Sans profil validé, ou en profil mixte, toutes les familles sont
    ouvertes : mieux vaut un référentiel large qu'un article qui tombe dans
    le compte d'attente faute de famille.
    """
    if not profil or profil == PROFIL_MIXTE:
        return set(FAMILLES)
    return {nom for nom, f in FAMILLES.items() if profil in f.get("profils", set())}


# =============================================================================
# ÉTAPE 1 — les comptes cibles existent-ils, et sont-ils imputables
# =============================================================================

def etape_champs_rccm(jrn, apply_mode, societe=None):
    """Ajoute à la fiche Société les champs du profil d'activité.

    Le champ sitiame_rccm existe déjà : on complète ce bloc au lieu d'en
    ouvrir un second. Les champs sont des Custom Field, donc retirables
    sans toucher au cœur d'ERPNext.
    """
    jrn.titre("ÉTAPE 0 — Champs du profil d'activité sur la fiche Société")

    options = "\n" + "\n".join(sorted(PROFILS) + [PROFIL_MIXTE])
    poses = 0

    for champ in CHAMPS_RCCM:
        nom = f"Company-{champ['fieldname']}"
        if frappe.db.exists("Custom Field", nom):
            jrn.info(f"{champ['fieldname']} : déjà présent.")
            continue
        jrn.action(f"Création du champ « {champ['label']} »")
        poses += 1
        if not apply_mode:
            continue
        doc = dict(champ)
        doc.update({"doctype": "Custom Field", "dt": "Company"})
        if champ["fieldtype"] == "Select":
            doc["options"] = options
        frappe.get_doc(doc).insert(ignore_permissions=True)

    jrn.info(f"{poses} champ(s) à créer.")
    jrn.info("Profils disponibles : " + ", ".join(sorted(PROFILS)) +
             f", {PROFIL_MIXTE}.")

    manquants = [s for s in societes(societe)
                 if not frappe.db.get_value("Company", s, "sitiame_rccm")]
    if manquants:
        jrn.alerte(
            f"{len(manquants)} société(s) sur {len(societes(societe))} n'ont "
            f"aucun numéro RCCM enregistré. Le bloc d'ouverture de compte "
            f"existe depuis le début mais n'est pas rempli. Tant qu'il reste "
            f"vide, le profil comptable ne peut reposer sur rien : "
            f"{', '.join(manquants[:6])}"
            + (" …" if len(manquants) > 6 else "")
        )
    return {"champs": poses, "sans_rccm": len(manquants)}


def etape_profil(jrn, apply_mode, societe=None):
    """Lit le profil validé, ou en propose un à partir du libellé du RCCM.

    N'écrit jamais le profil validé : seulement la proposition, son motif
    et son indice de certitude. La validation est un geste humain, parce
    qu'elle engage la liasse fiscale du client.
    """
    jrn.titre("ÉTAPE 0 bis — Profil d'activité des sociétés")

    if not champ_existe("Company", "sitiame_profil_comptable"):
        jrn.alerte("Les champs du profil n'existent pas encore. Appliquez "
                   "d'abord l'étape `champs_rccm`.")
        return {"valides": 0, "proposes": 0}

    valides, proposes, sans_rien = 0, 0, []

    for soc in societes(societe):
        valide = profil_societe(soc)
        if valide:
            jrn.info(f"{soc} : profil validé « {valide} » — "
                     f"{PROFILS.get(valide, {}).get('libelle', 'arbitrage article par article')}")
            valides += 1
            continue

        libelle = frappe.db.get_value("Company", soc, "sitiame_activite_rccm")
        p = proposer_profil(libelle)

        if not p["profil"]:
            sans_rien.append(soc)
            jrn.alerte(f"{soc} : {p['motif']}")
            continue

        jrn.action(f"{soc} : profil proposé « {p['profil']} » "
                   f"(certitude {p['certitude']:.0%}) — {p['motif']}")
        proposes += 1
        if apply_mode:
            frappe.db.set_value("Company", soc, {
                "sitiame_profil_propose": p["profil"],
                "sitiame_profil_justification": p["motif"],
            })

    if valides == 0:
        jrn.alerte(
            "Aucune société n'a de profil comptable validé. Les familles "
            "seront donc toutes ouvertes à toutes les sociétés, ce qui "
            "fonctionne mais laisse le choix du compte d'achat à "
            "l'utilisateur au moment de la saisie — exactement ce que ce "
            "dispositif cherche à éviter."
        )
    if sans_rien:
        jrn.alerte(f"{len(sans_rien)} société(s) sans libellé d'activité "
                   f"exploitable. À reprendre à l'ouverture du compte.")

    jrn.info(f"{valides} profil(s) validé(s), {proposes} proposition(s).")
    return {"valides": valides, "proposes": proposes}


def etape_comptes(jrn, apply_mode, societe=None):
    """Contrôle la table FAMILLES contre le plan comptable réel.

    C'est le garde-fou qui manquait au script 1 : une règle qui désigne un
    compte de regroupement ne corrige rien et n'en dit rien.
    """
    jrn.titre("ÉTAPE 1 — Contrôle des comptes du référentiel")

    manquants, collectifs = [], []
    for soc in societes(societe):
        for nom, f in FAMILLES.items():
            for role in ("achat", "vente", "stock", "variation"):
                num = f.get(role)
                if not num or compte_imputable(num, soc):
                    continue
                if compte_collectif(num, soc):
                    collectifs.append(f"{soc} · {nom} · {role} : {num} est collectif")
                else:
                    manquants.append(f"{soc} · {nom} · {role} : {num} absent")

    if collectifs:
        jrn.alerte(f"{len(collectifs)} compte(s) visé(s) sont des comptes de "
                   f"regroupement et ne peuvent recevoir aucune écriture.")
        for c in collectifs[:25]:
            jrn.info("  " + c)
    if manquants:
        jrn.alerte(f"{len(manquants)} compte(s) visé(s) n'existent pas.")
        for c in manquants[:25]:
            jrn.info("  " + c)
    if not collectifs and not manquants:
        jrn.info(f"Les {len(FAMILLES)} familles pointent toutes vers des comptes "
                 f"existants et imputables, dans chaque société contrôlée.")

    return {"collectifs": collectifs, "manquants": manquants}


# =============================================================================
# ÉTAPE 2 — typer les comptes de stock
# =============================================================================

def etape_typer_stock(jrn, apply_mode, societe=None):
    """Pose account_type = « Stock » sur les comptes de classe 3 utilisés.

    ERPNext refuse de rattacher un entrepôt à un compte qui n'est pas de ce
    type. Sans cette étape, la première réception de marchandises échoue.
    """
    jrn.titre("ÉTAPE 2 — Typage des comptes de stock")

    traites, deja = 0, 0
    for soc in societes(societe):
        for num in COMPTES_STOCK:
            nom = compte_imputable(num, soc)
            if not nom:
                jrn.info(f"{soc} : compte {num} absent, ignoré.")
                continue
            actuel = frappe.db.get_value("Account", nom, "account_type")
            if actuel == "Stock":
                deja += 1
                continue
            if actuel:
                jrn.alerte(f"{soc} · {num} : type « {actuel} » déjà posé, "
                           f"non remplacé. À arbitrer manuellement.")
                continue
            jrn.action(f"{soc} · {num} : type « Stock »")
            if apply_mode:
                frappe.db.set_value("Account", nom, "account_type", "Stock")
            traites += 1

    jrn.info(f"{traites} compte(s) à typer, {deja} déjà typé(s).")
    return {"types": traites, "deja": deja}


# =============================================================================
# ÉTAPE 3 — rattacher les entrepôts à leur compte SYSCOHADA
# =============================================================================

def etape_entrepots(jrn, apply_mode, societe=None):
    """Chaque entrepôt doit pointer vers le compte de stock de sa nature.

    Aujourd'hui les quatre entrepôts de SITIAME pointent tous vers 3111
    « Marchandises », y compris « Produits finis » et « Travaux en cours ».
    Un stock de produits finis valorisé en compte de marchandises fausse le
    bilan et la variation de stocks.
    """
    jrn.titre("ÉTAPE 3 — Rattachement des entrepôts")

    corriges, inchanges, sans_regle = 0, 0, []
    for soc in societes(societe):
        for w in frappe.get_all("Warehouse",
                                filters={"company": soc, "is_group": 0},
                                fields=["name", "warehouse_name", "account"]):
            bas = (w.warehouse_name or w.name).lower()
            num = next((v for k, v in ENTREPOTS.items() if k in bas), None)
            if not num:
                sans_regle.append(f"{soc} · {w.warehouse_name}")
                continue
            cible = compte_imputable(num, soc)
            if not cible:
                jrn.alerte(f"{soc} · {w.warehouse_name} : compte {num} absent.")
                continue
            if w.account == cible:
                inchanges += 1
                continue
            jrn.action(f"{soc} · {w.warehouse_name} : {w.account or 'aucun'} -> {cible}")
            if apply_mode:
                frappe.db.set_value("Warehouse", w.name, "account", cible)
            corriges += 1

    if sans_regle:
        jrn.alerte(f"{len(sans_regle)} entrepôt(s) sans règle de rattachement. "
                   f"Ils garderont le compte par défaut de la société.")
        for s in sans_regle[:15]:
            jrn.info("  " + s)

    jrn.info(f"{corriges} entrepôt(s) à rattacher, {inchanges} déjà corrects.")
    return {"corriges": corriges, "sans_regle": len(sans_regle)}


# =============================================================================
# ÉTAPE 4 — réglages société de l'inventaire permanent
# =============================================================================

def etape_societe_stock(jrn, apply_mode, societe=None):
    """Pose les comptes que l'inventaire permanent SYSCOHADA consomme.

    C'est l'étape qui débloque la tenue du stock. Le réglage global
    `book_stock_expense_gl_entries` est actif mais les comptes qu'il exige
    sont vides : ERPNext refuse alors toute entrée en stock. Les remplir
    rétablit à la fois la possibilité de recevoir de la marchandise et la
    double écriture SYSCOHADA D 601 / C 6031.

    Ne touche pas à enable_perpetual_inventory : la Direction a tranché.
    """
    jrn.titre("ÉTAPE 4 — Réglages société de l'inventaire permanent")

    livre = frappe.db.get_single_value("Accounts Settings",
                                       "book_stock_expense_gl_entries")
    if livre:
        jrn.info("Paramètres comptables : la double écriture d'achat est "
                 "ACTIVE. C'est le réglage qui alimente le compte 601 et la "
                 "ligne RA du compte de résultat.")
    else:
        jrn.alerte(
            "Paramètres comptables : la double écriture d'achat est INACTIVE. "
            "Sans elle, l'achat ira directement au compte de stock et le "
            "compte 601 « Achats de marchandises » restera vide, donc la marge "
            "commerciale illisible dans la liasse. Le script ne l'active pas "
            "de lui-même : c'est un réglage global, commun aux dix sociétés, "
            "qui rend des comptes obligatoires partout. À activer dans les "
            "Paramètres comptables une fois cette étape appliquée."
        )

    # Les trois comptes historiques de l'inventaire permanent.
    reglages = {
        "default_inventory_account": "3111",
        "stock_adjustment_account": COMPTE_VARIATION_DEFAUT,
        "stock_received_but_not_billed": COMPTE_RECEPTION_NON_FACTUREE,
        # La paire qui produit l'écriture SYSCOHADA D 601 / C 6031.
        "purchase_expense_account": "6011",
        "purchase_expense_contra_account": COMPTE_VARIATION_DEFAUT,
    }

    poses, absents = 0, []
    for soc in societes(societe):
        if not frappe.db.get_value("Company", soc, "enable_perpetual_inventory"):
            jrn.alerte(f"{soc} : inventaire permanent DÉSACTIVÉ alors que la "
                       f"Direction l'a retenu pour PME360. À activer après "
                       f"s'être assuré qu'aucun stock n'a été tenu autrement.")

        for champ, num in reglages.items():
            if not champ_existe("Company", champ):
                if champ not in absents:
                    absents.append(champ)
                continue
            cible = compte_imputable(num, soc)
            if not cible:
                jrn.alerte(f"{soc} · {champ} : compte {num} introuvable.")
                continue
            actuel = frappe.db.get_value("Company", soc, champ)
            if actuel == cible:
                continue
            jrn.action(f"{soc} · {champ} : {actuel or 'vide'} -> {cible}")
            if apply_mode:
                frappe.db.set_value("Company", soc, champ, cible)
            poses += 1

        # Les deux comptes des mouvements de stock internes ne sont PAS
        # posés automatiquement : il n'existe pas de correspondance
        # SYSCOHADA évidente pour une régularisation d'inventaire, et se
        # tromper ici fausserait chaque inventaire physique.
        for champ in ("expenses_added_to_stock_account",
                      "expenses_added_to_stock_contra_account"):
            if champ_existe("Company", champ) and \
               not frappe.db.get_value("Company", soc, champ) and livre:
                jrn.alerte(
                    f"{soc} · {champ} : vide. Tant qu'il le reste, les "
                    f"mouvements et inventaires de stock resteront bloqués. "
                    f"Ce compte n'est pas posé automatiquement : la "
                    f"contrepartie comptable d'une régularisation d'inventaire "
                    f"est un arbitrage de l'expert-comptable, pas un défaut "
                    f"technique."
                )

    if absents:
        jrn.alerte(f"Champ(s) absent(s) de cette version d'ERPNext : "
                   f"{', '.join(absents)}. Le schéma SYSCOHADA en double "
                   f"écriture n'est alors pas disponible nativement.")

    jrn.info(f"{poses} réglage(s) à poser.")
    jrn.info(
        "Schéma attendu après cette étape, sur une réception de marchandises :"
        "  D 3111 / C 4081  puis  D 6011 / C 6031. "
        "À la vente : D 6031 / C 3111. Vérifiez-le sur la première pièce, en "
        "présence du comptable, avant de généraliser aux dix sociétés."
    )
    return {"reglages": poses, "double_ecriture": bool(livre)}


# =============================================================================
# ÉTAPE 5 — créer les familles et y attacher les comptes
# =============================================================================

def etape_familles(jrn, apply_mode, societe=None):
    """Crée l'arbre SYSCOHADA et remplit Item Group Default.

    C'est le niveau que les logiciels comptables utilisent en priorité et
    qui est vide dans l'instance : ERPNext cherche le compte sur l'article,
    puis sur la famille, puis sur la société. Les deux premiers niveaux
    étant vides, tout tombait sur le compte d'attente.
    """
    jrn.titre("ÉTAPE 5 — Familles SYSCOHADA et comptes par famille")

    racine = frappe.db.get_value(
        "Item Group", {"is_group": 1, "parent_item_group": ""}, "name"
    ) or "All Item Groups"
    jrn.info(f"Racine de l'arbre : {racine}")

    cibles = societes(societe)
    cree, posees = 0, 0

    # Les champs de l'enfant Item Default varient selon la version : on
    # regarde ce qui existe avant d'écrire, plutôt que de le supposer.
    a_cogs = champ_existe("Item Default", "default_cogs_account")
    a_inventaire = champ_existe("Item Default", "default_inventory_account")
    a_achat = champ_existe("Item Default", "purchase_expense_account")
    a_contra = champ_existe("Item Default", "purchase_expense_contra_account")
    jrn.info(f"Champs disponibles — COGS: {a_cogs} · inventaire: {a_inventaire} "
             f"· achat: {a_achat} · contrepartie: {a_contra}")

    for nom, f in FAMILLES.items():
        existe = frappe.db.exists("Item Group", nom)
        if not existe:
            jrn.action(f"Création de la famille « {nom} » — {f.get('aide','')}")
            if apply_mode:
                frappe.get_doc({
                    "doctype": "Item Group",
                    "item_group_name": nom,
                    "parent_item_group": racine,
                    "is_group": 0,
                }).insert(ignore_permissions=True)
            cree += 1
        else:
            jrn.info(f"Famille « {nom} » : déjà présente.")

        if not apply_mode and not existe:
            for soc in cibles:
                jrn.action(f"  {soc} : achat {f.get('achat')} · vente {f.get('vente')} "
                           f"· stock {f.get('stock')} · variation {f.get('variation')}")
            continue

        doc = frappe.get_doc("Item Group", nom)
        deja = {d.company for d in (doc.item_group_defaults or [])}
        modifie = False

        for soc in cibles:
            if soc in deja:
                jrn.info(f"  {soc} : affectation déjà en place.")
                continue

            # Le profil d'activité décide des familles ouvertes à la société.
            profil = profil_societe(soc)
            if nom not in familles_du_profil(profil):
                jrn.info(f"  {soc} : famille hors du profil « {profil} », "
                         f"laissée sans affectation.")
                continue

            achat = compte_imputable(f.get("achat"), soc)
            stock = compte_imputable(f.get("stock"), soc)
            variation = compte_imputable(f.get("variation"), soc)

            # Le compte de vente de la famille des biens vient du profil :
            # un restaurant qui achète de la viande en 6021 ne vend pas de
            # la viande, il vend un repas, donc 7061 et non 7011.
            num_vente = f.get("vente")
            p = PROFILS.get(profil or "")
            if p and p.get("famille_biens") == nom and p.get("vente"):
                num_vente = p["vente"]
            vente = compte_imputable(num_vente, soc)

            if not any([achat, vente, stock]):
                jrn.alerte(f"  {soc} · {nom} : aucun compte imputable, famille "
                           f"laissée sans affectation.")
                continue

            ligne = {"company": soc}
            if achat:
                # Utilisé tel quel pour une famille non stockée. Pour une
                # famille stockée, ERPNext le réécrit : il n'est gardé que
                # comme repli. Le vrai porteur du 601 est plus bas.
                ligne["expense_account"] = achat
            if vente:
                ligne["income_account"] = vente

            if f["stockable"]:
                if stock and a_inventaire:
                    ligne["default_inventory_account"] = stock
                # Sortie de stock à la vente : D 6031 / C 31, l'écriture
                # SYSCOHADA de variation des stocks.
                if variation and a_cogs:
                    ligne["default_cogs_account"] = variation
                # Entrée en stock à l'achat : la paire qui produit
                # D 601 Achats / C 6031 Variations, en plus de D 31 / C 408.
                if achat and a_achat:
                    ligne["purchase_expense_account"] = achat
                if variation and a_contra:
                    ligne["purchase_expense_contra_account"] = variation

            jrn.action(f"  {soc} : achat {achat} · vente {vente} · "
                       f"stock {stock} · variation {variation}")
            if apply_mode:
                doc.append("item_group_defaults", ligne)
                modifie = True
            posees += 1

        titres = {d.item_tax_template for d in (doc.taxes or [])}
        for soc in cibles:
            mt = modele_tva(f.get("tva"), soc)
            if mt and mt not in titres:
                jrn.action(f"  {soc} : modèle de TVA {mt}")
                if apply_mode:
                    doc.append("taxes", {"item_tax_template": mt})
                    modifie = True

        if apply_mode and modifie:
            doc.save(ignore_permissions=True)

    jrn.info(f"{cree} famille(s) à créer, {posees} affectation(s) à poser.")
    if not a_cogs:
        jrn.alerte("Cette version d'ERPNext n'expose pas default_cogs_account "
                   "sur Item Default : le coût des marchandises vendues suivra "
                   "le compte de charge de la famille. À vérifier sur la "
                   "première vente avant de généraliser.")
    if not (a_achat and a_contra):
        jrn.alerte("Cette version n'expose pas la paire purchase_expense sur "
                   "Item Default : le compte 601 ne pourra être posé qu'au "
                   "niveau de la société, donc identique pour toutes les "
                   "familles. Acceptable pour un négoce mono-activité, à "
                   "revoir dès qu'une société achète à la fois des "
                   "marchandises et des matières premières.")
    return {"familles_creees": cree, "affectations": posees}


# =============================================================================
# ÉTAPE 6 — ranger les articles dans leur famille
# =============================================================================

def _societes_de_larticle(code):
    """Sociétés qui utilisent réellement cet article.

    Dans ERPNext, la famille d'un article est unique : un même code
    partagé entre plusieurs sociétés ne peut pas appartenir à deux
    familles. Tant que le cloisonnement n'est pas posé, un article partagé
    entre un négociant et un restaurateur n'a pas de bonne réponse — et le
    dire vaut mieux que trancher.
    """
    soc = set(frappe.get_all("Item Default",
                             filters={"parent": code, "parenttype": "Item"},
                             pluck="company"))
    rows = frappe.db.sql(
        """SELECT pi.company FROM `tabPurchase Invoice Item` pii
           INNER JOIN `tabPurchase Invoice` pi ON pi.name = pii.parent
           WHERE pii.item_code = %s AND pi.docstatus = 1""",
        code, as_list=True)
    soc |= {r[0] for r in rows if r[0]}
    return {s for s in soc if s}


def _famille_cible(article, indecidables):
    """Famille d'un article : exception explicite, sinon profil de sa société.

    Retourne (famille, origine de la décision).
    """
    forcee = RECLASSEMENT.get(article.name)
    if forcee:
        return forcee, "exception explicite"

    # Un article non stockable relève des services, pas d'une famille de biens.
    if not article.is_stock_item:
        return None, None

    sociétés = _societes_de_larticle(article.name)
    if not sociétés:
        return None, None

    profils = {profil_societe(s) for s in sociétés}
    profils.discard(None)

    if not profils:
        return None, None

    if len(profils) > 1:
        indecidables.append(
            f"{article.name} ({article.item_name}) : utilisé par des sociétés "
            f"de profils différents ({', '.join(sorted(profils))}). ERPNext ne "
            f"permet qu'une famille par article : aucun reclassement "
            f"automatique possible. À trancher en créant un article par "
            f"société — ce que le cloisonnement imposera de toute façon."
        )
        return None, None

    profil = profils.pop()
    if profil == PROFIL_MIXTE:
        indecidables.append(
            f"{article.name} ({article.item_name}) : société en profil mixte. "
            f"La famille doit être choisie explicitement, dans RECLASSEMENT."
        )
        return None, None

    famille = PROFILS.get(profil, {}).get("famille_biens")
    if not famille:
        indecidables.append(
            f"{article.name} ({article.item_name}) : le profil « {profil} » "
            f"n'achète pas de biens pour revente, mais l'article est déclaré "
            f"stockable. Incohérence à arbitrer."
        )
        return None, None

    return famille, f"profil « {profil} »"


def etape_reclasser(jrn, apply_mode, societe=None):
    """Déplace les articles, et aligne leur caractère stockable sur la famille.

    Inactive tant que APPLIQUER_RECLASSEMENT est False : changer la famille
    d'un article change le compte de ses futures écritures, donc son
    traitement fiscal.
    """
    jrn.titre("ÉTAPE 6 — Rangement des articles")

    if not APPLIQUER_RECLASSEMENT:
        jrn.info("APPLIQUER_RECLASSEMENT est False : inventaire seul, aucun "
                 "article n'est déplacé.")

    articles = frappe.get_all("Item", fields=["name", "item_name", "item_group",
                                              "is_stock_item", "stock_uom",
                                              "disabled"])
    deplaces, sans_regle, indecidables = 0, [], []

    for a in articles:
        cible, origine = _famille_cible(a, indecidables)
        if not cible:
            if a.item_group not in FAMILLES:
                sans_regle.append(f"{a.name} ({a.item_name}) — « {a.item_group} »")
            continue
        if a.item_group == cible:
            continue

        jrn.action(f"{a.name} ({a.item_name}) : « {a.item_group} » -> "
                   f"« {cible} » [{origine}]")
        deplaces += 1
        if not (apply_mode and APPLIQUER_RECLASSEMENT):
            continue
        if not frappe.db.exists("Item Group", cible):
            jrn.alerte(f"  famille « {cible} » absente, article laissé en place.")
            continue
        frappe.db.set_value("Item", a.name, "item_group", cible)

    for message in indecidables:
        jrn.alerte(message)

    # Cohérence entre le caractère stockable de l'article et celui de sa famille.
    ignorer = []
    for a in articles:
        famille = _famille_cible(a, ignorer)[0] or a.item_group
        regle = FAMILLES.get(famille)
        if not regle:
            continue
        attendu = 1 if regle["stockable"] else 0
        if a.is_stock_item == attendu:
            continue
        mot = "stockable" if attendu else "non stockable"
        if frappe.db.exists("Stock Ledger Entry", {"item_code": a.name}):
            jrn.alerte(f"{a.name} : devrait être {mot} d'après sa famille, mais "
                       f"porte déjà des mouvements de stock. Changement refusé.")
            continue
        jrn.action(f"{a.name} : article {mot}, d'après la famille « {famille} ».")
        if apply_mode and APPLIQUER_RECLASSEMENT:
            frappe.db.set_value("Item", a.name, "is_stock_item", attendu)

    for a in articles:
        remplacement = UOM_SUSPECTES.get(a.stock_uom)
        if not remplacement:
            continue
        if frappe.db.exists("Stock Ledger Entry", {"item_code": a.name}):
            jrn.alerte(f"{a.name} : unité « {a.stock_uom} » fausse, mais des "
                       f"mouvements existent. Correction manuelle requise.")
            continue
        jrn.alerte(f"{a.name} : unité « {a.stock_uom} » — unité de force, pas de "
                   f"masse. À remplacer par « {remplacement} ». Aucun mouvement "
                   f"n'existant, la correction est sans risque aujourd'hui.")

    if sans_regle:
        jrn.alerte(f"{len(sans_regle)} article(s) hors du référentiel SYSCOHADA. "
                   f"Ils continueront de tomber dans le compte d'attente.")
        for s in sans_regle:
            jrn.info("  " + s)

    return {"deplaces": deplaces, "sans_regle": len(sans_regle)}


# =============================================================================
# ÉTAPE 7 — retirer les affectations par article périmées
# =============================================================================

def etape_purger_defauts(jrn, apply_mode, societe=None):
    """Supprime les Item Default qui masquent la famille.

    Le niveau article l'emporte sur le niveau famille : tant que les
    anciennes lignes 6011/7011 posées en bloc subsistent, la famille ne
    sert à rien. Une ligne n'est retirée que si la famille de l'article
    porte bien un compte pour cette société, faute de quoi l'article
    retomberait dans le compte d'attente.
    """
    jrn.titre("ÉTAPE 7 — Purge des affectations par article")

    lignes = frappe.get_all(
        "Item Default", filters={"parenttype": "Item"},
        fields=["name", "parent", "company", "expense_account"],
    )
    retirees, gardees = 0, 0

    for l in lignes:
        if societe and l.company != societe:
            continue
        famille = frappe.db.get_value("Item", l.parent, "item_group")
        if famille not in FAMILLES:
            gardees += 1
            continue

        grp = frappe.get_doc("Item Group", famille)
        couvert = any(d.company == l.company and d.expense_account
                      for d in (grp.item_group_defaults or []))
        if not couvert:
            jrn.info(f"{l.parent} · {l.company} : famille « {famille} » sans "
                     f"compte pour cette société, ligne conservée.")
            gardees += 1
            continue

        jrn.action(f"{l.parent} · {l.company} : retrait de l'affectation par "
                   f"article ({l.expense_account}), la famille prend le relais.")
        if apply_mode:
            frappe.delete_doc("Item Default", l.name, ignore_permissions=True)
        retirees += 1

    jrn.info(f"{retirees} ligne(s) à retirer, {gardees} conservée(s).")
    return {"retirees": retirees, "gardees": gardees}


# =============================================================================
# ÉTAPE 8 — remettre la TVA facturée au passif
# =============================================================================

def etape_tva_facturee(jrn, apply_mode, societe=None):
    """Déplace la rubrique 443 de la branche ACTIF vers la branche PASSIF.

    Le modèle « Syscohada - Plan Comptable avec code » place « État, TVA
    facturée » sous la branche actif. La TVA collectée est une dette envers
    l'État : portée à l'actif, elle gonfle l'actif et minore le passif du
    même montant. Le défaut se reproduira sur chaque société créée depuis
    ce modèle.

    Le déplacement n'est tenté que si aucun compte de la rubrique ne porte
    d'écriture, dans aucune société : ERPNext refuse la manœuvre sinon, et
    forcer la main n'aurait aucun sens comptable.
    """
    jrn.titre("ÉTAPE 8 — Rubrique TVA facturée au passif")

    deplaces = 0
    for soc in societes(societe):
        for num, branche in RUBRIQUES_A_DEPLACER.items():
            groupe = frappe.db.get_value(
                "Account", {"account_number": num, "company": soc, "is_group": 1},
                ["name", "root_type", "parent_account"], as_dict=True
            )
            if not groupe:
                continue
            if groupe.root_type == "Liability":
                continue

            enfants = frappe.get_all("Account",
                                     filters={"parent_account": groupe.name},
                                     pluck="name")
            portant = [e for e in enfants if a_des_ecritures(e)]
            if portant:
                jrn.alerte(
                    f"{soc} · rubrique {num} : {len(portant)} compte(s) portent "
                    f"déjà des écritures ({', '.join(portant[:3])}). Déplacement "
                    f"refusé. La correction devra se faire par transfert de "
                    f"solde, sous contrôle du comptable."
                )
                continue

            cible = frappe.db.get_value(
                "Account", {"account_name": branche, "company": soc, "is_group": 1},
                "name"
            )
            if not cible:
                jrn.alerte(f"{soc} : branche « {branche} » introuvable.")
                continue

            jrn.action(f"{soc} · {groupe.name} : rattachement à {cible} "
                       f"(actif -> passif)")
            deplaces += 1
            if apply_mode:
                d = frappe.get_doc("Account", groupe.name)
                d.parent_account = cible
                d.save(ignore_permissions=True)

    jrn.info(f"{deplaces} rubrique(s) à déplacer.")
    if deplaces:
        jrn.info("Le modèle de plan comptable lui-même reste à corriger, sans "
                 "quoi chaque nouvelle société reproduira le défaut.")
    return {"deplaces": deplaces}


# =============================================================================
# ÉTAPES 9 et 10 — le compte d'attente
# =============================================================================

def _lignes_attente(societe=None):
    comptes = frappe.get_all("Account",
                             filters={"account_number": COMPTE_ATTENTE_NUM},
                             fields=["name", "company"])
    if societe:
        comptes = [c for c in comptes if c.company == societe]
    if not comptes:
        return []
    return frappe.get_all(
        "GL Entry",
        filters={"account": ["in", [c.name for c in comptes]], "is_cancelled": 0},
        fields=["name", "company", "account", "debit", "credit",
                "voucher_type", "voucher_no", "posting_date", "party"],
        order_by="posting_date asc",
    )


def etape_inventaire_6589(jrn, apply_mode, societe=None):
    """Lecture seule. Ce qui dort dans le compte d'attente, et depuis quand."""
    jrn.titre("ÉTAPE 9 — Inventaire du compte d'attente")

    lignes = _lignes_attente(societe)
    if not lignes:
        jrn.info("Le compte d'attente est vide. Rien à régulariser.")
        return {"lignes": 0, "montant": 0}

    total = {}
    for l in lignes:
        total[l.company] = total.get(l.company, 0) + (l.debit - l.credit)
        jrn.info(f"{l.company} · {l.posting_date} · {l.voucher_no} · "
                 f"{l.debit - l.credit:,.0f} FCFA")

    for soc, m in total.items():
        jrn.alerte(f"{soc} : {m:,.0f} FCFA en attente d'imputation. Ce montant "
                   f"est hors de la marge commerciale tant qu'il y reste.")

    return {"lignes": len(lignes), "montant": sum(total.values()), "parSociete": total}


def etape_regulariser_6589(jrn, apply_mode, societe=None):
    """Produit une écriture de journal EN BROUILLON par société.

    Jamais validée. Le comptable relit chaque ligne, corrige l'imputation
    si la famille proposée ne convient pas, puis valide lui-même.

    Souvent préférable quand les pièces sont du jour et qu'aucun tiers ne
    les a vues : annuler et rectifier les factures d'origine, ce qui laisse
    le compte d'attente sans aucun mouvement. Ce script ne le fait pas —
    annuler une facture validée n'est pas une décision de script.
    """
    jrn.titre("ÉTAPE 10 — Brouillons de reclassement")

    lignes = _lignes_attente(societe)
    if not lignes:
        jrn.info("Rien à régulariser.")
        return {"brouillons": 0}

    par_societe = {}
    for l in lignes:
        par_societe.setdefault(l.company, []).append(l)

    produits = 0
    for soc, lots in par_societe.items():
        montant = sum(l.debit - l.credit for l in lots)
        if abs(montant) < 1:
            continue

        attente = compte_imputable(COMPTE_ATTENTE_NUM, soc)
        cible = compte_imputable(FAMILLES["31 - Marchandises"]["achat"], soc)
        if not attente or not cible:
            jrn.alerte(f"{soc} : compte d'attente ou compte cible introuvable, "
                       f"brouillon non produit.")
            continue

        jrn.action(f"{soc} : brouillon de {montant:,.0f} FCFA "
                   f"({attente} -> {cible}), sur {len(lots)} pièce(s).")
        produits += 1
        if not apply_mode:
            continue

        je = frappe.get_doc({
            "doctype": "Journal Entry",
            "voucher_type": "Journal Entry",
            "company": soc,
            "posting_date": nowdate(),
            "user_remark": (
                "À ARBITRER AVANT VALIDATION — reclassement des charges logées "
                "en {} faute d'affectation d'article. Vérifier la nature réelle "
                "de chaque pièce : le compte proposé est celui des achats de "
                "marchandises.\nPièces : {}"
            ).format(COMPTE_ATTENTE_NUM,
                     ", ".join(sorted({l.voucher_no for l in lots}))),
            "accounts": [
                {"account": cible, "debit_in_account_currency": montant},
                {"account": attente, "credit_in_account_currency": montant},
            ],
        })
        je.insert(ignore_permissions=True)   # docstatus 0 — jamais submit
        jrn.info(f"  brouillon {je.name} créé, non validé.")

    return {"brouillons": produits}


# =============================================================================
# ÉTAPE 11 — la chaîne d'inventaire permanent est-elle complète
# =============================================================================

def etape_controle_stock(jrn, apply_mode, societe=None):
    """Lecture seule. Vérifie que l'inventaire permanent peut fonctionner.

    La Direction a retenu l'inventaire permanent. Il ne suffit pas de
    l'activer : il faut que les comptes soient typés, que les entrepôts
    soient rattachés, et surtout que les mouvements de stock existent.
    Un inventaire permanent sans mouvement n'est pas un inventaire
    permanent, c'est un inventaire absent.
    """
    jrn.titre("ÉTAPE 11 — Contrôle de la chaîne d'inventaire permanent")

    nb_fact = frappe.db.count("Purchase Invoice", {"docstatus": 1})
    nb_maj = frappe.db.count("Purchase Invoice", {"docstatus": 1, "update_stock": 1})
    nb_rec = frappe.db.count("Purchase Receipt", {"docstatus": 1})
    nb_sle = frappe.db.count("Stock Ledger Entry", {"is_cancelled": 0})

    jrn.info(f"Factures d'achat validées : {nb_fact}")
    jrn.info(f"dont avec mise à jour du stock : {nb_maj}")
    jrn.info(f"Bons de réception validés : {nb_rec}")
    jrn.info(f"Mouvements de stock vivants : {nb_sle}")

    livre = frappe.db.get_single_value("Accounts Settings",
                                       "book_stock_expense_gl_entries")

    if nb_fact and not (nb_maj or nb_rec):
        jrn.alerte(
            "Aucun mouvement de stock n'existe alors que l'inventaire permanent "
            "est retenu. Les achats passent en charge, le compte de stock reste "
            "à zéro, et il n'y aura rien à valoriser à la clôture."
        )
        if livre:
            jrn.alerte(
                "Cause probable : la double écriture d'achat est active alors "
                "que les comptes qu'elle exige sont vides. ERPNext refuse donc "
                "toute entrée en stock, et la seule façon de valider une facture "
                "était de décocher « mettre à jour le stock ». L'étape "
                "`societe_stock` corrige cela."
            )

    for soc in societes(societe):
        if not frappe.db.get_value("Company", soc, "enable_perpetual_inventory"):
            jrn.alerte(f"{soc} : inventaire permanent désactivé.")
            continue
        manques = []
        champs = ["default_inventory_account", "stock_adjustment_account",
                  "stock_received_but_not_billed"]
        if livre:
            champs += ["purchase_expense_account", "purchase_expense_contra_account",
                       "expenses_added_to_stock_account",
                       "expenses_added_to_stock_contra_account"]
        for champ in champs:
            if not champ_existe("Company", champ):
                continue
            if not frappe.db.get_value("Company", soc, champ):
                manques.append(champ)
        mal_types = []
        for w in frappe.get_all("Warehouse",
                                filters={"company": soc, "is_group": 0},
                                fields=["warehouse_name", "account"]):
            if not w.account:
                mal_types.append(f"{w.warehouse_name} (aucun compte)")
            elif frappe.db.get_value("Account", w.account, "account_type") != "Stock":
                mal_types.append(f"{w.warehouse_name} ({w.account} non typé Stock)")

        if manques:
            jrn.alerte(f"{soc} : réglage(s) manquant(s) — {', '.join(manques)}")
        if mal_types:
            jrn.alerte(f"{soc} : entrepôt(s) non exploitables — "
                       f"{'; '.join(mal_types[:4])}")
        if not manques and not mal_types:
            jrn.info(f"{soc} : chaîne d'inventaire permanent complète.")

    return {"factures": nb_fact, "avec_stock": nb_maj,
            "receptions": nb_rec, "mouvements": nb_sle}


# =============================================================================
# GARDES — à brancher dans hooks.py
# =============================================================================

def bloquer_compte_attente(doc, method=None):
    """Interdit de VALIDER une pièce dont une ligne tombe dans le compte d'attente.

    Posée sur before_submit, donc la saisie reste possible : le gérant
    enregistre son brouillon à 18 heures, et c'est la comptabilisation qui
    attend que le compte soit juste.

    Le rôle Comptable Sitiame n'en est pas exempté : une pièce mal imputée
    reste mal imputée, quel que soit celui qui la valide.
    """
    attente = compte_imputable(COMPTE_ATTENTE_NUM, doc.company)
    if not attente:
        return

    champ = "expense_account" if doc.doctype == "Purchase Invoice" else "income_account"
    fautives = [(l.idx, l.get("item_code"), l.get("item_group"))
                for l in (doc.get("items") or []) if l.get(champ) == attente]
    if not fautives:
        return

    detail = "\n".join(f"  · ligne {i} : {c} (famille « {g} »)" for i, c, g in fautives)
    frappe.throw(
        "Cette pièce ne peut pas être validée : {} ligne(s) tombent dans le "
        "compte {} « Charges en attente d'imputation », faute d'affectation.\n\n"
        "{}\n\n"
        "Le compte se renseigne sur la famille de l'article "
        "(Stock › Famille d'articles › Valeurs par défaut), pas ligne à ligne. "
        "Corrigez la famille, rouvrez la pièce : le compte se remplira seul. "
        "Le brouillon est conservé.".format(len(fautives), COMPTE_ATTENTE_NUM, detail),
        title="Affectation comptable manquante",
    )


def provisionner_profil_societe(doc, method=None):
    """À la création d'une société, propose son profil et le signale.

    Branché sur Company.after_insert. N'écrit que la proposition : le
    chargé d'affaires valide, et sa validation est horodatée. Une société
    ouverte sans profil validé fonctionnera, mais ses articles tomberont
    dans le compte d'attente — ce qui est voulu : mieux vaut un blocage
    visible le premier jour qu'un plan de comptes faux pendant trois ans.
    """
    if not champ_existe("Company", "sitiame_profil_comptable"):
        return
    if doc.get("sitiame_profil_comptable"):
        return

    p = proposer_profil(doc.get("sitiame_activite_rccm"))
    frappe.db.set_value("Company", doc.name, {
        "sitiame_profil_propose": p["profil"] or "",
        "sitiame_profil_justification": p["motif"],
    })

    if p["profil"]:
        frappe.msgprint(
            f"Profil comptable proposé pour {doc.name} : <b>{p['profil']}</b> "
            f"(certitude {p['certitude']:.0%}).<br>{p['motif']}<br><br>"
            f"Reprenez-le dans le champ « Profil comptable (validé) » s'il "
            f"correspond à l'activité réelle. Tant qu'il n'est pas validé, "
            f"les achats de cette société tomberont dans le compte "
            f"d'attente.",
            title="Profil d'activité à valider", indicator="orange",
        )
    else:
        frappe.msgprint(
            f"Aucun profil comptable n'a pu être proposé pour {doc.name}.<br>"
            f"{p['motif']}<br><br>Renseignez l'activité déclarée au RCCM, "
            f"puis choisissez le profil à la main.",
            title="Profil d'activité manquant", indicator="red",
        )


def consigner_validation_profil(doc, method=None):
    """Horodate et signe la validation du profil comptable.

    Branché sur Company.on_update. Le profil commande le compte d'achat,
    le compte de vente et la TVA facturée : savoir qui l'a arrêté, et
    quand, fait partie de la piste d'audit que l'AUDCIF attend.
    """
    if not champ_existe("Company", "sitiame_profil_comptable"):
        return
    nouveau = doc.get("sitiame_profil_comptable")
    if not nouveau:
        return

    avant = doc.get_doc_before_save()
    ancien = avant.get("sitiame_profil_comptable") if avant else None
    if ancien == nouveau:
        return

    frappe.db.set_value("Company", doc.name, {
        "sitiame_profil_valide_par": frappe.session.user,
        "sitiame_profil_valide_le": nowdate(),
    }, update_modified=False)

    if ancien:
        frappe.msgprint(
            f"Profil comptable modifié : « {ancien} » devient « {nouveau} ». "
            f"Les écritures déjà passées gardent leurs comptes d'origine ; "
            f"seules les pièces à venir suivront le nouveau profil. Si "
            f"l'activité a réellement changé, le changement de comptes doit "
            f"être documenté dans l'annexe des états financiers.",
            title="Changement de profil comptable", indicator="orange",
        )


def exiger_mouvement_stock(doc, method=None):
    """Sous inventaire permanent, un achat stocké doit mouvementer le stock.

    Refuse de valider une facture d'achat qui contient des articles
    stockables sans mise à jour du stock et sans bon de réception. C'est
    la garde qui donne un sens à la décision de rester en inventaire
    permanent : sans elle, le compte de stock reste vide et la clôture est
    impossible à établir.
    """
    if doc.doctype != "Purchase Invoice":
        return
    if not frappe.db.get_value("Company", doc.company, "enable_perpetual_inventory"):
        return
    if doc.get("update_stock"):
        return
    if doc.get("is_return"):
        return

    stockes = []
    for l in (doc.get("items") or []):
        if not l.get("item_code"):
            continue
        if not frappe.db.get_value("Item", l.item_code, "is_stock_item"):
            continue
        if l.get("purchase_receipt"):   # la réception a déjà mouvementé le stock
            continue
        stockes.append((l.idx, l.item_code))

    if not stockes:
        return

    detail = "\n".join(f"  · ligne {i} : {c}" for i, c in stockes)
    frappe.throw(
        "Cette facture contient {} article(s) de stock, mais aucun mouvement "
        "de stock ne lui est rattaché. En inventaire permanent, la marchandise "
        "doit entrer en magasin au moment où elle est comptabilisée, sinon le "
        "compte de stock reste vide et la clôture ne peut pas être établie.\n\n"
        "{}\n\n"
        "Deux manières de corriger, au choix : cocher « Mettre à jour le stock » "
        "sur cette facture, ou saisir d'abord un bon de réception et rattacher "
        "la facture à ce bon. Le brouillon est conservé.".format(
            len(stockes), detail),
        title="Mouvement de stock manquant",
    )


# =============================================================================
# POINT D'ENTRÉE
# =============================================================================

def main(apply=False, societe=None, etapes=None, rapport=None):
    """Lance le correctif. Sans apply=True, rien n'est écrit."""
    jrn = Journal(apply)

    print("\n" + "#" * 78)
    print("# PME360 — référentiel SYSCOHADA des articles et inventaire permanent")
    print("# Version 2.0")
    print("# Mode : " + ("APPLICATION RÉELLE" if apply else "SIMULATION (aucune écriture)"))
    print("# Société : " + (societe or "toutes"))
    print("#" * 78)

    garde_fous(jrn)

    if societe and not frappe.db.exists("Company", societe):
        frappe.throw(f"Société inconnue : {societe}")

    a_faire = etapes or ETAPES
    inconnues = [e for e in a_faire if e not in ETAPES]
    if inconnues:
        frappe.throw(f"Étape(s) inconnue(s) : {inconnues}. Choix : {ETAPES}")

    # Purger les affectations par article avant que les familles ne portent
    # leurs comptes renverrait tout dans le compte d'attente.
    if "purger_defauts" in a_faire and "familles" not in a_faire:
        if not frappe.db.exists("Item Group", next(iter(FAMILLES))):
            frappe.throw(
                "L'étape `purger_defauts` exige que les familles existent et "
                "portent leurs comptes. Appliquez d'abord `familles`."
            )

    # Rattacher un entrepôt à un compte non typé « Stock » est refusé par
    # ERPNext : le typage doit précéder.
    if "entrepots" in a_faire and "typer_stock" not in a_faire:
        jrn.info("Rappel : l'étape `entrepots` suppose que `typer_stock` a déjà "
                 "été appliquée, sans quoi ERPNext refusera le rattachement.")

    resultats = {}
    table = {
        "champs_rccm":      etape_champs_rccm,
        "profil":           etape_profil,
        "comptes":          etape_comptes,
        "typer_stock":      etape_typer_stock,
        "entrepots":        etape_entrepots,
        "societe_stock":    etape_societe_stock,
        "familles":         etape_familles,
        "reclasser":        etape_reclasser,
        "purger_defauts":   etape_purger_defauts,
        "tva_facturee":     etape_tva_facturee,
        "inventaire_6589":  etape_inventaire_6589,
        "regulariser_6589": etape_regulariser_6589,
        "controle_stock":   etape_controle_stock,
    }
    for nom in ETAPES:
        if nom in a_faire:
            resultats[nom] = table[nom](jrn, apply, societe)

    print("\n" + "=" * 78)
    if apply:
        frappe.db.commit()
        print("Modifications enregistrées.")
    else:
        frappe.db.rollback()
        print("SIMULATION — transaction annulée, aucune écriture conservée.")
    print("=" * 78)

    if jrn.anomalies:
        print(f"\n{len(jrn.anomalies)} point(s) d'attention :")
        for a in jrn.anomalies:
            print("  /!\\ " + a)

    chemin = rapport or os.path.join(
        frappe.get_site_path(),
        f"correctif_affectation_{nowdate()}_{'apply' if apply else 'simulation'}.json",
    )
    jrn.rapport(chemin)
    return resultats
