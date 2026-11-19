<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $__cab = \App\Models\Infocabinet::find(Auth::user()->fkidcabinet ?? 0) ?? \App\Models\Infocabinet::first();
        $__appName = ($__cab && !empty($__cab->nom_application)) ? $__cab->nom_application : 'SysMedical';
        $__logo = ($__cab && !empty($__cab->logo) && file_exists(public_path($__cab->logo))) ? asset($__cab->logo) : asset('SysMedical.png');
    @endphp
    <title>{{ $__appName }}</title>
    <!-- Tailwind CSS compilé -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Alpine.js + app bundle -->
    <script src="{{ asset('js/app.js') }}" defer></script>
    @livewireStyles

</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-white shadow-lg border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-8">
                    <div class="flex items-center">
                        <a href="{{ route('accueil.patient') }}" class="flex items-center">
                            <img src="{{ $__logo }}" alt="{{ $__appName }}" class="h-8 w-auto">
                        </a>
                    </div>
                    <!-- Menu desktop -->
                    <div class="hidden md:flex space-x-4">
                        
                    </div>
                </div>

                <!-- Bouton menu mobile -->
                <div class="flex items-center md:hidden">
                    <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200"
                            aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobile-navigation">
                        <i class="fas fa-bars" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Menu utilisateur -->
                <div class="hidden md:flex items-center space-x-4">
                    @if(auth()->check())
                        <div class="relative" data-user-menu>
                            <button type="button" data-user-menu-button aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-dropdown" class="flex items-center space-x-3 px-4 py-2 rounded-lg text-gray-700 hover:text-blue-600 hover:bg-blue-50 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 border border-gray-200">
                                <span class="font-medium">{{ auth()->user()->NomComplet ?? auth()->user()->name ?? 'Utilisateur' }}</span>
                                <i class="fas fa-chevron-down text-sm transition-colors duration-200" aria-hidden="true"></i>
                            </button>
                            <div id="user-menu-dropdown" data-user-menu-dropdown class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 z-50">
                                <div class="py-1">
                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="block">
                                        @csrf
                                        <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-all duration-200">
                                            Déconnexion
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm hover:shadow">
                            Connexion
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <!-- Menu mobile -->
        <div id="mobile-navigation" class="mobile-menu hidden md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-2">
               
                
                @if(auth()->check())
                    <form id="mobile-logout-form" action="{{ route('logout') }}" method="POST" class="block">
                        @csrf
                        <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-blue-50 hover:text-blue-600 transition-all duration-200">
                            Déconnexion
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="py-4">
        @yield('content')
    </main>

    <!-- Conteneur de toasts -->
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

    @livewireScripts


    <script>
        /* ─── Toast system ─── */
        window.showToast = function(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const icons = { success:'fa-check-circle', error:'fa-exclamation-circle', warning:'fa-exclamation-triangle', info:'fa-info-circle' };
            const colors = { success:'#16a34a', error:'#dc2626', warning:'#d97706', info:'var(--color-primary)' };
            const t = document.createElement('div');
            t.className = `toast toast-${type}`;
            t.setAttribute('role', type === 'error' ? 'alert' : 'status');

            const icon = document.createElement('i');
            icon.className = `fas ${icons[type] || icons.info}`;
            icon.style.color = colors[type] || colors.info;
            icon.style.flexShrink = '0';
            icon.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.textContent = message;
            text.style.flex = '1';

            const closeButton = document.createElement('button');
            closeButton.type = 'button';
            closeButton.className = 'toast-close';
            closeButton.setAttribute('aria-label', 'Fermer la notification');
            closeButton.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';

            t.append(icon, text, closeButton);
            container.appendChild(t);
            let timer;
            const dismiss = () => {
                window.clearTimeout(timer);
                t.classList.add('toast-hide');
                window.setTimeout(() => t.remove(), 220);
            };
            closeButton.addEventListener('click', dismiss);
            timer = window.setTimeout(dismiss, type === 'error' ? Math.max(duration, 8000) : duration);
        };

        /* ─── Livewire toast bridge ─── */
        document.addEventListener('livewire:load', function() {
            Livewire.on('toast', ({ message, type }) => window.showToast(message, type || 'info'));
        });

        /* ─── WhatsApp opener ─── */
        window.whatsappWindow = window.whatsappWindow || null;
        window.openWhatsApp = function(url, cb) {
            try {
                if (window.whatsappWindow && !window.whatsappWindow.closed) {
                    window.whatsappWindow.location.href = url;
                    window.whatsappWindow.focus();
                } else {
                    window.whatsappWindow = window.open(url, '_blank', 'noopener,noreferrer')
                                        || window.open(url, '_blank');
                }
                if (cb) cb();
            } catch(e) {
                navigator.clipboard?.writeText(url).then(() =>
                    window.showToast('Lien WhatsApp copié dans le presse-papiers.', 'info')
                );
                if (cb) cb();
            }
        };
    </script>

    <!-- Script pour le menu mobile -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.querySelector('.mobile-menu-button');
            const menu = document.querySelector('.mobile-menu');

            btn?.addEventListener('click', () => {
                const willOpen = menu.classList.contains('hidden');
                menu.classList.toggle('hidden', !willOpen);
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                btn.setAttribute('aria-label', willOpen ? 'Fermer le menu' : 'Ouvrir le menu');
            });

            const userMenu = document.querySelector('[data-user-menu]');
            const userMenuButton = userMenu?.querySelector('[data-user-menu-button]');
            const userMenuDropdown = userMenu?.querySelector('[data-user-menu-dropdown]');
            const closeUserMenu = () => {
                userMenuDropdown?.classList.add('hidden');
                userMenuButton?.setAttribute('aria-expanded', 'false');
            };

            userMenuButton?.addEventListener('click', () => {
                const willOpen = userMenuDropdown.classList.contains('hidden');
                userMenuDropdown.classList.toggle('hidden', !willOpen);
                userMenuButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            document.addEventListener('click', (event) => {
                if (userMenu && !userMenu.contains(event.target)) closeUserMenu();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && userMenuButton?.getAttribute('aria-expanded') === 'true') {
                    closeUserMenu();
                    userMenuButton.focus();
                }
            });
        });
    </script>

    <script>
        /* Gestion centralisée et accessible des modales Livewire. */
        document.addEventListener('DOMContentLoaded', function () {
            let activeOverlay = null;
            let modalCounter = 0;
            let syncQueued = false;

            const visibleOverlays = () => Array.from(document.querySelectorAll('.modal-overlay'))
                .filter((overlay) => overlay.getClientRects().length > 0)
                .sort((a, b) => {
                    const zA = Number.parseInt(getComputedStyle(a).zIndex, 10) || 0;
                    const zB = Number.parseInt(getComputedStyle(b).zIndex, 10) || 0;
                    return zA === zB
                        ? Array.from(document.querySelectorAll('.modal-overlay')).indexOf(a) - Array.from(document.querySelectorAll('.modal-overlay')).indexOf(b)
                        : zA - zB;
                });

            const focusableElements = (box) => Array.from(box.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter((element) => element.getClientRects().length > 0);

            const enhanceOverlay = (overlay) => {
                const box = overlay.querySelector(':scope > .modal-box') || overlay.querySelector('.modal-box');
                if (!box) return;

                box.setAttribute('role', 'dialog');
                box.setAttribute('tabindex', '-1');
                const title = box.querySelector('.modal-header h2, .modal-header h3, h2, h3');
                if (title) {
                    if (!title.id) title.id = `modal-title-${++modalCounter}`;
                    box.setAttribute('aria-labelledby', title.id);
                } else {
                    box.setAttribute('aria-label', 'Fenêtre de dialogue');
                }

                box.querySelectorAll('.modal-close, .modal-close-button').forEach((button) => {
                    if (!button.getAttribute('aria-label')) button.setAttribute('aria-label', 'Fermer la fenêtre');
                });
            };

            const syncModals = () => {
                syncQueued = false;
                const overlays = visibleOverlays();
                overlays.forEach(enhanceOverlay);
                const nextActive = overlays[overlays.length - 1] || null;

                overlays.forEach((overlay) => {
                    const box = overlay.querySelector(':scope > .modal-box') || overlay.querySelector('.modal-box');
                    const isActive = overlay === nextActive;
                    const containsActiveModal = Boolean(nextActive && overlay.contains(nextActive));
                    overlay.setAttribute('aria-hidden', isActive || containsActiveModal ? 'false' : 'true');
                    box?.setAttribute('aria-modal', isActive ? 'true' : 'false');
                });

                document.body.classList.toggle('modal-open', overlays.length > 0);

                if (activeOverlay !== nextActive) {
                    const previous = activeOverlay;
                    activeOverlay = nextActive;

                    if (nextActive) {
                        const returnTarget = previous?._returnFocus;
                        if (returnTarget?.isConnected && nextActive.contains(returnTarget)) {
                            window.setTimeout(() => returnTarget.focus({ preventScroll: true }), 0);
                            return;
                        }
                        if (!nextActive._returnFocus || !nextActive._returnFocus.isConnected) {
                            nextActive._returnFocus = document.activeElement;
                        }
                        const box = nextActive.querySelector(':scope > .modal-box') || nextActive.querySelector('.modal-box');
                        const autofocus = box?.querySelector('[autofocus]');
                        window.setTimeout(() => (autofocus || box)?.focus({ preventScroll: true }), 0);
                    } else if (previous?._returnFocus?.isConnected) {
                        previous._returnFocus.focus({ preventScroll: true });
                    }
                }
            };

            const scheduleSync = () => {
                if (syncQueued) return;
                syncQueued = true;
                window.requestAnimationFrame(syncModals);
            };

            // Les clics sur l'arrière-plan ne ferment plus un formulaire en cours.
            document.addEventListener('click', (event) => {
                if (event.target.classList?.contains('modal-overlay')) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);

            document.addEventListener('keydown', (event) => {
                if (!activeOverlay) return;
                const box = activeOverlay.querySelector(':scope > .modal-box') || activeOverlay.querySelector('.modal-box');
                if (!box) return;

                if (event.key === 'Escape') {
                    const closeButton = box.querySelector('.modal-close, .modal-close-button');
                    if (closeButton) {
                        event.preventDefault();
                        event.stopPropagation();
                        closeButton.click();
                    }
                    return;
                }

                if (event.key === 'Tab') {
                    const focusable = focusableElements(box);
                    if (!focusable.length) {
                        event.preventDefault();
                        box.focus();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && (document.activeElement === first || !box.contains(document.activeElement))) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            }, true);

            new MutationObserver(scheduleSync).observe(document.body, { childList: true, subtree: true });
            document.addEventListener('livewire:load', scheduleSync);
            scheduleSync();
        });
    </script>

    <script>
        /* ─── Confirm dialog stylisé (remplace window.confirm) ─── */
        window.confirmAction = function(title, message, onConfirm) {
            // Supprimer un éventuel dialog existant
            document.getElementById('_confirm_dialog')?.remove();
            const returnFocus = document.activeElement;

            const overlay = document.createElement('div');
            overlay.id = '_confirm_dialog';
            overlay.className = 'confirm-dialog-overlay';
            overlay.innerHTML = `
                <div class="confirm-dialog-box" role="dialog" aria-modal="true" aria-labelledby="_cd_title">
                    <h3 id="_cd_title">${title}</h3>
                    ${message ? `<p>${message}</p>` : ''}
                    <div class="confirm-dialog-actions">
                        <button id="_cd_cancel" class="btn-secondary text-sm">Annuler</button>
                        <button id="_cd_ok"     class="btn-danger text-sm">Confirmer</button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);

            const close = () => {
                document.removeEventListener('keydown', onKeyDown, true);
                overlay.remove();
                if (returnFocus?.isConnected) returnFocus.focus({ preventScroll: true });
            };
            const onKeyDown = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    close();
                    return;
                }
                if (event.key === 'Tab') {
                    const buttons = Array.from(overlay.querySelectorAll('button'));
                    const first = buttons[0];
                    const last = buttons[buttons.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            };
            document.addEventListener('keydown', onKeyDown, true);
            overlay.querySelector('#_cd_cancel').addEventListener('click', close);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
            overlay.querySelector('#_cd_ok').addEventListener('click', () => {
                close();
                if (typeof onConfirm === 'function') onConfirm();
            });
            // Focus sur le bouton Annuler par défaut (accessibilité)
            setTimeout(() => overlay.querySelector('#_cd_cancel').focus(), 50);
        };
    </script>

    <script>
        /* ─── Impression modale historique paiements ─── */
        document.addEventListener('imprimer-modal', function () {
            const printModal = document.getElementById('historique-paiement-modal');
            if (!printModal) return;
            const w = window.open('', 'PRINT', 'height=800,width=1200');
            let styles = [...document.querySelectorAll('link[rel="stylesheet"]')].map(n => n.outerHTML).join('');
            styles += '<style>body{background:#fff;font-family:Arial,sans-serif;font-size:12px;padding:20px}table{width:100%;border-collapse:collapse;margin-bottom:20px}th,td{border:1px solid #000;padding:4px 8px}th{background:#f3f4f6;-webkit-print-color-adjust:exact}@page{size:A4;margin:10mm}</style>';
            w.document.write(`<html><head><title>Historique des paiements</title>${styles}</head><body>${printModal.innerHTML}</body></html>`);
            w.document.close();
            w.focus();
            setTimeout(() => { w.print(); w.close(); }, 400);
        });

        /* ─── Feedback visuel boutons sous-menu patient ─── */
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.patient-nav-button');
            if (!btn) return;
            // Retirer active sur tous les boutons du sous-menu
            document.querySelectorAll('.patient-nav-button').forEach(b => b.classList.remove('active'));
            // Appliquer active sur le bouton cliqué
            btn.classList.add('active');
        });

        /* ─── Ouverture reçu (open-receipt) ─── */
        if (!window.openReceiptListenerAdded) {
            window.addEventListener('open-receipt', function(e) {
                if (e.detail?.url) window.open(e.detail.url, '_blank');
            });
            window.openReceiptListenerAdded = true;
        }
    </script>
</body>
</html>
