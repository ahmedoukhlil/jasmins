<div class="p-4 md:p-6 space-y-6">

    @if(session()->has('message'))
    <div class="flex items-center gap-2 px-4 py-3 bg-green-50 border-l-4 border-green-500 rounded text-green-800 text-sm">
        <i class="fas fa-check-circle"></i> {{ session('message') }}
    </div>
    @endif
    @if(session()->has('error'))
    <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border-l-4 border-red-500 rounded text-red-800 text-sm">
        <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
    </div>
    @endif

    {{-- Alertes --}}
    @if($alertesStockFaible > 0 || $alertesExpires > 0 || $alertesExpireBientot > 0)
    <div class="space-y-2">
        @if($alertesStockFaible > 0)
        <div class="flex items-center gap-2 px-4 py-3 bg-yellow-50 border-l-4 border-yellow-400 rounded text-yellow-800 text-sm">
            <i class="fas fa-exclamation-triangle"></i>
            <span><strong>{{ $alertesStockFaible }}</strong> médicament(s) en stock faible — quantité inférieure au seuil minimum</span>
        </div>
        @endif
        @if($alertesExpires > 0)
        <div class="flex items-center gap-2 px-4 py-3 bg-red-50 border-l-4 border-red-400 rounded text-red-800 text-sm">
            <i class="fas fa-times-circle"></i>
            <span><strong>{{ $alertesExpires }}</strong> lot(s) expiré(s) — date d'expiration dépassée</span>
        </div>
        @endif
        @if($alertesExpireBientot > 0)
        <div class="flex items-center gap-2 px-4 py-3 bg-orange-50 border-l-4 border-orange-400 rounded text-orange-800 text-sm">
            <i class="fas fa-clock"></i>
            <span><strong>{{ $alertesExpireBientot }}</strong> lot(s) expire(nt) dans les 30 prochains jours</span>
        </div>
        @endif
    </div>
    @endif

    @php $stats = $this->statistiquesDashboard; @endphp

    {{-- Statistiques --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <button wire:click="ouvrirDetailModal('total')" class="bg-primary-light rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-primary uppercase tracking-wide mb-1">Total médicaments</dt>
            <dd class="text-2xl font-bold text-primary">{{ $stats['totalMedicaments'] }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('valeur')" class="bg-green-50 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1">Valeur du stock</dt>
            <dd class="text-2xl font-bold text-green-700">{{ number_format($stats['valeurStock'], 0, ',', ' ') }} <span class="text-sm font-normal">MRU</span></dd>
        </button>
        <button wire:click="ouvrirDetailModal('quantite')" class="bg-gray-50 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1">Unités disponibles</dt>
            <dd class="text-2xl font-bold text-gray-800">{{ number_format($stats['totalQuantiteStock'], 0, ',', ' ') }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('rupture')" class="bg-red-50 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-1">En rupture</dt>
            <dd class="text-2xl font-bold text-red-600">{{ $stats['medicamentsRupture'] }}</dd>
        </button>
    </div>

    {{-- Alertes secondaires & mouvements --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        <button wire:click="ouvrirDetailModal('faible')" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-yellow-700 uppercase tracking-wide mb-1">Stock faible</dt>
            <dd class="text-xl font-bold text-yellow-700">{{ $stats['medicamentsStockFaible'] }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('expires')" class="bg-red-50 border border-red-200 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-1">Lots expirés</dt>
            <dd class="text-xl font-bold text-red-600">{{ $stats['lotsExpires'] }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('expire_bientot')" class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-orange-700 uppercase tracking-wide mb-1">Expire bientôt</dt>
            <dd class="text-xl font-bold text-orange-600">{{ $stats['lotsExpireBientot'] }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('entrees')" class="bg-green-50 border border-green-200 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-green-700 uppercase tracking-wide mb-1"><i class="fas fa-arrow-down mr-1"></i>Entrées ce mois</dt>
            <dd class="text-xl font-bold text-green-700">{{ $stats['entreesCeMois'] }}</dd>
        </button>
        <button wire:click="ouvrirDetailModal('sorties')" class="bg-red-50 border border-red-200 rounded-xl p-4 text-left hover:shadow-md transition-shadow">
            <dt class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-1"><i class="fas fa-arrow-up mr-1"></i>Sorties ce mois</dt>
            <dd class="text-xl font-bold text-red-600">{{ $stats['sortiesCeMois'] }}</dd>
        </button>
    </div>

    <p class="text-xs text-gray-400 text-center"><i class="fas fa-mouse-pointer mr-1"></i>Cliquez sur une carte pour voir les détails</p>

    {{-- Modal de détails --}}
    @if($showDetailModal)
    <div class="modal-overlay" wire:click.self="fermerDetailModal">
        <div class="modal-box max-w-5xl w-full">
            <div class="modal-header">
                <h2>
                    @if($detailType === 'total') Détails — Total médicaments
                    @elseif($detailType === 'valeur') Détails — Valeur du stock
                    @elseif($detailType === 'quantite') Détails — Unités disponibles
                    @elseif($detailType === 'rupture') Détails — Médicaments en rupture
                    @elseif($detailType === 'faible') Détails — Stock faible
                    @elseif($detailType === 'expires') Détails — Lots expirés
                    @elseif($detailType === 'expire_bientot') Détails — Lots expirant bientôt
                    @elseif($detailType === 'entrees') Détails — Entrées ce mois
                    @elseif($detailType === 'sorties') Détails — Sorties ce mois
                    @endif
                </h2>
                <button wire:click="fermerDetailModal" class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                @if(count($detailData) > 0)
                <p class="text-sm text-gray-500 mb-3">
                    {{ (($this->detailPage - 1) * $this->detailPerPage) + 1 }}–{{ min($this->detailPage * $this->detailPerPage, count($detailData)) }} sur {{ count($detailData) }} résultat(s)
                </p>
                <div class="overflow-x-auto">
                    <table>
                        <thead>
                            <tr>
                                @if(in_array($detailType, ['total', 'valeur', 'quantite', 'rupture', 'faible']))
                                    <th>Médicament</th>
                                    <th>Quantité</th>
                                    @if(in_array($detailType, ['total', 'valeur', 'rupture', 'faible']))<th>Prix d'achat</th>@endif
                                    @if(in_array($detailType, ['total', 'valeur']))<th>Valeur</th>@endif
                                    @if(in_array($detailType, ['total', 'quantite', 'faible']))<th>Seuil min</th>@endif
                                    @if($detailType === 'faible')<th>Déficit</th>@endif
                                    @if($detailType === 'total')<th>Statut</th>@endif
                                    @if(in_array($detailType, ['faible', 'rupture']))<th></th>@endif
                                @elseif(in_array($detailType, ['expires', 'expire_bientot']))
                                    <th>Médicament</th>
                                    <th>N° Lot</th>
                                    <th>Quantité</th>
                                    <th>Date expiration</th>
                                    <th>{{ $detailType === 'expires' ? 'Jours expirés' : 'Jours restants' }}</th>
                                @elseif(in_array($detailType, ['entrees', 'sorties']))
                                    <th>Date</th>
                                    <th>Médicament</th>
                                    <th>Quantité</th>
                                    <th>{{ $detailType === 'entrees' ? 'Prix d\'achat' : 'Prix de vente' }}</th>
                                    <th>Montant</th>
                                    <th>Utilisateur</th>
                                    @if($detailType === 'sorties')<th>Patient</th><th>Facture</th>@endif
                                    @if($detailType === 'entrees')<th>Référence</th>@endif
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->detailDataPaginated as $item)
                            <tr>
                                @if(in_array($detailType, ['total', 'valeur', 'quantite', 'rupture', 'faible']))
                                    <td class="font-medium">{{ $item['medicament'] }}</td>
                                    <td>{{ number_format($item['quantite'], 0) }}</td>
                                    @if(in_array($detailType, ['total', 'valeur', 'rupture', 'faible']))<td>{{ number_format($item['prix_achat'] ?? 0, 0) }} MRU</td>@endif
                                    @if(in_array($detailType, ['total', 'valeur']))<td class="font-semibold">{{ number_format($item['valeur'] ?? 0, 0) }} MRU</td>@endif
                                    @if(in_array($detailType, ['total', 'quantite', 'faible']))<td>{{ number_format($item['seuil_min'] ?? 0, 0) }}</td>@endif
                                    @if($detailType === 'faible')<td class="text-red-600 font-semibold">{{ number_format($item['difference'] ?? 0, 0) }}</td>@endif
                                    @if($detailType === 'total')
                                    <td>
                                        @if(($item['statut'] ?? '') === 'faible')
                                            <span class="badge badge-warning">Faible</span>
                                        @elseif(($item['statut'] ?? '') === 'rupture')
                                            <span class="badge badge-error">Rupture</span>
                                        @else
                                            <span class="badge badge-success">OK</span>
                                        @endif
                                    </td>
                                    @endif
                                    @if(in_array($detailType, ['faible', 'rupture']))
                                    <td>
                                        @if(!empty($item['medicament_id']))
                                        <button wire:click="openEntreeModalForMedicament({{ $item['medicament_id'] }})"
                                                class="btn-primary text-xs px-3 py-1">
                                            <i class="fas fa-plus-circle"></i> Alimenter
                                        </button>
                                        @endif
                                    </td>
                                    @endif
                                @elseif(in_array($detailType, ['expires', 'expire_bientot']))
                                    <td class="font-medium">{{ $item['medicament'] }}</td>
                                    <td>{{ $item['numero_lot'] ?? 'N/A' }}</td>
                                    <td>{{ number_format($item['quantite'], 0) }}</td>
                                    <td>{{ $item['date_expiration'] }}</td>
                                    @if($detailType === 'expires')
                                        <td class="text-red-600 font-semibold">{{ abs($item['jours_expires'] ?? 0) }} jours</td>
                                    @else
                                        <td class="text-orange-600 font-semibold">{{ $item['jours_restants'] ?? 0 }} jours</td>
                                    @endif
                                @elseif(in_array($detailType, ['entrees', 'sorties']))
                                    <td class="whitespace-nowrap">{{ $item['date'] }}</td>
                                    <td class="font-medium">{{ $item['medicament'] }}</td>
                                    <td>{{ number_format($item['quantite'], 0) }}</td>
                                    <td>{{ number_format($item['prix_unitaire'] ?? 0, 0) }} MRU</td>
                                    <td class="font-semibold">{{ number_format($item['montant'] ?? 0, 0) }} MRU</td>
                                    <td>{{ $item['utilisateur'] }}</td>
                                    @if($detailType === 'sorties')
                                        <td>{{ $item['patient'] ?? 'N/A' }}</td>
                                        <td>{{ $item['facture'] ?? 'N/A' }}</td>
                                    @endif
                                    @if($detailType === 'entrees')
                                        <td>{{ $item['reference'] ?? 'N/A' }}</td>
                                    @endif
                                @endif
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($this->detailTotalPages > 1)
                <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
                    <div class="flex items-center gap-1">
                        <button wire:click="previousDetailPage" @if($this->detailPage <= 1) disabled @endif
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        @php $startPage = max(1, $this->detailPage - 2); $endPage = min($this->detailTotalPages, $this->detailPage + 2); @endphp
                        @if($startPage > 1)
                        <button wire:click="goToDetailPage(1)" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">1</button>
                        @if($startPage > 2)<span class="px-1 text-gray-400">...</span>@endif
                        @endif
                        @for($i = $startPage; $i <= $endPage; $i++)
                        <button wire:click="goToDetailPage({{ $i }})" class="px-3 py-1.5 text-sm rounded {{ $i == $this->detailPage ? 'bg-primary text-white' : 'border border-gray-300 hover:bg-gray-50' }}">{{ $i }}</button>
                        @endfor
                        @if($endPage < $this->detailTotalPages)
                        @if($endPage < $this->detailTotalPages - 1)<span class="px-1 text-gray-400">...</span>@endif
                        <button wire:click="goToDetailPage({{ $this->detailTotalPages }})" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ $this->detailTotalPages }}</button>
                        @endif
                        <button wire:click="nextDetailPage" @if($this->detailPage >= $this->detailTotalPages) disabled @endif
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-40">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <span class="text-sm text-gray-500">Page {{ $this->detailPage }} / {{ $this->detailTotalPages }}</span>
                </div>
                @endif

                @else
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-inbox text-3xl mb-2 block"></i>
                    <p>Aucun détail disponible</p>
                </div>
                @endif

                <div class="flex justify-end mt-4">
                    <button wire:click="fermerDetailModal" class="btn-secondary">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal entrée de stock --}}
    @if($showEntreeModal)
    <div class="modal-overlay" style="z-index:70;">
        <div class="modal-box max-w-2xl w-full">
            <div class="modal-header">
                <div>
                    <h2><i class="fas fa-plus-circle mr-2"></i>Alimenter le stock</h2>
                    @if($entreeLibelleMedic)
                    <p>{{ $entreeLibelleMedic }}</p>
                    @endif
                </div>
                <button wire:click="closeEntreeModal" class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form wire:submit.prevent="enregistrerEntree" class="space-y-4">

                    {{-- Sélection médicament --}}
                    @if(!$entreeMedicamentId)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Médicament *</label>
                        <div class="relative">
                            <input type="text" wire:model.live.debounce.300ms="entreeSearchMedicament"
                                   placeholder="Rechercher un médicament..."
                                   class="form-input">
                            @if($entreeIsSearchingMedicament)
                            <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
                            </div>
                            @endif
                            @if($entreeShowMedicamentResults && count($entreeMedicamentsResults) > 0)
                            <div class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl z-50 max-h-48 overflow-y-auto">
                                @foreach($entreeMedicamentsResults as $med)
                                <div wire:click="selectEntreeMedicament({{ $med->IDMedic }})"
                                     class="px-4 py-2.5 hover:bg-primary-light cursor-pointer text-sm border-b border-gray-50 last:border-0">
                                    <span class="font-medium">{{ $med->LibelleMedic }}</span>
                                    <span class="text-gray-400 ml-2">{{ number_format($med->PrixRef ?? 0, 0) }} MRU</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                    @else
                    <div class="flex items-center justify-between px-4 py-2.5 bg-primary-light rounded-lg border border-primary/20">
                        <span class="text-sm font-semibold text-primary">{{ $entreeLibelleMedic }}</span>
                        <button type="button" wire:click="$set('entreeMedicamentId', null)" class="text-primary hover:text-primary-dark text-xs">
                            <i class="fas fa-times"></i> Changer
                        </button>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantité *</label>
                            <input type="number" wire:model.defer="entreeQuantite" class="form-input" min="1" required>
                            @error('entreeQuantite') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prix d'achat unitaire *</label>
                            <input type="number" step="0.01" wire:model.defer="entreePrixAchat" class="form-input" min="0">
                            @error('entreePrixAchat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Seuil minimum *</label>
                            <input type="number" wire:model.defer="entreeQuantiteMin" class="form-input" min="0" required>
                            @error('entreeQuantiteMin') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de lot</label>
                            <input type="text" wire:model.defer="entreeNumeroLot" class="form-input" placeholder="Optionnel">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date d'expiration</label>
                            <input type="date" wire:model.defer="entreeDateExpiration" class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fournisseur</label>
                            <input type="text" wire:model.defer="entreeFournisseur" class="form-input" placeholder="Optionnel">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Référence facture</label>
                            <input type="text" wire:model.defer="entreeReferenceFacture" class="form-input" placeholder="Optionnel">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <input type="text" wire:model.defer="entreeNotes" class="form-input" placeholder="Optionnel">
                        </div>
                    </div>

                    <div class="modal-footer px-0 pb-0">
                        <button type="button" wire:click="closeEntreeModal" class="btn-secondary">Annuler</button>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Enregistrer l'entrée
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

</div>
