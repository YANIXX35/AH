<?php

namespace Database\Seeders;

use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class TreasuryTransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer le premier utilisateur
        $user = User::first();
        
        if (!$user) {
            return;
        }

        $transactions = [
            // Encaissements
            [
                'user_id' => $user->id,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 500000,
                'description' => 'Paiement facture Client ABC - Janvier',
                'transaction_date' => now()->subDays(15),
                'reference' => 'FACT-2026-001',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Chèque reçu et déposé',
            ],
            [
                'user_id' => $user->id,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 650000,
                'description' => 'Paiement facture Client XYZ',
                'transaction_date' => now()->subDays(10),
                'reference' => 'FACT-2026-002',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Virement bancaire',
            ],
            [
                'user_id' => $user->id,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 400000,
                'description' => 'Paiement facture Client DEF',
                'transaction_date' => now()->subDays(5),
                'reference' => 'FACT-2026-003',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Virment bancaire en attente de confirmation',
            ],
            [
                'user_id' => $user->id,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 300000,
                'description' => 'Paiement facture Client GHI - Planifiée',
                'transaction_date' => now()->addDays(3),
                'reference' => 'FACT-2026-004',
                'bank_account' => 'Compte courant',
                'status' => 'planifie',
                'notes' => 'À recevoir sous peu',
            ],

            // Décaissements
            [
                'user_id' => $user->id,
                'type' => 'decaissement',
                'transaction_type' => 'Paiement fournisseur',
                'amount' => 200000,
                'description' => 'Paiement facture Fournisseur ABC',
                'transaction_date' => now()->subDays(12),
                'reference' => 'FOURNI-2026-001',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Chèque émis',
            ],
            [
                'user_id' => $user->id,
                'type' => 'decaissement',
                'transaction_type' => 'Frais bancaires',
                'amount' => 15000,
                'description' => 'Frais bancaires mensuels',
                'transaction_date' => now()->subDays(8),
                'reference' => 'FRAIS-2026-001',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Débité automatiquement',
            ],
            [
                'user_id' => $user->id,
                'type' => 'decaissement',
                'transaction_type' => 'Paiement fournisseur',
                'amount' => 350000,
                'description' => 'Paiement facture Fournisseur XYZ',
                'transaction_date' => now()->subDays(3),
                'reference' => 'FOURNI-2026-002',
                'bank_account' => 'Compte courant',
                'status' => 'effectue',
                'notes' => 'Virement effectué',
            ],
            [
                'user_id' => $user->id,
                'type' => 'decaissement',
                'transaction_type' => 'Paiement fournisseur',
                'amount' => 175000,
                'description' => 'Paiement facture Fournisseur DEF - Planifiée',
                'transaction_date' => now()->addDays(7),
                'reference' => 'FOURNI-2026-003',
                'bank_account' => 'Compte courant',
                'status' => 'planifie',
                'notes' => 'À payer prochainement',
            ],
        ];

        TreasuryTransaction::upsert($transactions, uniqueBy: ['user_id', 'reference'], update: ['amount', 'status']);
    }
}
