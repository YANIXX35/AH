# OCR (Reconnaissance de texte)

✅ **Bonne nouvelle: Aucune installation requise!**

L'application utilise **OCR.space API** (service gratuit en ligne) pour extraire le texte des documents.

## ✨ Fonctionnalités OCR

- 📄 Supporte: JPG, PNG, PDF
- 🌍 Gratuit et en ligne (pas d'installation)
- 🇫🇷 Support du français
- ✅ Vérification automatique des montants

## 🚀 Comment ça fonctionne

1. **Uploadez votre facture/document** dans le formulaire
2. **L'OCR extrait automatiquement le texte**
3. **Vérifie que le montant du document = montant saisi**
4. **Affiche le résultat:**
   - ✅ **Vérifié** = Montant correspond
   - ⚠️ **Ne correspond pas** = Vérifiez le montant
   - ❌ **Erreur OCR** = Format non supporté ou image illisible

## 📋 Formats acceptés

- Images: JPG, PNG
- Documents: PDF (recommandé pour les factures numérisées)

## 🔍 Procédure de vérification

L'OCR cherche les montants dans le texte:
- "TOTAL: 12345.67"
- "Montant: 100 FCFA"
- "HT" (Hors taxes)
- "TTC" (Toutes taxes comprises)

**Tolérance:** ±5% (pour les arrondis ou erreurs d'extraction)

## 🆘 Problèmes courants

### L'OCR ne détecte rien
- L'image est **trop petite** ou **floue** → Rescannez avec meilleure qualité
- Le format n'est **pas supporté** → Utilisez JPG, PNG ou PDF
- Le document est en **arabe/anglais** → L'app est configurée pour le français

### Le montant ne correspond pas
- Vérifiez le **montant saisi** vs le **montant du document**
- La **tolérance est ±5%** (pour arrondir les frais)
- Si c'est intentionnel, l'entrée sera enregistrée avec status "mismatched" ⚠️

## 💡 Conseils

1. **Qualité du scan** = Qualité de l'OCR
   - Scanner en couleur pour les factures
   - Luminosité: 60-80% (ni trop sombre, ni trop clair)
   - Résolution: minimum 200 DPI

2. **Pour les factures**
   - Préférez les PDFs numérisés proprement
   - Les scans de mauvaise qualité peuvent donner des résultats inexacts

3. **Vérification manuelle**
   - Consultez la colonne "Vérification" du tableau
   - Cliquez sur l'entrée pour voir le texte OCR extrait

## 📡 API utilisée

- **Service:** OCR.space (gratuit)
- **Limite:** 25 000 appels/jour
- **Temps réponse:** 3-5 secondes par document
- **Langues supportées:** +100 langues

---

**L'OCR fonctionne automatiquement - aucune configuration n'est nécessaire!** 🎉

