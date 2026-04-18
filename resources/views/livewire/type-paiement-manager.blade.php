<div class="p-4 md:p-6">

    {{-- Formulaire ajout/édition --}}
    <div class="mb-6 bg-primary-light border-l-4 border-primary rounded-lg p-4">
        <h3 class="text-sm font-semibold text-primary uppercase tracking-wide mb-4">
            <i class="fas fa-{{ $editMode ? 'edit' : 'plus' }} mr-1"></i>
            {{ $editMode ? 'Modifier le type de paiement' : 'Ajouter un type de paiement' }}
        </h3>
        <form wire:submit.prevent="saveTypePaiement" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Libellé *</label>
                <input type="text" wire:model.defer="LibPaie" class="form-input" required>
                @error('LibPaie') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1">
                    <i class="fas fa-save"></i> {{ $editMode ? 'Mettre à jour' : 'Enregistrer' }}
                </button>
                @if($editMode)
                <button type="button" wire:click="resetForm" class="btn-secondary">Annuler</button>
                @endif
            </div>
        </form>
    </div>

    {{-- Tableau --}}
    <div class="overflow-x-auto max-h-[55vh] overflow-y-auto rounded-lg border border-gray-200">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Libellé</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $index => $type)
                <tr>
                    <td>{{ ($types->firstItem() ?? 0) + $index }}</td>
                    <td>{{ $type->LibPaie }}</td>
                    <td>
                        <button wire:click="editTypePaiement({{ $type->idtypepaie }})" class="text-primary hover:text-primary-dark text-sm font-medium mr-3">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button wire:click="deleteTypePaiement({{ $type->idtypepaie }})"
                                onclick="return confirm('Supprimer ce type de paiement ?')"
                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" class="text-center text-gray-400 py-8">
                        <i class="fas fa-credit-card text-2xl mb-2 block"></i> Aucun type de paiement trouvé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $types->links() }}</div>

</div>
