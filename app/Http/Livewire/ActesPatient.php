<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Acte;
use App\Models\Facture;
use App\Models\DetailFacturePatient;
use App\Models\CaisseOperation;
use App\Models\Patient;
use App\Models\Medecin;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ActesPatient extends Component
{
    public $patient = null;
    public $medecin_id = null;
    public $mode_paiement = '';
    public $search_acte = '';
    public $actes = [];
    public $lignes = []; // [['acte_id'=>, 'acte_nom'=>, 'prix'=>, 'quantite'=>1]]
    public $medecins = [];
    public $typesPaiement = [];
    public $total = 0;

    protected $listeners = ['refresh' => '$refresh'];

    public function mount($patient = null)
    {
        $this->patient = $patient;

        $user = Auth::user();

        $this->medecins = \App\Models\User::whereHas('typeuser', function ($q) {
            $q->whereIn('Libelle', ['Docteur Propriétaire', 'Docteur']);
        })->where('ismasquer', 0)
          ->where('fkidcabinet', $user->fkidcabinet)
          ->get();

        $this->typesPaiement = \App\Models\RefTypePaiement::select(['idtypepaie', 'LibPaie'])->get();

        // Médecin par défaut
        if ($user->isDocteur() || $user->isDocteurProprietaire()) {
            $this->medecin_id = $user->fkidmedecin ?: ($this->medecins->first()->fkidmedecin ?? null);
        } else {
            $this->medecin_id = $this->medecins->first()->fkidmedecin ?? null;
        }

        $this->loadActes();
    }

    public function loadActes()
    {
        $query = Acte::where('Masquer', 0)->orderBy('nordre');
        if (trim($this->search_acte) !== '') {
            $query->where('Acte', 'like', '%' . $this->search_acte . '%');
        }
        $this->actes = $query->get()->toArray();
    }

    public function updatedSearchActe()
    {
        $this->loadActes();
    }

    public function ajouterLigne($acteId)
    {
        $acte = Acte::find($acteId);
        if (!$acte) return;

        // Si déjà dans la liste, incrémenter la quantité
        foreach ($this->lignes as $i => $ligne) {
            if ($ligne['acte_id'] == $acteId) {
                $this->lignes[$i]['quantite']++;
                $this->calculerTotal();
                return;
            }
        }

        $this->lignes[] = [
            'acte_id'   => $acte->ID,
            'acte_nom'  => $acte->Acte,
            'prix'      => $acte->PrixRef,
            'quantite'  => 1,
        ];

        $this->calculerTotal();
    }

    public function supprimerLigne($index)
    {
        array_splice($this->lignes, $index, 1);
        $this->calculerTotal();
    }

    public function updatedLignes()
    {
        $this->calculerTotal();
    }

    public function calculerTotal()
    {
        $this->total = collect($this->lignes)->sum(fn($l) => floatval($l['prix']) * intval($l['quantite']));
    }

    public function save()
    {
        $this->validate([
            'medecin_id'    => 'required',
            'mode_paiement' => 'required',
            'lignes'        => 'required|array|min:1',
        ], [
            'medecin_id.required'    => 'Veuillez sélectionner un médecin.',
            'mode_paiement.required' => 'Veuillez sélectionner un mode de paiement.',
            'lignes.min'             => 'Veuillez ajouter au moins un acte.',
        ]);

        $patientId = is_array($this->patient) ? $this->patient['ID'] : $this->patient->ID;
        $patient = Patient::find($patientId);

        try {
            DB::transaction(function () use ($patient, $patientId) {
                $this->calculerTotal();

                // Taux PEC
                $txpec = 0;
                $fkidEtsAssurance = null;
                if ($patient && $patient->Assureur) {
                    $fkidEtsAssurance = $patient->Assureur;
                    $txpec = floatval($patient->assureur->TauxdePEC ?? 0);
                }

                $totalPEC     = $this->total * $txpec;
                $totalPatient = $this->total * (1 - $txpec);

                // Créer la facture
                $factureData = Facture::generateUniqueFactureNumber(Auth::user()->fkidcabinet);
                $facture = Facture::create([
                    'Nfacture'            => $factureData['Nfacture'],
                    'anneeFacture'        => $factureData['anneeFacture'],
                    'nordre'              => $factureData['nordre'],
                    'DtFacture'           => Carbon::now(),
                    'IDPatient'           => $patientId,
                    'ISTP'                => $txpec > 0 ? 1 : 0,
                    'fkidEtsAssurance'    => $fkidEtsAssurance,
                    'TXPEC'               => $txpec,
                    'TotFacture'          => $this->total,
                    'TotalPEC'            => $totalPEC,
                    'TotalfactPatient'    => $totalPatient,
                    'ModeReglement'       => $this->mode_paiement,
                    'DtReglement'         => Carbon::now(),
                    'FkidMedecinInitiateur' => $this->medecin_id,
                    'fkidCabinet'         => Auth::user()->fkidcabinet,
                    'ispayerAssureur'     => 0,
                    'user'                => Auth::user()->NomComplet ?? Auth::user()->name,
                    'TotReglPatient'      => $totalPatient,
                    'ReglementPEC'        => 0,
                    'PartLaboratoire'     => 0,
                    'MontantAffectation'  => 0,
                    'Type'                => 'Facture',
                ]);

                // Créer les détails
                foreach ($this->lignes as $ligne) {
                    $prixLigne = floatval($ligne['prix']) * intval($ligne['quantite']);
                    DetailFacturePatient::create([
                        'fkidfacture'  => $facture->Idfacture,
                        'DtAjout'      => Carbon::now(),
                        'Actes'        => $ligne['acte_nom'],
                        'PrixFacture'  => floatval($ligne['prix']),
                        'PrixRef'      => floatval($ligne['prix']),
                        'Quantite'     => intval($ligne['quantite']),
                        'fkidMedecin'  => $this->medecin_id,
                        'fkidacte'     => $ligne['acte_id'],
                        'IsAct'        => 1,
                        'fkidcabinet'  => Auth::user()->fkidcabinet,
                        'ActesArab'    => null,
                        'user'         => Auth::user()->NomComplet ?? Auth::user()->name,
                        'TauxPEC'      => $txpec,
                        'MontantPEC'   => $prixLigne * $txpec,
                        'MontantPatient' => $prixLigne * (1 - $txpec),
                        'Dents'        => 'Acte',
                    ]);
                }

                // Opération de caisse
                $medecin = Medecin::find($this->medecin_id);
                CaisseOperation::create([
                    'dateoper'          => Carbon::now(),
                    'MontantOperation'  => $this->total,
                    'designation'       => 'Actes N°' . $facture->Nfacture . ' - Dr. ' . ($medecin->Nom ?? ''),
                    'fkidTiers'         => $patientId,
                    'entreEspece'       => $totalPatient,
                    'retraitEspece'     => 0,
                    'pourPatFournisseur' => 0,
                    'pourCabinet'       => 1,
                    'fkiduser'          => Auth::id(),
                    'exercice'          => Carbon::now()->year,
                    'fkIdTypeTiers'     => 1,
                    'fkidfacturebord'   => $facture->Idfacture,
                    'DtCr'              => Carbon::now(),
                    'fkidcabinet'       => Auth::user()->fkidcabinet,
                    'fkidtypePaie'      => 1,
                    'TypePAie'          => $this->mode_paiement,
                    'fkidmedecin'       => $this->medecin_id,
                    'medecin'           => $medecin->Nom ?? '',
                    'TauxPEC'           => $txpec,
                    'MontantPEC'        => $totalPEC,
                ]);

                // Ouvrir le reçu
                $receiptUrl = route('consultations.receipt', $facture->Idfacture);
                $this->dispatchBrowserEvent('open-receipt', ['url' => $receiptUrl]);

                session()->flash('success', 'Actes enregistrés et facturés avec succès.');
                $this->lignes = [];
                $this->total = 0;
                $this->mode_paiement = '';
                $this->search_acte = '';
                $this->loadActes();
            });
        } catch (\Exception $e) {
            \Log::error('Erreur ActesPatient::save()', ['error' => $e->getMessage()]);
            $this->addError('general', 'Erreur : ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.actes-patient');
    }
}
