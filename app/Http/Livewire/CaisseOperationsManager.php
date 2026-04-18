<?php

namespace App\Http\Livewire;

use App\Models\CaisseOperation;
use App\Models\Facture;
use App\Models\Medecin;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class CaisseOperationsManager extends Component
{
    use WithPagination;

    public $medecin_id;
    public $date_debut;
    public $date_fin;
    public $isDocteurProprietaire = false;
    public $isSecretaire = false;
    public $isDocteur = false;

    protected $queryString = [
        'medecin_id' => ['except' => ''],
        'date_debut' => ['except' => ''],
        'date_fin' => ['except' => '']
    ];

    private const CACHE_TTL = 300; // 5 minutes
    private const CACHE_KEY_MEDECINS = 'caisse_medecins';
    private const CACHE_KEY_OPERATIONS = 'caisse_operations_';

    protected $listeners = ['caisseOperationsUpdated' => '$refresh'];

    public function mount()
    {
        $user = Auth::user();
        $this->isSecretaire = ($user->IdClasseUser == 1);
        $this->isDocteur = ($user->IdClasseUser == 2);
        $this->isDocteurProprietaire = ($user->IdClasseUser == 3);

        // Par défaut, filtrer sur la journée courante
        $today = now()->toDateString();
        $this->date_debut = $today;
        
        if ($this->isDocteur) {
            $this->medecin_id = $user->fkidmedecin;
        }
    }

    public function resetFilters()
    {
        $this->reset(['medecin_id', 'date_debut', 'date_fin']);
        $this->resetPage();
    }

    /**
     * Supprimer une recette liée à une facture.
     * Annule toute la recette sur la facture (TotReglPatient et/ou ReglementPEC) avant suppression.
     * Pour les factures assurées, le montant est réparti proportionnellement entre Patient et PEC.
     */
    public function supprimerRecette($operationId)
    {
        try {
            $operation = CaisseOperation::find($operationId);
            if (!$operation) {
                session()->flash('error', 'Opération introuvable.');
                return;
            }

            // Vérifier que c'est bien une recette (entrée d'espèces)
            if ($operation->entreEspece <= 0) {
                session()->flash('error', 'Seules les recettes peuvent être supprimées depuis cette action.');
                return;
            }

            // Vérifier les permissions
            $user = Auth::user();
            if ($this->isSecretaire && $operation->fkiduser != $user->Iduser) {
                session()->flash('error', 'Vous ne pouvez supprimer que vos propres recettes.');
                return;
            }
            if ($this->isDocteur && $operation->fkidmedecin != $user->fkidmedecin) {
                session()->flash('error', 'Vous ne pouvez supprimer que les recettes de vos consultations.');
                return;
            }

            DB::beginTransaction();

            // Si la recette est liée à une facture, annuler tout le règlement
            if ($operation->fkidfacturebord > 0) {
                $facture = Facture::find($operation->fkidfacturebord);
                if ($facture) {
                    $montant = abs($operation->MontantOperation);
                    $totReglPatient = $facture->TotReglPatient ?? 0;
                    $reglementPec = $facture->ReglementPEC ?? 0;
                    $totalRegle = $totReglPatient + $reglementPec;

                    if ($totalRegle > 0) {
                        // Répartition proportionnelle : annuler toute la recette sur Patient et PEC selon les soldes actuels
                        $ratioPatient = $totReglPatient / $totalRegle;
                        $soustrairePatient = min($totReglPatient, round($montant * $ratioPatient, 2));
                        $soustrairePec = min($reglementPec, $montant - $soustrairePatient);
                        $facture->TotReglPatient = max(0, $totReglPatient - $soustrairePatient);
                        $facture->ReglementPEC = max(0, $reglementPec - $soustrairePec);
                    } else {
                        $facture->TotReglPatient = max(0, $totReglPatient - $montant);
                    }
                    $facture->save();
                }
            }

            $operation->delete();

            // Invalider le cache des opérations (pour tous les types d'utilisateurs)
            $this->invalidateOperationsCache($user->fkidcabinet ?? 1, $operation->dateoper);

            DB::commit();

            session()->flash('message', 'Recette supprimée avec succès. Le règlement a été annulé sur la facture.');
            $this->emit('caisseOperationsUpdated');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur suppression recette', [
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }

    /**
     * Supprimer une dépense.
     * Seul le médecin propriétaire peut supprimer une dépense.
     */
    public function supprimerDepense($operationId)
    {
        try {
            $user = Auth::user();
            if (!$this->isDocteurProprietaire) {
                session()->flash('error', 'Seul le médecin propriétaire peut supprimer une dépense.');
                return;
            }

            $operation = CaisseOperation::find($operationId);
            if (!$operation) {
                session()->flash('error', 'Opération introuvable.');
                return;
            }

            // Vérifier que c'est bien une dépense (sortie d'espèces)
            if ($operation->retraitEspece <= 0) {
                session()->flash('error', 'Seules les dépenses peuvent être supprimées depuis cette action.');
                return;
            }

            DB::beginTransaction();

            $operation->delete();

            // Invalider le cache des opérations (pour tous les types d'utilisateurs)
            $this->invalidateOperationsCache($user->fkidcabinet ?? 1, $operation->dateoper);

            DB::commit();

            session()->flash('message', 'Dépense supprimée avec succès.');
            $this->emit('caisseOperationsUpdated');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Erreur suppression dépense', [
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }
    }

    private function getCacheKey()
    {
        $user = Auth::user();
        $key = self::CACHE_KEY_OPERATIONS . $user->fkidcabinet . '_u' . ($user->IdClasseUser ?? 0);
        if ($this->medecin_id) $key .= '_m' . $this->medecin_id;
        if ($this->date_debut) $key .= '_d' . $this->date_debut;
        if ($this->date_fin) $key .= '_f' . $this->date_fin;
        return $key;
    }

    private function invalidateOperationsCache($cabinetId, $dateOperation)
    {
        Cache::forget($this->getCacheKey());
        $dateOperationStr = $dateOperation ? \Carbon\Carbon::parse($dateOperation)->toDateString() : null;
        if ($dateOperationStr) {
            $medecins = Medecin::where('fkidcabinet', $cabinetId)->pluck('idMedecin');
            foreach ([1, 2, 3] as $idClasseUser) {
                $base = self::CACHE_KEY_OPERATIONS . $cabinetId . '_u' . $idClasseUser;
                foreach ($medecins as $medecinId) {
                    Cache::forget($base . '_m' . $medecinId . '_d' . $dateOperationStr);
                    Cache::forget($base . '_m' . $medecinId . '_d' . $dateOperationStr . '_f' . $dateOperationStr);
                }
                Cache::forget($base . '_d' . $dateOperationStr);
                Cache::forget($base . '_d' . $dateOperationStr . '_f' . $dateOperationStr);
            }
        }
        foreach ([1, 2, 3] as $idClasseUser) {
            Cache::forget(self::CACHE_KEY_OPERATIONS . $cabinetId . '_u' . $idClasseUser);
        }
    }

    private function getMedecins()
    {
        return Cache::remember(self::CACHE_KEY_MEDECINS, self::CACHE_TTL, function () {
            return Medecin::orderBy('Nom')->get();
        });
    }

    private function getOperations()
    {
        $cacheKey = $this->getCacheKey();
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $user = Auth::user();
            $query = CaisseOperation::where('fkidcabinet', $user->fkidcabinet);

            if ($this->isDocteur) {
                $query->where('fkidmedecin', $user->fkidmedecin);
            } elseif ($this->isSecretaire) {
                $query->where('fkiduser', $user->Iduser);
                if ($this->medecin_id) {
                    $query->where('fkidmedecin', $this->medecin_id);
                }
            } else            if ($this->medecin_id) {
                $query->where('fkidmedecin', $this->medecin_id);
            }

            // Seul le médecin propriétaire peut voir les dépenses
            if (!$this->isDocteurProprietaire) {
                $query->where('retraitEspece', 0);
            }

            // Filtrer sur la date choisie (ou aujourd'hui par défaut)
            $date = $this->date_debut ?: now()->toDateString();
            $query->whereDate('dateoper', $date);

            return $query->get();
        });
    }

    private function getPaginatedOperations()
    {
        $user = Auth::user();
        $query = CaisseOperation::where('fkidcabinet', $user->fkidcabinet);

        if ($this->isDocteur) {
            $query->where('fkidmedecin', $user->fkidmedecin);
        } elseif ($this->isSecretaire) {
            $query->where('fkiduser', $user->Iduser);
            if ($this->medecin_id) {
                $query->where('fkidmedecin', $this->medecin_id);
            }
        } elseif ($this->medecin_id) {
            $query->where('fkidmedecin', $this->medecin_id);
        }

        // Seul le médecin propriétaire peut voir les dépenses
        if (!$this->isDocteurProprietaire) {
            $query->where('retraitEspece', 0);
        }

        // Filtrer sur la date choisie (ou aujourd'hui par défaut)
        $date = $this->date_debut ?: now()->toDateString();
        $query->whereDate('dateoper', $date);

        return $query->orderBy('dateoper', 'desc')
                    ->orderBy('cle', 'desc')
                    ->paginate(10);
    }

    public function render()
    {
        // Récupérer les médecins (avec cache)
        $medecins = $this->getMedecins();

        // Récupérer les opérations (avec cache)
        $operations = $this->getOperations();

        // Charger les médecins et patients en une seule requête
        $medecinIds = $operations->pluck('fkidmedecin')->unique()->filter();
        $patientIds = $operations->pluck('fkidTiers')->map(fn($id) => (int)$id)->unique()->filter();
        
        $medecinsMap = Medecin::whereIn('idMedecin', $medecinIds)->get()->keyBy('idMedecin');
        $patientsMap = Patient::whereIn('ID', $patientIds)->get()->keyBy('ID');

        // Associer les médecins et patients aux opérations
        $operations->each(function($operation) use ($medecinsMap, $patientsMap) {
            $medecinObjet = $medecinsMap->get($operation->fkidmedecin);
            $operation->medecin = $medecinObjet
                ? $medecinObjet
                : (object)['Nom' => $operation->getAttributes()['medecin'] ?? 'N/A'];
            $operation->tiers = $patientsMap->get((int)$operation->fkidTiers);
        });

        // Calculer les totaux
        $totalRecettes = $operations->sum('entreEspece');
        $totalDepenses = $operations->sum('retraitEspece');
        $solde = $totalRecettes - $totalDepenses;

        // Calculer les totaux par mode de paiement
        $typesPaiement = $operations->pluck('TypePAie')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $totauxGenerauxParMoyenPaiement = [];
        foreach ($typesPaiement as $type) {
            $operationsType = $operations->where('TypePAie', $type);
            $totauxGenerauxParMoyenPaiement[$type] = [
                'recettes' => $operationsType->sum('entreEspece'),
                'depenses' => $operationsType->sum('retraitEspece'),
                'solde' => $operationsType->sum('entreEspece') - $operationsType->sum('retraitEspece')
            ];
        }

        // Calculer les totaux par médecin
        $totauxParMedecin = $medecinsMap->map(function($medecin) use ($operations) {
            $operationsMedecin = $operations->where('fkidmedecin', $medecin->idMedecin);
            $recettes = $operationsMedecin->sum('entreEspece');
            $depenses = $operationsMedecin->sum('retraitEspece');
            
            // Calculer les totaux par mode de paiement pour ce médecin
            $modesPaiement = [];
            foreach ($operationsMedecin->pluck('TypePAie')->unique() as $type) {
                $opsType = $operationsMedecin->where('TypePAie', $type);
                $modesPaiement[$type] = [
                    'recettes' => $opsType->sum('entreEspece'),
                    'depenses' => $opsType->sum('retraitEspece'),
                    'solde' => $opsType->sum('entreEspece') - $opsType->sum('retraitEspece')
                ];
            }

            return [
                'nom' => $medecin->Nom,
                'recettes' => $recettes,
                'depenses' => $depenses,
                'solde' => $recettes - $depenses,
                'modes_paiement' => $modesPaiement
            ];
        })->toArray();

        // Récupérer les opérations paginées
        $paginatedOperations = $this->getPaginatedOperations();
        
        // Associer les médecins et patients aux opérations paginées
        $paginatedOperations->getCollection()->each(function($operation) use ($medecinsMap, $patientsMap) {
            $medecinObjet = $medecinsMap->get($operation->fkidmedecin);
            $operation->medecin = $medecinObjet
                ? $medecinObjet
                : (object)['Nom' => $operation->getAttributes()['medecin'] ?? 'N/A'];
            $operation->tiers = $patientsMap->get((int)$operation->fkidTiers);
        });

        return view('livewire.caisse-operations-manager', [
            'operations' => $paginatedOperations,
            'medecins' => $medecins,
            'isSecretaire' => $this->isSecretaire,
            'isDocteur' => $this->isDocteur,
            'isDocteurProprietaire' => $this->isDocteurProprietaire,
            'totalRecettes' => $totalRecettes,
            'totalDepenses' => $totalDepenses,
            'solde' => $solde,
            'totauxParMedecin' => $totauxParMedecin,
            'totauxGenerauxParMoyenPaiement' => $totauxGenerauxParMoyenPaiement
        ]);
    }
} 