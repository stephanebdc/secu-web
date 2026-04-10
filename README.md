# Sécurité Web

Module de sécurité PHP pour sites Apache — protection 404, blacklist de chemins, bannissement d'IPs, interface de gestion.

---

## Pourquoi ce module ?

Des solutions comme Cloudflare, Fail2ban ou les WAF cloud font très bien ce travail — et souvent mieux. Ce module n'est pas là pour les remplacer.

Son intérêt est différent : il fonctionne **localement, directement sur le serveur**, sans dépendance externe, sans proxy, sans abonnement. Tout est lisible, modifiable, contrôlable. Pour un hébergement mutualisé standard où on ne peut pas installer Fail2ban ni configurer un pare-feu réseau, c'est une couche de sécurité supplémentaire qu'on maîtrise entièrement.

### Limitation connue — performances

Actuellement, le module repose sur PHP exécuté à chaque requête. Cela a un coût en ressources serveur non négligeable, surtout sur les sites à fort trafic. **Ce point est en cours de correction** — l'objectif est de déplacer la logique critique (blacklist, ban) vers des règles `.htaccess` statiques évaluées directement par Apache, sans passer par PHP.

---

## Fonctionnalités

- Journalisation des visites (index) et des erreurs 404
- Blacklist de chemins suspects avec bannissement automatique d'IP
- Blacklist automatique des chemins WordPress sur sites non-WordPress
- Interface de gestion protégée (stats, logs, IPs bannies, blacklist)
- Vérification des IPs via AbuseIPDB
- Protection des fichiers sensibles via `.htaccess` + `.htpasswd`

---

## Installation (première fois)

### Prérequis
- Serveur Apache avec `mod_rewrite` et `AllowOverride All`
- PHP 7.4+
- Accès FTP ou panneau d'hébergement

### Étapes

**1. Déposer deux fichiers sur le serveur** (à la racine du site) :
- `setup.php`
- `install.php`

Les deux fichiers sont disponibles sur la branche `claude/code-audit-7zuYa`.

**2. Ouvrir `setup.php` dans le navigateur**

`setup.php` extrait automatiquement tous les fichiers nécessaires, met à jour `gestion.php` si déjà présent, puis se supprime.

**3. Ouvrir `install.php` dans le navigateur**

L'installeur guide en 8 étapes :

| Étape | Action |
|-------|--------|
| 1 | Vérification de la présence des fichiers requis |
| 2 | Sauvegarde du `.htaccess` existant (`ANCIEN-htaccess-YYYYMMDDHHMMSS`) |
| 3 | Détection WordPress — écrit `wordpress = 0` ou `1` dans `pipou.ini` |
| 4 | Vérification de la blacklist (faux positifs) |
| 5 | Clé API AbuseIPDB (optionnelle) |
| 6 | Identifiants administrateur (login + mot de passe) |
| 7 | Installation — génère `.htaccess`, `.htpasswd`, `gestion.php`, pages d'erreur |
| 8 | Finalisation — supprime `install.php` et `setup.token` automatiquement |

**Authentification de l'installeur** : à la première ouverture d'`install.php`, un token est généré dans `setup.token`. Il est affiché à l'écran — collez-le pour accéder à l'installeur.

---

## Mise à jour (installation existante)

Pas besoin de réinstaller. Il suffit de :

1. Télécharger `setup.php` (branche `claude/code-audit-7zuYa`) → Raw → sauvegarder
2. Uploader `setup.php` à la racine du site
3. Ouvrir `setup.php` dans le navigateur

`setup.php` met à jour tous les fichiers PHP **et `gestion.php`** automatiquement, puis se supprime. Vos données (blacklist, logs, `.htpasswd`, `.htaccess`) sont conservées.

---

## Réinstallation complète

Pour repartir de zéro (ex : changer de serveur, nettoyer une installation corrompue) :

1. Uploader `clean.php` à la racine du site
2. Ouvrir `clean.php` dans le navigateur — il affiche l'inventaire avant d'agir
3. Choisir si vous voulez conserver `blacklist.txt`
4. Confirmer — `clean.php` :
   - Supprime les fichiers générés (`gestion.php`, logs, pages d'erreur, `pipou.ini`…)
   - Restaure `.htaccess` depuis la sauvegarde originale (`ANCIEN-htaccess-*` le plus ancien)
   - Retire `include 'debut.php'` de `index.php`
   - Se supprime lui-même
5. Reprendre depuis l'étape **Installation** ci-dessus

---

## Structure des fichiers

```
# Fichiers sources (dans le dépôt)
debut.php                     — Logger de visites (inclus dans index.php)
404.php                       — Gestionnaire d'erreurs 404 (blacklist + ban IP)
gestion-template.php          — Template de l'interface de gestion
blacklistverif.php            — Vérification des chemins blacklistés
ifwordpress.php               — Module WordPress (injection thème)
debut404wpclassictheme.php    — Logger 404 pour thèmes WordPress
installation-de-gestion.php   — Moteur d'installation (appelé par install.php)
sample_index                  — Index de démo (renommé en index.php si absent)
blacklist.txt                 — Liste noire des chemins suspects

# Fichiers générés à l'installation
gestion.php                   — Interface de gestion (copie de gestion-template.php)
pipou.ini                     — Configuration (api_key, wordpress)
stats.html                    — Log des visites index
perdus_logs.html              — Log des erreurs 404
404_visitor_log.txt           — Log brut des chemins 404
yon-maru-yon.html             — Page d'erreur 404 personnalisée
403_.html                     — Page d'erreur 403 personnalisée
desactivated.txt              — Chemins blacklist temporairement désactivés
.htpasswd                     — Mots de passe (hors document root)

# Fichiers du pack de déploiement
setup.php                     — Extracteur (dépose tous les fichiers sources)
install.php                   — Installeur (8 étapes)
clean.php                     — Nettoyeur pré-réinstallation
```

---

## Blacklist automatique WordPress

Si le site n'est **pas** WordPress (`wordpress = 0` dans `pipou.ini`), tout chemin 404 correspondant à un pattern WordPress est automatiquement :
- Ajouté à `blacklist.txt`
- Suivi d'un bannissement immédiat de l'IP
- Redirigé vers la page 403

Patterns détectés : `/wp-admin/`, `/wp-login.php`, `/wp-includes/`, `/wp-content/`, `/wp-cron.php`, `/wordpress/`, `xmlrpc.php`, et tout fichier `wp-*.php`.

---

## Sécurité

- Tokens CSRF sur tous les formulaires POST
- Validation stricte des IPs (`filter_var(FILTER_VALIDATE_IP)`) avant écriture dans `.htaccess`
- Échappement HTML (`htmlspecialchars(ENT_QUOTES)`) sur toutes les sorties
- Protection contre le path traversal (`realpath()` + `strncmp()`)
- Authentification par token aléatoire (`bin2hex(random_bytes(20))`) pour l'installeur
- Mots de passe hashés avec `password_hash(PASSWORD_BCRYPT)`
- `session_regenerate_id(true)` après authentification
