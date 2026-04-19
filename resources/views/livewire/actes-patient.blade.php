<div>
    @if(session()->has('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-800 text-sm">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->has('general'))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first('general') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        {{-- Colonne gauche : recherche et liste des actes --}}
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Rechercher un acte</label>
            <input type="text"
                   wire:model.live.debounce.300ms="search_acte"
                   placeholder="Nom de l'acte..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-primary mb-2">

            <div class="border border-gray-200 rounded-lg overflow-hidden max-h-72 overflow-y-auto">
                @forelse($actes as $acte)
                    <div wire:click="ajouterLigne({{ $acte['ID'] }})"
                         class="flex items-center justify-between px-3 py-2 hover:bg-primary-light cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors">
                        <span class="text-sm font-medium text-gray-800">{{ $acte['Acte'] }}</span>
                        <span class="text-xs text-primary font-semibold">{{ number_format($acte['PrixRef'], 0) }} MRU</span>
                    </div>
                @empty
                    <div class="px-3 py-4 text-sm text-gray-400 text-center">
                        <i class="fas fa-search mr-1"></i> Aucun acte trouvé
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Colonne droite : actes sélectionnés --}}
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Actes sélectionnés</label>
            <div class="border border-gray-200 rounded-lg overflow-hidden min-h-[180px]">
                @forelse($lignes as $i => $ligne)
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 last:border-b-0">
                        <span class="flex-1 text-sm text-gray-800">{{ $ligne['acte_nom'] }}</span>
                        <input type="number"
                               wire:model.live="lignes.{{ $i }}.quantite"
                               min="1"
                               class="w-14 border border-gray-300 rounded px-1 py-0.5 text-sm text-center">
                        <span class="text-xs text-gray-500 w-20 text-right">
                            {{ number_format(floatval($ligne['prix']) * intval($ligne['quantite']), 0) }} MRU
                        </span>
                        <button wire:click="supprimerLigne({{ $i }})"
                                class="text-red-400 hover:text-red-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                @empty
                    <div class="flex items-center justify-center h-[180px] text-sm text-gray-400">
                        <div class="text-center">
                            <i class="fas fa-hand-pointer text-2xl mb-2 opacity-30"></i>
                            <p>Cliquez sur un acte pour l'ajouter</p>
                        </div>
                    </div>
                @endforelse
            </div>

            @if(count($lignes) > 0)
                <div class="mt-2 text-right text-sm font-bold text-primary">
                    Total : {{ number_format($total, 0) }} MRU
                </div>
            @endif
        </div>
    </div>

    {{-- Médecin + Mode de paiement --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Médecin <span class="text-red-500">*</span></label>
            <select wire:model="medecin_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                <option value="">Sélectionner...</option>
                @foreach($medecins as $med)
                    <option value="{{ $med->fkidmedecin }}">Dr. {{ $med->NomComplet }}</option>
                @endforeach
            </select>
            @error('medecin_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Mode de paiement <span class="text-red-500">*</span></label>
            <select wire:model="mode_paiement" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                <option value="">Sélectionner...</option>
                @foreach($typesPaiement as $type)
                    <option value="{{ $type->LibPaie }}">{{ $type->LibPaie }}</option>
                @endforeach
            </select>
            @error('mode_paiement') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    @error('lignes') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror

    {{-- Bouton enregistrer --}}
    <div class="mt-4 flex justify-end">
        <button wire:click="save"
                wire:loading.attr="disabled"
                @if(count($lignes) === 0) disabled @endif
                class="btn-primary px-6 py-2 text-sm disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="save">
                <i class="fas fa-check mr-1"></i> Enregistrer et facturer
            </span>
            <span wire:loading wire:target="save">
                <i class="fas fa-spinner fa-spin mr-1"></i> Enregistrement...
            </span>
        </button>
    </div>
</div>
