# Design Spec: Tableau Numéroté Compact des Clients

**Date** : 2026-07-28  
**Objectif** : Transformer la liste des dossiers clients en un tableau numéroté compact trié du plus récent au plus ancien.

## Modifications
1. **Tri du plus récent au plus ancien** (`created_at DESC`) pour que le nouveau client soit toujours en haut (#1).
2. **Colonne de numérotation `#`** (1, 2, 3, ...).
3. **Tableau compact unique** (suppression des espaces vides, hauteur des lignes optimisée).
