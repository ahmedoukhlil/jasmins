<?php

namespace App\Services;

use App\Models\LotMedicament;
use App\Models\MouvementStock;
use App\Models\StockMedicament;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Enregistre une entrée et maintient le stock global, le lot et le mouvement
     * dans une seule transaction.
     */
    public function addEntry(
        int $medicamentId,
        int $cabinetId,
        float $quantite,
        float $prixAchat,
        int $userId,
        array $meta = []
    ): StockMedicament {
        if ($quantite <= 0) {
            throw new DomainException('La quantité d\'entrée doit être supérieure à zéro.');
        }

        return DB::transaction(function () use ($medicamentId, $cabinetId, $quantite, $prixAchat, $userId, $meta) {
            $stock = StockMedicament::where('fkidMedicament', $medicamentId)
                ->where('fkidCabinet', $cabinetId)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = StockMedicament::create([
                    'fkidMedicament' => $medicamentId,
                    'fkidCabinet' => $cabinetId,
                    'quantiteStock' => 0,
                    'quantiteMin' => $meta['quantiteMin'] ?? 0,
                    'prixAchat' => 0,
                    'Masquer' => 0,
                ]);
            }

            $ancienneQuantite = (float) $stock->quantiteStock;
            $nouvelleQuantite = $ancienneQuantite + $quantite;

            // Toute entrée reçoit un lot, même sans date d'expiration. Cela permet
            // de conserver le coût réel et de faire des sorties FIFO/FEFO fiables.
            $lot = LotMedicament::create([
                'fkidStock' => $stock->idStock,
                'fkidMedicament' => $medicamentId,
                'numeroLot' => $meta['numeroLot'] ?? null,
                'quantiteInitiale' => $quantite,
                'quantiteRestante' => $quantite,
                'dateExpiration' => $meta['dateExpiration'] ?? null,
                'dateEntree' => now(),
                'prixAchatUnitaire' => $prixAchat,
                'fournisseur' => $meta['fournisseur'] ?? null,
                'referenceFacture' => $meta['reference'] ?? null,
                'fkidUser' => $userId,
                'Masquer' => 0,
            ]);

            $stock->update([
                'quantiteStock' => $nouvelleQuantite,
                // Ce champ représente le dernier prix d'achat saisi. Le coût
                // exact de chaque entrée reste porté par son lot.
                'prixAchat' => $prixAchat,
                'quantiteMin' => $meta['quantiteMin'] ?? $stock->quantiteMin,
                'dateDerniereEntree' => now(),
                'Masquer' => 0,
            ]);

            MouvementStock::create([
                'fkidStock' => $stock->idStock,
                'fkidMedicament' => $medicamentId,
                'fkidLot' => $lot->idLot,
                'typeMouvement' => 'ENTREE',
                'quantite' => $quantite,
                'prixUnitaire' => $prixAchat,
                'montantTotal' => $prixAchat * $quantite,
                'motif' => $meta['motif'] ?? 'Entrée de stock',
                'fkidUser' => $userId,
                'dateMouvement' => now(),
                'reference' => $meta['reference'] ?? null,
                'notes' => $meta['notes'] ?? null,
            ]);

            return $stock->fresh();
        });
    }

    /**
     * Déduit une ligne de facture une seule fois et consomme les lots en FEFO
     * (expiration la plus proche), puis FIFO pour les lots sans expiration.
     */
    public function deductForInvoiceDetail(
        int $stockId,
        float $quantite,
        int $detailFactureId,
        array $meta = []
    ): float {
        if ($quantite <= 0) {
            throw new DomainException('La quantité à sortir doit être supérieure à zéro.');
        }

        return DB::transaction(function () use ($stockId, $quantite, $detailFactureId, $meta) {
            $dejaDeduit = MouvementStock::where('fkidDetailFacture', $detailFactureId)
                ->whereRaw('UPPER(typeMouvement) = ?', ['SORTIE'])
                ->exists();

            if ($dejaDeduit) {
                return 0.0;
            }

            $stock = StockMedicament::whereKey($stockId)->lockForUpdate()->firstOrFail();
            if ((float) $stock->quantiteStock < $quantite) {
                throw new DomainException(sprintf(
                    'Stock insuffisant pour ce médicament : %.2f disponible(s), %.2f demandé(s).',
                    (float) $stock->quantiteStock,
                    $quantite
                ));
            }

            $restant = $quantite;
            $lots = LotMedicament::where('fkidStock', $stock->idStock)
                ->where('Masquer', 0)
                ->where('quantiteRestante', '>', 0)
                ->orderByRaw('CASE WHEN dateExpiration IS NULL THEN 1 ELSE 0 END')
                ->orderBy('dateExpiration')
                ->orderBy('dateEntree')
                ->orderBy('idLot')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($restant <= 0) {
                    break;
                }

                $prise = min((float) $lot->quantiteRestante, $restant);
                if ($prise <= 0) {
                    continue;
                }

                $lot->quantiteRestante = (float) $lot->quantiteRestante - $prise;
                $lot->save();
                $this->createSortieMovement($stock, $prise, $detailFactureId, $lot, $meta);
                $restant -= $prise;
            }

            // Compatibilité avec le stock historique qui n'a pas de lot.
            if ($restant > 0) {
                $this->createSortieMovement($stock, $restant, $detailFactureId, null, $meta);
            }

            $stock->quantiteStock = (float) $stock->quantiteStock - $quantite;
            $stock->dateDerniereSortie = now();
            $stock->save();

            return $quantite;
        });
    }

    /** Restaure uniquement les sorties réellement enregistrées pour une ligne. */
    public function restoreForDetail(int $detailFactureId, int $cabinetId): float
    {
        return $this->restoreMovements(
            MouvementStock::where('fkidDetailFacture', $detailFactureId),
            $cabinetId
        );
    }

    /** Restaure uniquement les sorties réellement enregistrées pour une facture. */
    public function restoreForInvoice(int $factureId, int $cabinetId): float
    {
        return $this->restoreMovements(
            MouvementStock::where('fkidFacture', $factureId),
            $cabinetId
        );
    }

    /** Quantité réservée par des factures non payées mais pas encore sortie. */
    public function reservedQuantity(int $medicamentId, int $cabinetId): float
    {
        // Une lecture verrouillante est volontairement utilisée ici. Sous MySQL
        // (REPEATABLE READ par défaut), elle lit l'état courant après l'attente
        // sur le stock et évite deux réservations concurrentes du même article.
        return (float) DB::table('detailfacturepatient as d')
            ->join('facture as f', 'd.fkidfacture', '=', 'f.Idfacture')
            ->select('d.Quantite')
            ->where('d.fkidmedicament', $medicamentId)
            ->where('d.IsAct', 2)
            ->where('f.fkidCabinet', $cabinetId)
            ->whereRaw('(CASE WHEN f.ISTP > 0 THEN f.TotalfactPatient ELSE f.TotFacture END) > (f.TotReglPatient + COALESCE(f.ReglementPEC, 0))')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('mouvements_stock as ms')
                    ->whereColumn('ms.fkidDetailFacture', 'd.idDetfacture')
                    ->whereRaw('UPPER(ms.typeMouvement) = ?', ['SORTIE']);
            })
            ->lockForUpdate()
            ->get()
            ->sum('Quantite');
    }

    /** Ajuste le stock global et les lots dans la même transaction. */
    public function adjustStock(
        int $stockId,
        float $nouvelleQuantite,
        float $prixNouveauStock,
        string $motif,
        int $userId
    ): float {
        if ($nouvelleQuantite < 0) {
            throw new DomainException('La quantité ajustée ne peut pas être négative.');
        }

        return DB::transaction(function () use ($stockId, $nouvelleQuantite, $prixNouveauStock, $motif, $userId) {
            $stock = StockMedicament::whereKey($stockId)->lockForUpdate()->firstOrFail();
            $ancienneQuantite = (float) $stock->quantiteStock;
            $ecart = $nouvelleQuantite - $ancienneQuantite;
            if (abs($ecart) < 0.00001) {
                return 0.0;
            }

            if ($ecart > 0) {
                $prix = $prixNouveauStock;
                $lot = LotMedicament::create([
                    'fkidStock' => $stock->idStock,
                    'fkidMedicament' => $stock->fkidMedicament,
                    'numeroLot' => 'AJUST-' . now()->format('YmdHis'),
                    'quantiteInitiale' => $ecart,
                    'quantiteRestante' => $ecart,
                    'dateExpiration' => null,
                    'dateEntree' => now(),
                    'prixAchatUnitaire' => $prix,
                    'fkidUser' => $userId,
                    'Masquer' => 0,
                ]);

                MouvementStock::create([
                    'fkidStock' => $stock->idStock,
                    'fkidMedicament' => $stock->fkidMedicament,
                    'fkidLot' => $lot->idLot,
                    'typeMouvement' => 'AJUSTEMENT',
                    'quantite' => $ecart,
                    'prixUnitaire' => $prix,
                    'montantTotal' => $prix * $ecart,
                    'motif' => $motif,
                    'fkidUser' => $userId,
                    'dateMouvement' => now(),
                    'notes' => 'Ajustement positif d\'inventaire',
                ]);

                // Un ajustement positif est une nouvelle valorisation d'entrée :
                // conserver son prix comme dernier prix d'achat, sans moyenne.
                $stock->prixAchat = $prix;
            } else {
                $aRetirer = abs($ecart);
                $restant = $aRetirer;
                $lots = LotMedicament::where('fkidStock', $stock->idStock)
                    ->where('Masquer', 0)
                    ->where('quantiteRestante', '>', 0)
                    ->orderByRaw('CASE WHEN dateExpiration IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('dateExpiration')
                    ->orderBy('dateEntree')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($restant <= 0) {
                        break;
                    }
                    $prise = min((float) $lot->quantiteRestante, $restant);
                    $lot->quantiteRestante = (float) $lot->quantiteRestante - $prise;
                    $lot->save();
                    MouvementStock::create([
                        'fkidStock' => $stock->idStock,
                        'fkidMedicament' => $stock->fkidMedicament,
                        'fkidLot' => $lot->idLot,
                        'typeMouvement' => 'AJUSTEMENT',
                        'quantite' => -$prise,
                        'prixUnitaire' => (float) $lot->prixAchatUnitaire,
                        'montantTotal' => (float) $lot->prixAchatUnitaire * $prise,
                        'motif' => $motif,
                        'fkidUser' => $userId,
                        'dateMouvement' => now(),
                        'notes' => 'Ajustement négatif d\'inventaire',
                    ]);
                    $restant -= $prise;
                }

                if ($restant > 0) {
                    MouvementStock::create([
                        'fkidStock' => $stock->idStock,
                        'fkidMedicament' => $stock->fkidMedicament,
                        'fkidLot' => null,
                        'typeMouvement' => 'AJUSTEMENT',
                        'quantite' => -$restant,
                        'prixUnitaire' => (float) $stock->prixAchat,
                        'montantTotal' => (float) $stock->prixAchat * $restant,
                        'motif' => $motif,
                        'fkidUser' => $userId,
                        'dateMouvement' => now(),
                        'notes' => 'Ajustement négatif du stock historique sans lot',
                    ]);
                }
            }

            $stock->quantiteStock = $nouvelleQuantite;
            $stock->save();

            return $ecart;
        });
    }

    /** Valorisation par lots + reliquat historique sans lot. */
    public function stockValue(StockMedicament $stock): float
    {
        $lots = $stock->relationLoaded('lots') ? $stock->lots : $stock->lots()->get();
        $quantiteLots = (float) $lots->sum('quantiteRestante');
        $valeurLots = (float) $lots->sum(function ($lot) {
            return (float) $lot->quantiteRestante * (float) $lot->prixAchatUnitaire;
        });
        $sansLot = max(0, (float) $stock->quantiteStock - $quantiteLots);

        return $valeurLots + ($sansLot * (float) $stock->prixAchat);
    }

    private function createSortieMovement(
        StockMedicament $stock,
        float $quantite,
        int $detailFactureId,
        ?LotMedicament $lot,
        array $meta
    ): void {
        $cout = $lot ? (float) $lot->prixAchatUnitaire : (float) $stock->prixAchat;

        MouvementStock::create([
            'fkidStock' => $stock->idStock,
            'fkidMedicament' => $stock->fkidMedicament,
            'fkidLot' => $lot ? $lot->idLot : null,
            'typeMouvement' => 'SORTIE',
            'quantite' => $quantite,
            'prixUnitaire' => $cout,
            'montantTotal' => $cout * $quantite,
            'motif' => $meta['motif'] ?? 'Sortie de stock',
            'fkidFacture' => $meta['factureId'] ?? null,
            'fkidDetailFacture' => $detailFactureId,
            'fkidPatient' => $meta['patientId'] ?? null,
            'fkidUser' => $meta['userId'] ?? null,
            'dateMouvement' => now(),
            'reference' => $meta['reference'] ?? null,
            'notes' => $meta['notes'] ?? null,
        ]);
    }

    private function restoreMovements(Builder $baseQuery, int $cabinetId): float
    {
        return DB::transaction(function () use ($baseQuery, $cabinetId) {
            $mouvements = $baseQuery
                ->whereRaw('UPPER(typeMouvement) = ?', ['SORTIE'])
                ->whereHas('stock', function ($query) use ($cabinetId) {
                    $query->where('fkidCabinet', $cabinetId);
                })
                ->lockForUpdate()
                ->get();

            $total = 0.0;
            foreach ($mouvements as $mouvement) {
                $quantite = abs((float) $mouvement->quantite);
                if ($quantite <= 0) {
                    $mouvement->delete();
                    continue;
                }

                $stock = StockMedicament::whereKey($mouvement->fkidStock)->lockForUpdate()->first();
                if (!$stock || (int) $stock->fkidCabinet !== $cabinetId) {
                    continue;
                }

                $stock->quantiteStock = (float) $stock->quantiteStock + $quantite;
                $stock->save();

                if ($mouvement->fkidLot) {
                    $lot = LotMedicament::whereKey($mouvement->fkidLot)
                        ->where('fkidStock', $stock->idStock)
                        ->lockForUpdate()
                        ->first();
                    if ($lot) {
                        $lot->quantiteRestante = min(
                            (float) $lot->quantiteInitiale,
                            (float) $lot->quantiteRestante + $quantite
                        );
                        $lot->save();
                    }
                }

                $total += $quantite;
                $mouvement->delete();
            }

            return $total;
        });
    }
}
