<?php
function cleartextToArgon($password) : string
{
    // Set the options for the Argon2i algorithm
    $options = ['memory_cost' => 32768, // 32KB
        'time_cost' => 4, 'threads' => 1,];

    // Generate the Argon2i password hash
    $hash = password_hash($password, PASSWORD_ARGON2I, $options);

    // Replace the default prefix with the Dovecot compatible prefix
    $dovecotHash = str_replace('$argon2i$', '{ARGON2I}$argon2i$', $hash);

    return $dovecotHash;
}