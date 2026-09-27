# Bouton « Télécharger » sur les listes ERPNext — Design

## Contexte

Demande utilisateur (2026-09-27) : pouvoir télécharger les documents saisis (factures, achats, stocks, reçus…) en PDF, Word, Excel ou CSV depuis ERPNext.

État constaté en production :
- Chaque document s'imprime déjà en PDF un par un (bouton Imprimer), mais il n'y a aucun téléchargement groupé côté PME.
- Un compte PME n'a **pas** le droit natif « Export » sur Sales Invoice, Purchase Invoice, Purchase Receipt, Delivery Note (seulement sur Payment Entry, Stock Entry, Item, Journal Entry via certains rôles). Ouvrir ce droit natif exposerait bien plus que ces listes (Data Export, Report Builder).
- Disponible sur le serveur : `openpyxl`, `pypdf`, `wkhtmltopdf`. **Absent : `python-docx`** (Word).
- `frappe.utils.print_format._download_multi_pdf` ignore silencieusement les documents qui échouent : on ne le réutilise pas.

**Décisions utilisateur** : ERPNext uniquement ; un bouton sur chaque liste (recommandé, validé).

## Architecture

### 1. Bouton (`sitiame_core/public/js/list_download.js`)

Chargé via `doctype_list_js` (après le `*_list.js` d'ERPNext, qui sinon écraserait `frappe.listview_settings[doctype]`), comme `ocr_invoice_list.js` : il étend `onload` et ajoute `page.add_inner_button(__("Telecharger"))` sur : Sales Invoice, Purchase Invoice, Payment Entry, Journal Entry, Stock Entry, Purchase Receipt, Delivery Note, Purchase Order, Sales Order, Item. Frappe accepte une liste de fichiers par doctype dans `doctype_list_js` (vérifié : `append_hook` étend une liste) — les factures et paiements gardent `ocr_invoice_list.js`.

Au clic : dialogue avec un Select « Format » (PDF, Word (.docx), Excel (.xlsx), CSV) et une zone d'information. Portée : les lignes cochées (`listview.get_checked_items(true)`) si au moins une, sinon les filtres courants (`listview.get_filters_for_args()`). À l'ouverture et à chaque changement de format, `sitiame_core.exports.count_documents` renvoie `{count, limit}` ; le dialogue affiche « N document(s) cochés / selon les filtres » ou, au-delà de la limite, « Plus de X documents : filtrez davantage ». Le bouton « Telecharger » du dialogue lance `open_url_post("/api/method/sitiame_core.exports.download", {doctype, fmt, names | filters})` (POST avec jeton CSRF) seulement si `0 < count <= limit`.

### 2. Serveur (`sitiame_core/exports.py`)

- `EXPORTS` : pour chaque doctype, `title` (libellé français du document), `file` (nom de fichier), `columns` [(fieldname, libellé)], `items` (doctype enfant, colonnes) ou absent, `stock` (Item : colonne calculée « Stock actuel »). Champs vérifiés en production le 2026-09-27.

  | Doctype | Colonnes | Lignes |
  |---|---|---|
  | Sales Invoice | N°, Date, Client, HT, TVA, TTC, Reste à payer, Statut | Article, Qté, Prix unitaire, Montant, Compte |
  | Purchase Invoice | N°, Date, Fournisseur, N° fournisseur, HT, TVA, TTC, Reste à payer, Statut | Article, Qté, Prix unitaire, Montant, Compte |
  | Payment Entry | N°, Date, Type, Tiers, Moyen de paiement, Montant, Référence, Statut | Document réglé (type, n°), Montant alloué |
  | Journal Entry | N°, Date, Type, Libellé, Total débit, Total crédit | Compte, Tiers, Débit, Crédit |
  | Stock Entry | N°, Date, Type, Valeur sortie, Valeur entrée | Code, Article, Qté, Dépôt source, Dépôt cible, Prix, Montant |
  | Purchase Receipt / Delivery Note | N°, Date, Fournisseur/Client, Total, Statut | Code, Article, Qté, Prix, Montant, Dépôt |
  | Purchase Order / Sales Order | N°, Date, Fournisseur/Client, (Livraison), Total, Statut | Article, Qté, Prix, Montant, Date prévue |
  | Item | Code, Désignation, Groupe, Unité, Stocké, Valorisation, Stock actuel | — |

- `LIMITS = {"pdf": 200, "docx": 200, "xlsx": 5000, "csv": 5000}`.
- `_names(doctype, names, filters, limit)` : `frappe.get_list(doctype, filters=[["name","in",names]] si names sinon filters, pluck="name", order_by="creation asc", limit_page_length=limit+1)` — **seule source des documents** : droits de lecture + User Permissions (société) appliqués.
- `count_documents(doctype, fmt, names=None, filters=None)` (whitelist) → `{"count": len(_names(...)), "limit": LIMITS[fmt]}`.
- `download(doctype, fmt, names=None, filters=None)` (whitelist) : doctype/format validés, `frappe.throw` si 0 document ou plus que la limite, puis `frappe.local.response` = `filename` `<file>_<AAAA-MM-JJ>.<fmt>`, `filecontent`, `type="download"`.
- `_rows` : en-têtes via `frappe.get_list` (mêmes droits) ; lignes enfants via `frappe.get_all(child, {"parent": ["in", names], "parenttype": doctype})` (parents déjà filtrés par droits) ; Item : stock = Σ `Bin.actual_qty` sur les dépôts visibles par l'utilisateur (`frappe.get_list("Warehouse")`).
- Formats :
  - **PDF** : `frappe.get_print(doctype, name, as_pdf=True, output=writer)` pour chaque document (format d'impression par défaut, un document par page), un seul fichier. Une erreur n'est pas avalée.
  - **Word** : `python-docx` — par document : titre « <title> <n°> », société, tableau libellé/valeur des colonnes, tableau des lignes ; saut de page entre documents.
  - **Excel** : `openpyxl` — onglet « Documents » (une ligne par document, nombres en nombres, dates en dates JJ/MM/AAAA), onglet « Lignes » (colonne « Document » + colonnes des lignes) ; en-tête gras, volet figé, largeurs ajustées.
  - **CSV** : séparateur `;`, UTF-8 avec BOM, nombres à virgule décimale, dates JJ/MM/AAAA (lisible tel quel par Excel en français).
- Cases à cocher (`is_stock_item`) rendues « Oui »/« Non ».

### 3. Dépendance

`python-docx` ajouté à `pyproject.toml` (`dependencies`), installé une fois dans le conteneur backend (`bench pip install python-docx`) avant le déploiement — l'image est ensuite figée par `sitiame_core_deploy.sh`. La CI (`.github/workflows/tests.yml`) installe `openpyxl python-docx`.

## Hors périmètre

PME360 ; rapports (balance, grand livre : export natif) ; envoi par email ; téléchargement en tâche de fond (limites à la place) ; choix du format d'impression.

## Vérification

- pytest : `_names` (cochés vs filtres, limite+1), `count_documents`, `download` (refus 0 / trop, réponse `download`), `build_csv` (BOM, `;`, virgule décimale, dates), `build_xlsx` et `build_docx` relus avec openpyxl/python-docx, cohérence de `EXPORTS`.
- Production : endpoints appelés en tant que compte PME (sa société seulement) pour chaque format ; bouton contrôlé dans le navigateur par l'utilisateur.
