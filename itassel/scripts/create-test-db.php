<?php

/**
 * Crée uniquement la base itassel_test. N'ouvre jamais la base de développement.
 */
$racine = dirname(__DIR__);
$fichier = $racine.DIRECTORY_SEPARATOR.'.env.testing';

if (! is_file($fichier)) {
    fwrite(STDERR, "Fichier .env.testing absent. Copiez .env.testing.example et renseignez DB_USERNAME et DB_PASSWORD.\n");
    exit(1);
}

$contenu = file_get_contents($fichier);
$lire = function (string $cle) use ($contenu): string {
    if (! preg_match('/^'.preg_quote($cle, '/').'=(.*)$/m', $contenu, $correspondance)) {
        return '';
    }

    return trim($correspondance[1], " \t\r\n\"'");
};

$base = 'itassel_test';
$host = $lire('DB_HOST') ?: '127.0.0.1';
$port = $lire('DB_PORT') ?: '3306';
$utilisateur = $lire('DB_USERNAME');
$motDePasse = $lire('DB_PASSWORD');

if ($utilisateur === '') {
    fwrite(STDERR, "DB_USERNAME est vide dans .env.testing.\n");
    exit(1);
}

if (! str_ends_with($base, '_test')) {
    fwrite(STDERR, "Refus : la base de test doit finir par _test.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        $utilisateur,
        $motDePasse,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    echo "Base {$base} prête.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Impossible de créer la base {$base} : ".$e->getMessage()."\n");
    exit(1);
}
