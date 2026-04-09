<?php
/**
 * clean.php — Nettoyage avant mise à jour
 * Supprime les fichiers générés par l'ancienne installation
 * et restaure .htaccess + index.php dans leur état d'origine.
 *
 * Utilisation : déposez ce fichier seul, ouvrez-le dans le navigateur.
 */

// -----------------------------------------------------------------------
// Inventaire de ce qui sera nettoyé
// -----------------------------------------------------------------------

// Fichiers générés par l'installeur (à supprimer)
$toDelete = [
    'gestion.php'           => 'Interface de gestion (copie générée)',
    'stats.html'            => 'Log des visites index',
    'perdus_logs.html'      => 'Log des 404',
    '404_visitor_log.txt'   => 'Log brut des 404',
    'yon-maru-yon.html'     => 'Page d\'erreur 404 personnalisée',
    '403_.html'             => 'Page d\'erreur 403 personnalisée',
    'pipou.ini'             => 'Configuration clé API AbuseIPDB',
    'desactivated.txt'      => 'Chemins blacklist désactivés',
    'installation_beta3.php'=> 'Ancien installeur (version beta)',
    'setup.token'           => 'Token d\'installation résiduel',
];

// Fichiers optionnels (demander à l'utilisateur)
$optional = [
    'blacklist.txt' => 'Liste noire des chemins suspects (données accumulées)',
];

// Chercher les sauvegardes .htaccess générées par l'installeur
$htaccessBackups = glob(__DIR__ . '/ANCIEN-htaccess-*') ?: [];
sort($htaccessBackups);

// Détecter la présence d'un .htaccess modifié par l'installeur
$htaccessModified = false;
$htaccessExists   = file_exists(__DIR__ . '/.htaccess');
if ($htaccessExists) {
    $htContent = file_get_contents(__DIR__ . '/.htaccess');
    $htaccessModified = (
        strpos($htContent, 'ErrorDocument 404 /404.php') !== false ||
        strpos($htContent, 'Restricted Access') !== false ||
        strpos($htContent, 'AuthUserFile') !== false
    );
}

// Détecter si index.php a été modifié (include debut.php)
$indexModified = false;
$indexFile     = null;
foreach (['index.php', 'index.html', 'index.htm'] as $f) {
    if (file_exists($f)) { $indexFile = $f; break; }
}
if ($indexFile === 'index.php') {
    $indexContent  = file_get_contents('index.php');
    $indexModified = strpos($indexContent, "include 'debut.php'") !== false
                  || strpos($indexContent, 'include "debut.php"') !== false;
}

// -----------------------------------------------------------------------
// Traitement du formulaire de confirmation
// -----------------------------------------------------------------------
$done   = false;
$log    = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {

    $keepBlacklist = isset($_POST['keep_blacklist']);

    // 1 — Suppression des fichiers générés
    foreach ($toDelete as $file => $label) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            if (@unlink($path)) {
                $log[] = ['ok', "Supprimé : $file"];
            } else {
                $errors[] = "Impossible de supprimer : $file (permissions ?)";
            }
        }
    }

    // 2 — Blacklist (selon choix utilisateur)
    if (!$keepBlacklist && file_exists(__DIR__ . '/blacklist.txt')) {
        if (@unlink(__DIR__ . '/blacklist.txt')) {
            $log[] = ['ok', 'Supprimé : blacklist.txt'];
        } else {
            $errors[] = 'Impossible de supprimer : blacklist.txt';
        }
    } elseif ($keepBlacklist && file_exists(__DIR__ . '/blacklist.txt')) {
        $log[] = ['info', 'Conservé : blacklist.txt'];
    }

    // 3 — Sauvegardes ANCIEN-htaccess-*
    foreach ($htaccessBackups as $backup) {
        $name = basename($backup);
        if (@unlink($backup)) {
            $log[] = ['ok', "Supprimé : $name"];
        } else {
            $errors[] = "Impossible de supprimer : $name";
        }
    }

    // 4 — Restauration .htaccess
    if ($htaccessExists && $htaccessModified) {
        $bestBackup = !empty($htaccessBackups) ? end($htaccessBackups) : null;

        if ($bestBackup && file_exists($bestBackup)) {
            // Restaurer depuis la sauvegarde la plus récente
            if (@copy($bestBackup, __DIR__ . '/.htaccess')) {
                $log[] = ['ok', '.htaccess restauré depuis ' . basename($bestBackup)];
            } else {
                $errors[] = 'Impossible de restaurer .htaccess';
            }
        } else {
            // Pas de sauvegarde : on nettoie manuellement les sections connues
            $cleaned = cleanHtaccess($htContent);
            if (@file_put_contents(__DIR__ . '/.htaccess', $cleaned) !== false) {
                $log[] = ['ok', '.htaccess nettoyé (sections secu-web supprimées)'];
            } else {
                $errors[] = 'Impossible de réécrire .htaccess';
            }
        }
    } elseif (!$htaccessExists) {
        $log[] = ['info', 'Pas de .htaccess à nettoyer'];
    } elseif (!$htaccessModified) {
        $log[] = ['info', '.htaccess non modifié — inchangé'];
    }

    // 5 — Nettoyage de index.php (retrait de include debut.php)
    if ($indexModified) {
        $clean = preg_replace("/^<\?php\s*\n(?:include ['\"]debut\.php['\"];?\n)/m", "<?php\n", $indexContent);
        // Si le fichier ne contenait QUE le include (old html→php), on restaure l'original
        $clean = preg_replace("/^<\?php\s*\ninclude ['\"]debut\.php['\"];?\n\?>\n/", '', $clean);
        if (@file_put_contents('index.php', $clean) !== false) {
            $log[] = ['ok', 'index.php nettoyé (include debut.php retiré)'];
        } else {
            $errors[] = 'Impossible de modifier index.php';
        }
    } elseif ($indexFile) {
        $log[] = ['info', "$indexFile non modifié — inchangé"];
    }

    // 6 — Auto-suppression de ce fichier
    if (@unlink(__FILE__)) {
        $log[] = ['ok', 'clean.php supprimé automatiquement'];
    }

    $done = true;
}

// -----------------------------------------------------------------------
// Nettoyage manuel du .htaccess (si pas de backup)
// Retire les blocs <Files> et directives ajoutés par l'installeur
// -----------------------------------------------------------------------
function cleanHtaccess($content) {
    // Retirer les blocs <Files "..."> ajoutés par secu-web
    $content = preg_replace('/<Files\s+"[^"]+"\s*>\s*\n(?:(?:[ \t]+[^\n]*\n)*?)\s*<\/Files>\s*\n?/m', '', $content);
    // Retirer les ErrorDocument ajoutés
    $content = preg_replace('/^ErrorDocument\s+(404|403)\s+\/(404\.php|403_\.html)\s*$/m', '', $content);
    // Retirer les blocs RewriteEngine ajoutés par secu-web (bloc connu)
    $content = preg_replace(
        '/\nRewriteEngine On\nRewriteCond %\{THE_REQUEST\}.*?\n.*?\n.*?\n.*?\n/s',
        "\n",
        $content
    );
    // Retirer les commentaires de protection de sauvegarde
    $content = preg_replace('/^# (?:Protection|Sauvegarde)[^\n]*\n/m', '', $content);
    // Retirer les "deny from IP" (bans ajoutés en temps réel)
    $content = preg_replace('/^(?:#\s*)?deny from \S+\s*$/m', '', $content);
    // Retirer les lignes vides multiples
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    return trim($content) . "\n";
}

// -----------------------------------------------------------------------
// HTML
// -----------------------------------------------------------------------
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage — Sécurité Web</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:2rem 1rem;}
        .card{background:white;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);width:100%;max-width:560px;padding:2rem;}
        h1{font-size:1.5rem;color:#1a1a2e;margin-bottom:.25rem;}
        .sub{color:#6b7280;font-size:.9rem;margin-bottom:1.5rem;}
        h3{font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin:1.25rem 0 .5rem;}
        .item{display:flex;align-items:flex-start;gap:.6rem;padding:.35rem 0;border-bottom:1px solid #f3f4f6;font-size:.875rem;}
        .item:last-child{border:none;}
        .badge{display:inline-block;padding:.1rem .45rem;border-radius:3px;font-size:.72rem;font-weight:700;white-space:nowrap;flex-shrink:0;margin-top:.05rem;}
        .badge-del{background:#fee2e2;color:#b91c1c;}
        .badge-opt{background:#fef3c7;color:#92400e;}
        .badge-ht {background:#ede9fe;color:#5b21b6;}
        .badge-idx{background:#e0f2fe;color:#075985;}
        .badge-none{background:#f3f4f6;color:#9ca3af;}
        .item-label{flex:1;}
        .item-sub{color:#9ca3af;font-size:.78rem;}
        code{background:#f3f4f6;padding:.1rem .3rem;border-radius:3px;font-size:.8rem;}
        .separator{border:none;border-top:2px solid #f3f4f6;margin:1.25rem 0;}
        /* Options */
        .option{display:flex;align-items:center;gap:.75rem;padding:.75rem;background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;margin:.5rem 0;cursor:pointer;}
        .option input{width:16px;height:16px;cursor:pointer;}
        .option-label{font-size:.875rem;color:#374151;}
        .option-sub{font-size:.78rem;color:#9ca3af;}
        /* Alerts */
        .alert{display:flex;align-items:flex-start;gap:.5rem;padding:.7rem 1rem;border-radius:6px;margin:.4rem 0;font-size:.875rem;}
        .alert-warn{background:#fffbeb;border:1px solid #fcd34d;color:#78350f;}
        .alert-info{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;}
        /* Result items */
        .res-ok  {color:#059669;font-weight:700;}
        .res-info{color:#2563eb;font-weight:700;}
        .res-err {color:#dc2626;font-weight:700;}
        /* Buttons */
        .btn-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.5rem;}
        .btn{display:inline-block;padding:.7rem 1.4rem;border-radius:6px;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none;border:none;}
        .btn-danger{background:#dc2626;color:white;} .btn-danger:hover{background:#b91c1c;}
        .btn-success{background:#059669;color:white;} .btn-success:hover{background:#047857;}
        .btn-secondary{background:#6b7280;color:white;} .btn-secondary:hover{background:#4b5563;}
        .summary-ok {background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:.75rem 1rem;border-radius:6px;margin:.75rem 0;font-weight:600;}
        .summary-err{background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:.75rem 1rem;border-radius:6px;margin:.75rem 0;}
        .note{font-size:.8rem;color:#9ca3af;margin-top:.75rem;}
    </style>
</head>
<body>
<div class="card">

<?php if ($done): ?>

    <h1>🧹 Nettoyage terminé</h1>
    <p class="sub">Voici ce qui a été effectué.</p>

    <?php foreach ($log as [$type, $msg]): ?>
    <div class="item">
        <span class="res-<?= $type ?>"><?= $type === 'ok' ? '✓' : 'ℹ' ?></span>
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endforeach; ?>

    <?php foreach ($errors as $err): ?>
    <div class="item"><span class="res-err">✗</span> <?= htmlspecialchars($err) ?></div>
    <?php endforeach; ?>

    <?php if (empty($errors)): ?>
    <div class="summary-ok" style="margin-top:1rem;">✓ Nettoyage réussi — le serveur est prêt pour une nouvelle installation.</div>
    <?php else: ?>
    <div class="summary-err">⚠ Nettoyage partiel — corrigez les erreurs ci-dessus manuellement.</div>
    <?php endif; ?>

    <div class="btn-row">
        <?php if (file_exists('setup.php')): ?>
        <a href="setup.php" class="btn btn-success">Lancer setup.php →</a>
        <?php else: ?>
        <div class="alert alert-info" style="margin-top:0;">
            <span>ℹ</span> Déposez maintenant <code>setup.php</code> + <code>install.php</code> et ouvrez <code>setup.php</code>.
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>

    <h1>🧹 Nettoyage pré-installation</h1>
    <p class="sub">Voici ce qui va être supprimé ou restauré sur ce serveur.</p>

    <!-- Fichiers à supprimer -->
    <h3>Fichiers à supprimer</h3>
    <?php
    $anyFound = false;
    foreach ($toDelete as $file => $label):
        $exists = file_exists(__DIR__ . '/' . $file);
        if ($exists) $anyFound = true;
    ?>
    <div class="item">
        <span class="badge badge-del"><?= $exists ? 'PRÉSENT' : 'ABSENT' ?></span>
        <div class="item-label">
            <code><?= htmlspecialchars($file) ?></code>
            <div class="item-sub"><?= htmlspecialchars($label) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$anyFound): ?>
    <div class="item"><span class="badge badge-none">OK</span> <span class="item-sub">Aucun fichier généré trouvé.</span></div>
    <?php endif; ?>

    <!-- Sauvegardes htaccess -->
    <?php if (!empty($htaccessBackups)): ?>
    <h3>Sauvegardes .htaccess trouvées</h3>
    <?php foreach ($htaccessBackups as $b): ?>
    <div class="item">
        <span class="badge badge-del">PRÉSENT</span>
        <div class="item-label"><code><?= htmlspecialchars(basename($b)) ?></code></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- .htaccess -->
    <h3>Fichier .htaccess</h3>
    <?php if ($htaccessExists && $htaccessModified): ?>
        <?php if (!empty($htaccessBackups)): ?>
        <div class="item">
            <span class="badge badge-ht">RESTAURER</span>
            <div class="item-label">
                Restauration depuis <code><?= htmlspecialchars(basename(end($htaccessBackups))) ?></code>
                <div class="item-sub">Sauvegarde trouvée — restauration automatique</div>
            </div>
        </div>
        <?php else: ?>
        <div class="item">
            <span class="badge badge-ht">NETTOYER</span>
            <div class="item-label">
                Suppression des sections secu-web dans <code>.htaccess</code>
                <div class="item-sub">Aucune sauvegarde disponible — nettoyage manuel des blocs</div>
            </div>
        </div>
        <?php endif; ?>
    <?php elseif ($htaccessExists): ?>
    <div class="item"><span class="badge badge-none">OK</span> <span class="item-sub">.htaccess non modifié par secu-web — inchangé</span></div>
    <?php else: ?>
    <div class="item"><span class="badge badge-none">ABSENT</span> <span class="item-sub">Pas de .htaccess</span></div>
    <?php endif; ?>

    <!-- index.php -->
    <h3>Fichier index</h3>
    <?php if ($indexModified): ?>
    <div class="item">
        <span class="badge badge-idx">NETTOYER</span>
        <div class="item-label">
            Retrait de <code>include 'debut.php'</code> dans <code><?= htmlspecialchars($indexFile) ?></code>
        </div>
    </div>
    <?php elseif ($indexFile): ?>
    <div class="item"><span class="badge badge-none">OK</span> <span class="item-sub"><?= htmlspecialchars($indexFile) ?> non modifié — inchangé</span></div>
    <?php else: ?>
    <div class="item"><span class="badge badge-none">ABSENT</span> <span class="item-sub">Pas de fichier index</span></div>
    <?php endif; ?>

    <hr class="separator">

    <!-- Option blacklist -->
    <h3>Option</h3>
    <form method="post">
        <label class="option">
            <input type="checkbox" name="keep_blacklist" value="1" checked>
            <div>
                <div class="option-label">Conserver <code>blacklist.txt</code></div>
                <div class="option-sub">Votre liste noire accumulée sera gardée pour la nouvelle installation</div>
            </div>
        </label>

        <?php if ($htaccessModified && empty($htaccessBackups)): ?>
        <div class="alert alert-warn" style="margin-top:.75rem;">
            <span>⚠</span>
            <div>Aucune sauvegarde .htaccess trouvée. Le nettoyage sera appliqué directement sur le fichier existant. Faites une copie manuelle si vous avez des règles personnalisées importantes.</div>
        </div>
        <?php endif; ?>

        <input type="hidden" name="confirm" value="1">
        <div class="btn-row">
            <button type="submit" class="btn btn-danger">🧹 Lancer le nettoyage</button>
        </div>
    </form>
    <p class="note">Ce fichier se supprimera automatiquement après le nettoyage.</p>

<?php endif; ?>

</div>
</body>
</html>
