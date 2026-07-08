<?php
// Copyright (c) 2026 Andreas Vetter
// Einmalig lokal ausführen: erzeugt ein Ed25519-Schlüsselpaar für Update-Signaturen.
//   php tools/gen_update_keys.php
// Danach:
//   - PUBLIC KEY  -> config/update_pubkey.php eintragen (kommt ins Repo, ist öffentlich)
//   - SECRET KEY  -> config/update_privkey.secret speichern (NUR lokal, NIE ins Repo/Server!)
// ACHTUNG: Neues Schlüsselpaar macht alle bisher signierten Pakete ungültig.

if (!function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "libsodium nicht verfügbar.\n"); exit(1);
}
$kp  = sodium_crypto_sign_keypair();
$pub = base64_encode(sodium_crypto_sign_publickey($kp));
$sec = base64_encode(sodium_crypto_sign_secretkey($kp));
echo "PUBLIC  (config/update_pubkey.php):\n$pub\n\n";
echo "SECRET  (config/update_privkey.secret — geheim halten!):\n$sec\n";
