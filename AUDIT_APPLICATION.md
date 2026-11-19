# Audit technique et sécurité — Cabinet Savwa / `jasmins`

Date de l'audit : 2026-11-19
Dépôt : <https://github.com/ahmedoukhlil/jasmins>
Révision auditée : `896a15fdb6c8f43cf09af5c1f563365720b43afa` (`main`)

## Synthèse

L'application ne doit pas être considérée comme prête pour une mise en production contenant des données médicales. Le risque principal n'est pas cosmétique : plusieurs requêtes et mutations ne sont pas isolées par cabinet. Un utilisateur authentifié peut potentiellement lire, modifier ou supprimer les données d'un autre cabinet en manipulant des identifiants. Les liens patients publics acceptent également encore un ancien format de jeton non signé, ce qui rend possible la fabrication de liens donnant accès à des informations médicales.

Verdict : **risque critique — mise en production déconseillée avant correction des points P0**.

## P0 — Critique

### 1. Cloisonnement multi-cabinets absent ou incohérent

Les modèles portent bien un champ `fkidcabinet`/`fkidCabinet`, mais de nombreuses lectures et mutations utilisent un identifiant brut sans filtre sur le cabinet courant.

Preuves représentatives :

- `PatientManager::render()` démarre avec `Patient::query()` sans filtre cabinet (`app/Http/Livewire/PatientManager.php:351`). Les méthodes d'édition, changement d'état et suppression utilisent `Patient::find($id)` (`:170`, `:245`, `:278`, `:291`) puis une suppression en cascade non cloisonnée (`:300-335`).
- `UserManager` liste tous les utilisateurs via `TUser::query()` (`app/Http/Livewire/UserManager.php:92`) et modifie/supprime des comptes par identifiant brut (`:135`, `:209`, `:268`, `:283`, `:297`, `:326`).
- `RendezVousManager` ne filtre pas la liste par cabinet (`app/Http/Livewire/RendezVousManager.php:61`) et modifie les rendez-vous avec `Rendezvou::find($id)` (`:113`, `:138`, `:166`).
- Les contrôleurs d'impression/téléchargement chargent factures, ordonnances, patients et règlements par identifiant sans contrainte cabinet : `ConsultationController.php:22,50,100,114,201`, `OrdonnanceController.php:40,85`, `PaiementController.php:13`, `ReglementFactureController.php:17` et `routes/web.php:93`.

Impact : fuite et altération inter-cabinets de dossiers patients, factures, ordonnances, rendez-vous, utilisateurs et règlements ; suppression irréversible possible.

Correctif : introduire un scope de tenant obligatoire, des policies Laravel et des clés composites/contraintes DB. Toute ressource doit être résolue par `where('fkidCabinet', Auth::user()->fkidcabinet)->...->findOrFail($id)`. Ajouter des tests négatifs avec deux cabinets pour chaque lecture et mutation.

### 2. Jetons patients publics forgeables

`PatientInterfaceController::decodeTokenParts()` accepte explicitement un ancien jeton Base64 sans signature (`app/Http/Controllers/PatientInterfaceController.php:243`). Le contenu attendu inclut directement l'identifiant patient et une date. Une personne peut donc fabriquer des jetons en variant les identifiants.

Le contrôleur charge ensuite le patient par ID (`:35`, `:179`) et expose tous ses rendez-vous ou consultations, pas uniquement la ressource ayant servi à créer le lien. Le dernier fallback fondé uniquement sur la date retourne même le premier rendez-vous du jour (`:281-285`).

Impact : accès public non authentifié à des identités, rendez-vous, file d'attente et résumés de consultations.

Correctif : supprimer immédiatement tous les formats legacy non signés ; stocker un jeton aléatoire de forte entropie haché en base, associé à une ressource précise, une expiration et une révocation. Ne jamais exposer « toutes les consultations » avec un jeton de rendez-vous.

### 3. Authentification compatible avec les mots de passe en clair

Si la valeur stockée n'a pas le format bcrypt attendu, `AuthController::checkPassword()` effectue une comparaison directe (`app/Http/Controllers/Auth/AuthController.php:59`). Après ce chemin de connexion, la session n'est pas régénérée (`Auth::login` à la ligne 38), et la route POST de connexion n'a aucun throttling (`routes/web.php:26`).

Des identifiants apparemment réels (`moctar` / `123456`) sont répétés dans les tests versionnés et leurs rapports. Il faut considérer ce secret comme compromis.

Correctif : rotation immédiate du compte concerné ; migration forcée de tous les mots de passe vers Argon2id/bcrypt ; suppression du fallback en clair ; `session()->regenerate()` après connexion ; rate limiting par compte + IP ; MFA pour les propriétaires.

### 4. Dépendances vulnérables et versions en fin de vie

Résultats des outils officiels sur les fichiers verrouillés :

- `composer audit --locked` : **59 avis affectant 15 paquets** et 2 paquets abandonnés. La pile verrouille notamment Laravel `8.83.29`, Livewire `2.12.8`, Dompdf `2.0.8`, Guzzle `7.9.3` et Symfony HttpFoundation `5.4.48`.
- `npm audit --package-lock-only` : **49 dépendances vulnérables** — 5 critiques, 22 élevées, 14 modérées et 8 faibles. Axios est une dépendance directe vulnérable ; beaucoup d'autres sont dans la chaîne de build Laravel Mix/Webpack.
- Paquets abandonnés : `fruitcake/laravel-cors` et `swiftmailer/swiftmailer`.

Correctif : planifier une migration vers une version Laravel maintenue, remplacer SwiftMailer, mettre à jour Dompdf/Guzzle/Axios et reconstruire le lockfile. Ne pas appliquer aveuglément `npm audit fix --force` : valider la compilation et les parcours fonctionnels.

## P1 — Élevé

### 5. Autorisation métier insuffisante dans les composants Livewire

`UserManager` peut gérer comptes, rôles et permissions sans appel à `authorize()` ni contrôle `hasPermission()` dans ses méthodes publiques. `PatientManager` a le même problème pour les opérations sensibles. Les contrôles visuels ou le middleware de la page ne remplacent pas une autorisation dans chaque action serveur Livewire.

Correctif : policies explicites, vérification dans chaque méthode publique mutante, et interdiction par défaut. Tester l'appel direct des actions Livewire avec chaque rôle.

### 6. Suppression définitive massive de données médicales

`PatientManager::deletePatient()` supprime directement factures, détails, ordonnances, rendez-vous, mouvements de stock, consultations, analyses et dossier médical avant `Patient::destroy()` (`app/Http/Livewire/PatientManager.php:300-335`). Il n'existe ni soft-delete, ni journal d'audit, ni preuve d'une règle de conservation.

Correctif : désactivation logique, workflow d'approbation, journal immuable, sauvegarde/restauration testée et politique de conservation conforme au cadre juridique applicable.

### 7. Informations sensibles dans le dépôt et les logs

Le dépôt contient `testsprite_tests/tmp/mcp.log` (~8,4 Mo), des rapports de test, des parcours avec identifiants et des données patient visibles. `ConsultationController` journalise une facture complète (`app/Http/Controllers/ConsultationController.php:52`) et plusieurs contrôleurs journalisent les traces complètes. `PatientManager` renvoie le message SQL brut à l'utilisateur (`:269-270`).

Correctif : retirer ces artefacts de l'historique Git si nécessaire, ajouter des règles `.gitignore`, faire une recherche de secrets/PII, purger ou restreindre les logs et utiliser des messages d'erreur génériques avec identifiant de corrélation.

## P2 — Moyen

### Audit du calcul de stock — résultat critique

Le stock est géré par trois sources (`stock_medicaments.quantiteStock`, `lots_medicaments.quantiteRestante` et `mouvements_stock`) qui ne sont pas maintenues de façon atomique et cohérente.

#### Double déduction des ordonnances urgentes

`OrdonnanceManager` décrémente immédiatement `quantiteStock` (`app/Http/Livewire/OrdonnanceManager.php:451`) mais enregistre le mouvement avec la valeur minuscule `sortie` (`:487`). Au paiement complet, `ReglementFacture` ne cherche que `SORTIE` en majuscules (`app/Http/Livewire/ReglementFacture.php:289,1028`) et considère donc le stock comme non déduit, puis le déduit une seconde fois.

Exemple : stock 10, ordonnance urgente de 3 → stock 7 ; paiement complet → stock 4 au lieu de 7.

En outre, l'ordonnance vérifie seulement que le stock est supérieur à zéro, pas qu'il est supérieur ou égal à la quantité demandée (`OrdonnanceManager.php:439-451`). Une ordonnance de 5 avec un stock de 2 produit un stock de -3.

#### Création de stock fictif lors d'une suppression

Une facture créée depuis l'onglet Pharmacie ne déduit le stock qu'au paiement complet (`PharmacieManager.php:778-782`). Pourtant, la suppression d'une ligne médicament ou de la facture restaure systématiquement la quantité à partir des détails, sans vérifier qu'un mouvement `SORTIE` correspondant existe (`ReglementFacture.php:726-738,1090-1118`).

Exemple : stock 10, facture non payée de 3 → stock toujours 10 ; suppression de la facture → stock 13.

#### Lots jamais décrémentés

Les entrées créent et augmentent les lots, mais aucun chemin de sortie ne diminue `quantiteRestante`. Les seules mutations ultérieures trouvées sont des restaurations avec `+=` (`ReglementFacture.php:758,1138`). Les méthodes annoncées comme FIFO créent des mouvements avec `fkidLot = null` et modifient seulement le stock global (`ReglementFacture.php:956-1059`).

Conséquences : les alertes de péremption restent fausses, la somme des lots ne correspond plus au stock global et une suppression peut restaurer des lots qui n'ont jamais été consommés.

#### Valorisation et bénéfice incorrects

- À chaque entrée, `stock_medicaments.prixAchat` est remplacé par le dernier prix (`PharmacieManager.php:490-491`), puis le tableau de bord calcule `quantiteStock × dernier prix` (`:893-898`). Ce n'est ni un coût moyen pondéré ni une valorisation FIFO.
- La vue détaillée utilise parfois la somme des lots et parfois le stock global (`:1002-1012`), donnant des montants différents pour le même stock.
- Le bénéfice effectue une jointure interne sur les lots alors que les sorties ont `fkidLot = null`, et calcule `prixUnitaire mouvement - prixAchat lot` alors que le prix du mouvement est lui-même un prix d'achat (`:950-963`). Le bénéfice peut donc être nul ou exclure la plupart des ventes au lieu d'utiliser `PrixFacture - coût de sortie`.

#### Réservation et concurrence

La quantité « déjà facturée non payée » n'est pas filtrée par cabinet (`PharmacieManager.php:720-731`) et peut réduire artificiellement le disponible d'un autre cabinet. Les lectures/modifications de stock n'utilisent pas `lockForUpdate()`, ce qui permet à deux ventes simultanées de valider le même disponible.

Correctif recommandé : créer un service unique de stock transactionnel. Toute entrée/sortie/annulation doit verrouiller la ligne stock, consommer/restaurer les lots FEFO/FIFO, créer un mouvement normalisé par enum, puis recalculer ou vérifier l'invariant `quantiteStock = somme(quantiteRestante des lots) + stock sans lot`. Une facture doit avoir un état explicite de réservation/déduction ; une annulation ne restaure que les mouvements réellement passés. Ajouter une contrainte d'idempotence par détail de facture et des tests de concurrence.

### 8. Routes de démonstration disponibles aux utilisateurs authentifiés

`/test-api`, `/modal-demo`, `/animation-test` et `/test-modals` sont enregistrées dans le groupe authentifié (`routes/web.php:180-183`). Le fichier `.env.example` active `APP_DEBUG=true`, et `TrustHosts` est désactivé dans le kernel (`app/Http/Kernel.php:17`).

Correctif : exclure les routes de test en production, fournir un exemple d'environnement sûr, activer la validation des hôtes et ajouter des en-têtes CSP/HSTS adaptés.

### 9. Risque XSS via `innerHTML`

Le layout insère directement `${message}` dans `innerHTML` (`resources/views/layouts/app.blade.php:112`). Certaines erreurs serveur incluent des messages d'exception ou de base de données. Un contenu contrôlé par un utilisateur peut donc devenir du HTML exécutable selon le chemin emprunté.

Correctif : utiliser `textContent`, créer les nœuds DOM séparément et ne jamais injecter les messages d'exception dans l'interface.

### 10. Défaut fonctionnel lors de l'enregistrement d'un patient

Le formulaire ignore les propriétés `prenom` et `nomContact` : il enregistre le nom dans `Prenom` et `NomContact` (`app/Http/Livewire/PatientManager.php:225,235`).

Correctif : affecter `$this->prenom` et `$this->nomContact`, puis ajouter un test de création/modification vérifiant chaque champ.

## Qualité, tests et maintenabilité

- 446 fichiers suivis, dont 334 fichiers PHP/Blade/JS environ.
- Les 235 fichiers PHP effectivement extraits et contrôlés passent `php -l` ; un fichier de migration et plusieurs tests aux noms très longs n'ont pas pu être extraits sur Windows sans activer les chemins longs.
- `composer.json` est valide.
- Le dossier `tests/` ne contient que les deux tests Laravel d'exemple. Le test feature attend `/` en HTTP 200 alors que l'application redirige vers `/login`, donc il est probablement déjà incohérent.
- Les 50 artefacts TestSprite sont surtout des scripts navigateur fragiles fondés sur des XPath absolus. Certains « tests réussis » ne vérifient que l'existence d'une URL ; ils ne constituent pas une suite de non-régression fiable.
- La documentation annonce Laravel 10 alors que le lockfile contient Laravel 8.83.29.
- Plusieurs composants dépassent 1 000 lignes et mélangent UI, autorisation, transactions, facturation et accès DB, ce qui augmente fortement le risque de régression.

## Plan recommandé

### Dans les 24 heures

1. Suspendre toute exposition publique contenant de vraies données médicales.
2. Révoquer les jetons patients legacy et faire expirer les liens existants.
3. Faire tourner le mot de passe `moctar/123456` et tout secret réutilisé.
4. Bloquer les accès inter-cabinets au niveau middleware/policies/scopes, en commençant par patients, utilisateurs, factures, ordonnances, règlements et rendez-vous.
5. Désactiver les suppressions définitives et les routes de test.

### Première semaine

1. Écrire une matrice rôles × actions et des tests avec deux cabinets.
2. Corriger tous les accès par ID et centraliser le contexte tenant.
3. Mettre à niveau les dépendances critiques sur une branche dédiée.
4. Nettoyer les secrets, logs et rapports versionnés ; réécrire l'historique seulement après validation opérationnelle.
5. Mettre en place CI : analyse statique, tests, `composer audit`, `npm audit`, détection de secrets.

### Ensuite

1. Migrer vers une version Laravel/Livewire maintenue.
2. Découper les gros composants en services, actions, policies et formulaires dédiés.
3. Ajouter journaux d'audit, sauvegardes restaurables, chiffrement des données sensibles et supervision.
4. Faire réaliser un test d'intrusion applicatif après correction des P0/P1.

## Limites de l'audit

Audit statique du code et des dépendances au commit indiqué. Aucune base de production, configuration serveur, instance déployée ni donnée réelle n'a été utilisée. Les tests fonctionnels complets n'ont pas été exécutés car les dépendances ne sont pas installées dans la copie temporaire et l'application dépend d'un schéma MySQL métier. Les constats de cloisonnement et de jetons publics sont néanmoins directement confirmés par le code.
