@extends('layouts.app')

@section('content')
<div class="fixed inset-0 bg-black/50 z-40 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col" style="max-height: calc(100vh - 2rem);">
        <div class="modal-header">
            <h2><i class="fas fa-users-cog mr-2"></i>Gestion des utilisateurs</h2>
            <a href="{{ route('accueil.patient') }}" class="modal-close"><i class="fas fa-times"></i></a>
        </div>
        <div class="modal-body">
            @livewire('user-manager')
        </div>
    </div>
</div>
@endsection
