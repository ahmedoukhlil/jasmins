<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - SysMedical</title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">

            <!-- Carte de connexion -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

                <!-- En-tête avec logo -->
                <div class="px-8 py-8 text-center" style="background-color: var(--color-primary, #1e3a8a);">
                    <img src="/SysMedical.png" alt="SysMedical" style="height: 140px; width: 140px; display: block; margin: 0 auto; object-fit: contain; background: white; border-radius: 12px; padding: 8px;">
                    <p class="mt-4 text-sm" style="color: rgba(255,255,255,0.9); font-weight: 500; letter-spacing: 0.5px;">Votre cabinet digitalisé</p>
                </div>

                <div class="px-8 py-8">
                <p class="text-center text-sm text-gray-500 mb-6">Connectez-vous à votre compte</p>
                @if($errors->any())
                    <div class="mb-6 bg-primary-light border-l-4 border-primary p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-primary"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-primary">
                                    {{ $errors->first() }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf
                    
                    <!-- Champ Identifiant -->
                    <div>
                        <label for="login" class="block text-sm font-medium text-gray-700 mb-1">Identifiant</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-user text-gray-400"></i>
                            </div>
                            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus
                                class="pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                placeholder="Entrez votre identifiant">
                        </div>
                    </div>

                    <!-- Champ Mot de passe -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-lock text-gray-400"></i>
                            </div>
                            <input id="password" type="password" name="password" required
                                class="pl-10 w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                                placeholder="Entrez votre mot de passe">
                        </div>
                    </div>

                    <!-- Options de connexion -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember" type="checkbox" name="remember"
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-gray-700">
                                Se souvenir de moi
                            </label>
                        </div>
                    </div>

                    <!-- Bouton de connexion -->
                    <button type="submit"
                        class="w-full flex justify-center items-center px-4 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Se connecter
                    </button>
                </form>
                </div>{{-- fin px-8 py-8 --}}
            </div>

            <!-- Footer -->
            <div class="text-center mt-8">
                <p class="text-sm text-gray-600">
                    &copy; {{ date('Y') }} SysMedical. Tous droits réservés.
                </p>
            </div>
        </div>
    </div>
</body>
</html>