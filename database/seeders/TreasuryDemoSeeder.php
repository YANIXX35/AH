<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TreasuryDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Récupérer le premier utilisateur existant
        $user = User::first();
        
        if (!$user) {
            $this->command->warn('Aucun utilisateur trouvé. Veuillez créer un compte d\'abord.');
            return;
        }

        $userId = $user->id;
        
        // Supprimer les anciennes données de démo
        DB::table('treasury_transactions')->where('user_id', $userId)->delete();

        // Données de démonstration pour la trésorerie
        $transactions = [
            // === ENCAISSEMENTS ===
            [
                'user_id' => $userId,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 2500000.00,
                'description' => 'Paiement facture F2026-001 - Client ABC SARL',
                'transaction_date' => Carbon::parse('2026-04-02'),
                'reference' => 'VIR-2026-001',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Virement bancaire reçu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 1800000.00,
                'description' => 'Paiement facture F2026-002 - Client XYZ Énergie',
                'transaction_date' => Carbon::parse('2026-04-05'),
                'reference' => 'VIR-2026-002',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Virement bancaire reçu',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'encaissement',
                'transaction_type' => 'Apport capital',
                'amount' => 5000000.00,
                'description' => 'Apport en capital associé',
                'transaction_date' => Carbon::parse('2026-04-01'),
                'reference' => 'CAP-2026-001',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Augmentation de capital',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // === DÉCAISSEMENTS ===
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Paiement fournisseur',
                'amount' => 1200000.00,
                'description' => 'Paiement fournisseur Fournisseur Principal Ltd',
                'transaction_date' => Carbon::parse('2026-04-03'),
                'reference' => 'CHK-2026-001',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Chèque n°001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Salaires',
                'amount' => 3500000.00,
                'description' => 'Paie salaires mois d\'avril 2026',
                'transaction_date' => Carbon::parse('2026-04-07'),
                'reference' => 'SAL-04-2026',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Virements salaires personnel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Loyer',
                'amount' => 800000.00,
                'description' => 'Loyer bureau et entrepôt avril 2026',
                'transaction_date' => Carbon::parse('2026-04-01'),
                'reference' => 'LOYER-04-2026',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Virement loyer mensuel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Frais généraux',
                'amount' => 250000.00,
                'description' => 'Facture électricité et eau',
                'transaction_date' => Carbon::parse('2026-04-04'),
                'reference' => 'UTIL-04-2026',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Services publics',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Télécommunications',
                'amount' => 150000.00,
                'description' => 'Facture télécom et internet',
                'transaction_date' => Carbon::parse('2026-04-06'),
                'reference' => 'TEL-04-2026',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'effectue',
                'notes' => 'Abonnements télécom',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // === PRÉVISIONS FUTURES ===
            [
                'user_id' => $userId,
                'type' => 'encaissement',
                'transaction_type' => 'Paiement client',
                'amount' => 3000000.00,
                'description' => 'Prévision paiement Client GHI - Facture F2026-003',
                'transaction_date' => Carbon::parse('2026-04-15'),
                'reference' => 'PREV-VIR-003',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'planifie',
                'notes' => 'Prévision encaissement',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'type' => 'decaissement',
                'transaction_type' => 'Paiement fournisseur',
                'amount' => 2000000.00,
                'description' => 'Prévision paiement Fournisseur Matériel',
                'transaction_date' => Carbon::parse('2026-04-20'),
                'reference' => 'PREV-CHK-002',
                'bank_account' => 'BICIS - Compte principal',
                'status' => 'planifie',
                'notes' => 'Prévision décaissement',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('treasury_transactions')->insert($transactions);
        
        $this->command->info('Données de démonstration de trésorerie insérées avec succès!');
    }
}
