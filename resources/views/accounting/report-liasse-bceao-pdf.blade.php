<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liasse Fiscale BCEAO / SYSCOHADA - {{ $companyName }}</title>
    <style>
        @page {
            margin: 12mm 10mm 15mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            line-height: 1.25;
        }
        .header-box {
            border: 1px solid #1a252f;
            padding: 6px 10px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
        }
        .header-title {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            color: #1a252f;
            margin-bottom: 2px;
        }
        .header-subtitle {
            text-align: center;
            font-size: 9px;
            color: #555;
        }
        .info-table {
            width: 100%;
            margin-bottom: 8px;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 2px 4px;
            font-size: 8.5px;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            background-color: #2c3e50;
            color: #ffffff;
            padding: 4px 6px;
            margin-top: 10px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data-table th, table.data-table td {
            border: 0.5pt solid #888;
            padding: 3px 4px;
            font-size: 8.5px;
        }
        table.data-table th {
            background-color: #ecf0f1;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .table-total {
            background-color: #fcf8e3;
            font-weight: bold;
        }
        .table-grand-total {
            background-color: #2c3e50;
            color: #ffffff;
            font-weight: bold;
        }
        .page-break {
            page-break-after: always;
        }
        .footer-qr {
            float: right;
            width: 60px;
            height: 60px;
        }
        .badge {
            display: inline-block;
            padding: 2px 4px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 3px;
        }
        .badge-success { background-color: #d4edda; color: #155724; }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-danger { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <!-- PAGE 1 : BILAN ACTIF -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - BILAN ACTIF</div>
        <div class="header-subtitle">Système Normal SYSCOHADA Révisé — Exercice {{ $exerciseYear }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Raison Sociale :</strong> {{ $companyName }} {{ $companySigle ? '('.$companySigle.')' : '' }}</td>
            <td class="text-end"><strong>Exercice clos le :</strong> {{ $exerciseEnd ?: '31/12/'.$exerciseYear }}</td>
        </tr>
        <tr>
            <td><strong>NIF / N° Impôt :</strong> {{ $companyTaxId ?: 'N/A' }}</td>
            <td class="text-end"><strong>Durée (en mois) :</strong> {{ $durationMonths ?: '12' }}</td>
        </tr>
    </table>

    <div class="section-title">1. BILAN ACTIF</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 7%">Réf.</th>
                <th>ACTIF</th>
                <th style="width: 16%">Brut N</th>
                <th style="width: 16%">Amort. / Prov.</th>
                <th style="width: 16%">Net N</th>
                <th style="width: 16%">Net N-1</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['actif']['rows'] as $ref => $row)
                <tr class="{{ !empty($row['is_total']) ? 'table-total' : '' }}">
                    <td class="text-center fw-bold">{{ $ref }}</td>
                    <td>{{ $row['libelle'] }}</td>
                    <td class="text-end">{{ number_format($row['brut'], 0, ',', ' ') }}</td>
                    <td class="text-end">{{ number_format($row['prov'], 0, ',', ' ') }}</td>
                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                    <td class="text-end">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            <tr class="table-grand-total">
                <td class="text-center">AZ+BZ+CZ</td>
                <td>{{ $liasse['actif']['total']['libelle'] }}</td>
                <td class="text-end">{{ number_format($liasse['actif']['total']['brut'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['actif']['total']['prov'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['actif']['total']['net_n'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['actif']['total']['net_n1'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 2 : BILAN PASSIF -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - BILAN PASSIF</div>
        <div class="header-subtitle">Système Normal SYSCOHADA Révisé — Exercice {{ $exerciseYear }}</div>
    </div>

    <table class="info-table">
        <tr>
            <td><strong>Raison Sociale :</strong> {{ $companyName }}</td>
            <td class="text-end"><strong>Exercice clos le :</strong> {{ $exerciseEnd ?: '31/12/'.$exerciseYear }}</td>
        </tr>
    </table>

    <div class="section-title">2. BILAN PASSIF</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%">Réf.</th>
                <th>PASSIF</th>
                <th style="width: 25%">Exercice N</th>
                <th style="width: 25%">Exercice N-1</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['passif']['rows'] as $ref => $row)
                <tr class="{{ !empty($row['is_total']) ? 'table-total' : '' }}">
                    <td class="text-center fw-bold">{{ $ref }}</td>
                    <td>{{ $row['libelle'] }}</td>
                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                    <td class="text-end">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            <tr class="table-grand-total">
                <td class="text-center">CZ+DZ+EZ+FZ</td>
                <td>{{ $liasse['passif']['total']['libelle'] }}</td>
                <td class="text-end">{{ number_format($liasse['passif']['total']['net_n'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['passif']['total']['net_n1'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 3 : COMPTE DE RÉSULTAT -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - COMPTE DE RÉSULTAT</div>
        <div class="header-subtitle">Système Normal SYSCOHADA Révisé — Exercice {{ $exerciseYear }}</div>
    </div>

    <div class="section-title">3. COMPTE DE RÉSULTAT</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 8%">Réf.</th>
                <th>RUBRIQUES DE RÉSULTAT</th>
                <th style="width: 25%">Exercice N</th>
                <th style="width: 25%">Exercice N-1</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['resultat']['rows'] as $ref => $row)
                <tr class="{{ !empty($row['is_total']) ? 'table-total' : '' }}">
                    <td class="text-center fw-bold">{{ $ref }}</td>
                    <td>{{ $row['libelle'] }}</td>
                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                    <td class="text-end">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
            <tr style="background-color:#d5dbdb;"><td colspan="4" class="text-center fw-bold">SOLDES INTERMÉDIAIRES DE GESTION (SIG)</td></tr>
            @foreach($liasse['resultat']['totals'] as $ref => $row)
                <tr class="{{ $ref === 'XZ' ? 'table-grand-total' : 'table-total' }}">
                    <td class="text-center fw-bold">{{ $ref }}</td>
                    <td>{{ $row['libelle'] }}</td>
                    <td class="text-end fw-bold">{{ number_format($row['net_n'], 0, ',', ' ') }}</td>
                    <td class="text-end">{{ number_format($row['net_n1'], 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 4 : TAFIRE (TABLEAU DE FINANCEMENT) -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - TAFIRE</div>
        <div class="header-subtitle">Tableau de Financement des Ressources et Emplois — Exercice {{ $exerciseYear }}</div>
    </div>

    @php $t = $liasse['tafire']; @endphp
    <div class="section-title">4. CAPACITÉ D'AUTOFINANCEMENT GLOBALE (CAFG)</div>
    <table class="data-table">
        <tbody>
            <tr><td>Résultat net de l'exercice</td><td class="text-end fw-bold">{{ number_format($t['resultat_net'], 0, ',', ' ') }}</td></tr>
            <tr><td>(+) Dotations aux amortissements et provisions</td><td class="text-end">{{ number_format($t['dot_amort'], 0, ',', ' ') }}</td></tr>
            <tr><td>(−) Reprises sur provisions et amortissements</td><td class="text-end">{{ number_format($t['reprises_amort'], 0, ',', ' ') }}</td></tr>
            <tr><td>(−) Produits de cessions d'actifs immobilisés HAO</td><td class="text-end">{{ number_format($t['prod_cessions_hao'], 0, ',', ' ') }}</td></tr>
            <tr><td>(+) Valeurs comptables des cessions d'actifs HAO</td><td class="text-end">{{ number_format($t['val_compt_cessions'], 0, ',', ' ') }}</td></tr>
            <tr class="table-grand-total"><td>= CAFG (Capacité d'Autofinancement Globale)</td><td class="text-end">{{ number_format($t['cafg'], 0, ',', ' ') }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">5. RESSOURCEMENT ET EMPLOIS STABLES</div>
    <table class="data-table">
        <thead>
            <tr><th style="width:50%">RESSOURCES STABLES</th><th style="width:50%">EMPLOIS STABLES</th></tr>
        </thead>
        <tbody>
            <tr>
                <td style="vertical-align:top; padding:0;">
                    <table class="data-table" style="margin:0; border:none;">
                        <tr><td>CAFG</td><td class="text-end">{{ number_format($t['cafg'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Cessions d'immobilisations</td><td class="text-end">{{ number_format($t['cessions_immob'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Augmentation de capital</td><td class="text-end">{{ number_format($t['augment_capital'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Nouveaux emprunts contractés</td><td class="text-end">{{ number_format($t['emprunts_nouveaux'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Subventions d'investissement</td><td class="text-end">{{ number_format($t['subventions_inv'], 0, ',', ' ') }}</td></tr>
                        <tr class="table-total"><td>TOTAL RESSOURCES</td><td class="text-end">{{ number_format($t['total_ressources'], 0, ',', ' ') }}</td></tr>
                    </table>
                </td>
                <td style="vertical-align:top; padding:0;">
                    <table class="data-table" style="margin:0; border:none;">
                        <tr><td>Acquisitions immo. corporelles</td><td class="text-end">{{ number_format($t['acq_immo_corpo'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Acquisitions immo. incorporelles</td><td class="text-end">{{ number_format($t['acq_immo_incorpo'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Acquisitions immo. financières</td><td class="text-end">{{ number_format($t['acq_immo_financ'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Remboursements d'emprunts</td><td class="text-end">{{ number_format($t['remb_emprunts'], 0, ',', ' ') }}</td></tr>
                        <tr><td>Dividendes distribués</td><td class="text-end">{{ number_format($t['dividendes'], 0, ',', ' ') }}</td></tr>
                        <tr class="table-total"><td>TOTAL EMPLOIS</td><td class="text-end">{{ number_format($t['total_emplois'], 0, ',', ' ') }}</td></tr>
                    </table>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">6. SYNTHÈSE DE LA VARIATION DU FRNG & TRÉSORERIE</div>
    <table class="data-table">
        <tbody>
            <tr class="table-total"><td>VARIATION DU FONDS DE ROULEMENT NET GLOBAL (FRNG = Ressources - Emplois)</td><td class="text-end fw-bold">{{ number_format($t['variation_frng'], 0, ',', ' ') }}</td></tr>
            <tr><td>BFR (Besoin en Fonds de Roulement) Exercice N</td><td class="text-end">{{ number_format($t['bfr_n'], 0, ',', ' ') }}</td></tr>
            <tr><td>BFR Exercice N-1</td><td class="text-end">{{ number_format($t['bfr_n1'], 0, ',', ' ') }}</td></tr>
            <tr class="table-total"><td>VARIATION DU BFR (BFR N - BFR N-1)</td><td class="text-end">{{ number_format($t['variation_bfr'], 0, ',', ' ') }}</td></tr>
            <tr><td>Trésorerie Nette Exercice N</td><td class="text-end">{{ number_format($t['treso_nette_n'], 0, ',', ' ') }}</td></tr>
            <tr><td>Trésorerie Nette Exercice N-1</td><td class="text-end">{{ number_format($t['treso_nette_n1'], 0, ',', ' ') }}</td></tr>
            <tr class="table-total"><td>VARIATION DE LA TRÉSORERIE NETTE</td><td class="text-end">{{ number_format($t['variation_treso'], 0, ',', ' ') }}</td></tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 5 : AMORTISSEMENTS ET PROVISIONS -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - AMORTISSEMENTS & PROVISIONS</div>
        <div class="header-subtitle">Tableaux synthétiques — Exercice {{ $exerciseYear }}</div>
    </div>

    <div class="section-title">7. TABLEAU DES AMORTISSEMENTS</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Catégorie d'Immobilisation</th>
                <th>Base Amortissable</th>
                <th>Amort. Cumulés</th>
                <th>Valeur Nette Comptable</th>
                <th>Taux Moy.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['amortissements']['rows'] as $row)
            <tr>
                <td>{{ $row['categorie'] }}</td>
                <td class="text-end">{{ number_format($row['base'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['amort_cumule'], 0, ',', ' ') }}</td>
                <td class="text-end fw-bold">{{ number_format($row['vnc'], 0, ',', ' ') }}</td>
                <td class="text-center">{{ $row['taux_moyen'] }}%</td>
            </tr>
            @endforeach
            <tr class="table-grand-total">
                <td>TOTAL AMORTISSEMENTS</td>
                <td class="text-end">{{ number_format($liasse['amortissements']['totaux']['base'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['amortissements']['totaux']['amort_cumule'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['amortissements']['totaux']['vnc'], 0, ',', ' ') }}</td>
                <td class="text-center">{{ $liasse['amortissements']['totaux']['taux_moyen'] }}%</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">8. TABLEAU DES PROVISIONS CONSTITUÉES</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Nature des Provisions</th>
                <th style="width: 35%">Montant (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['provisions']['rows'] as $row)
            <tr>
                <td>{{ $row['libelle'] }}</td>
                <td class="text-end fw-bold">{{ number_format($row['montant'], 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            <tr class="table-grand-total">
                <td>TOTAL PROVISIONS</td>
                <td class="text-end">{{ number_format($liasse['provisions']['totaux']['total'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 6 : NOTES SUR LES IMMOBILISATIONS -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - NOTES SUR LES IMMOBILISATIONS</div>
        <div class="header-subtitle">Tableau des Mouvements bruts et amortissements — Exercice {{ $exerciseYear }}</div>
    </div>

    <div class="section-title">9. TABLEAU DES IMMOBILISATIONS (MOUVEMENTS DE L'EXERCICE)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2">Rubriques</th>
                <th colspan="4">Valeurs Brutes</th>
                <th colspan="3">Amortissements</th>
                <th rowspan="2">VNC N</th>
            </tr>
            <tr>
                <th>Brut N-1</th>
                <th>Acq.</th>
                <th>Cess.</th>
                <th>Brut N</th>
                <th>Amort N-1</th>
                <th>Dot N</th>
                <th>Amort N</th>
            </tr>
        </thead>
        <tbody>
            @foreach($liasse['immobilisations']['rows'] as $row)
            <tr>
                <td>{{ $row['libelle'] }}</td>
                <td class="text-end">{{ number_format($row['brutN1'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['acq'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['cess'], 0, ',', ' ') }}</td>
                <td class="text-end fw-bold">{{ number_format($row['brutN'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['depN1'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['dotN'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($row['depN'], 0, ',', ' ') }}</td>
                <td class="text-end fw-bold">{{ number_format($row['netN'], 0, ',', ' ') }}</td>
            </tr>
            @endforeach
            <tr class="table-grand-total">
                <td>TOTAL IMMOBILISATIONS</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['brutN1'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['acq'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['cess'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['brutN'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['depN1'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['dotN'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['depN'], 0, ',', ' ') }}</td>
                <td class="text-end">{{ number_format($liasse['immobilisations']['totaux']['netN'], 0, ',', ' ') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="page-break"></div>

    <!-- PAGE 7+ : ÉTATS ANNEXES BCEAO (EA 1 à EA 13) -->
    <div class="header-box">
        <div class="header-title">LIASSE FISCALE BCEAO - ÉTATS ANNEXES</div>
        <div class="header-subtitle">Notes annexes obligatoires EA 1 à EA 13 — Exercice {{ $exerciseYear }}</div>
    </div>

    @foreach($liasse['annexes'] as $key => $annexe)
        <div class="section-title">NOTE {{ $key }} : {{ strtoupper($annexe['titre']) }}</div>
        @if(!empty($annexe['description']))
            <p style="font-style:italic; color:#555;">{{ $annexe['description'] }}</p>
        @endif

        @if(isset($annexe['items']) && is_array($annexe['items']))
            <table class="data-table">
                @foreach($annexe['items'] as $label => $val)
                <tr>
                    <td style="width:60%">{{ $label }}</td>
                    <td class="text-end fw-bold">
                        @if(is_numeric($val) && !is_string($val))
                            {{ number_format((float)$val, 0, ',', ' ') }}
                        @else
                            {{ $val }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
        @endif

        @if(isset($annexe['rows']) && is_array($annexe['rows']))
            <table class="data-table">
                <thead>
                    <tr>
                        @foreach(array_keys($annexe['rows'][0] ?? []) as $col)
                            <th>{{ ucfirst(str_replace('_', ' ', $col)) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($annexe['rows'] as $row)
                    <tr>
                        @foreach($row as $col => $val)
                        <td class="{{ is_numeric($val) ? 'text-end' : '' }}">
                            @if(is_numeric($val) && !is_string($val))
                                {{ number_format((float)$val, 0, ',', ' ') }}
                            @else
                                {{ $val }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if(isset($annexe['note']))
            <p style="font-size:8px; color:#666;"><em>Note : {{ $annexe['note'] }}</em></p>
        @endif
        <br>
    @endforeach

    <br><br>
    <table style="width: 100%; border: none;">
        <tr>
            <td style="border: none;">
                <p>Certifié exact, sincère et conforme aux règles du SYSCOHADA révisé BCEAO :</p>
                <p><strong>{{ $companyName }}</strong></p>
                <p>Référence Officielle : <strong>{{ $liasseReference ?? 'BCEAO-'.date('Ymd') }}</strong></p>
            </td>
            @if(!empty($qrUrl))
                <td style="border: none; text-align: right;">
                    <img src="{{ $qrUrl }}" class="footer-qr" alt="Signature QR Code">
                </td>
            @endif
        </tr>
    </table>

</body>
</html>
