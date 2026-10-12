<?php
/*
 * SMTP + app link settings. Keep this file private and do NOT overwrite it when upgrading.
 * It is used to send password-reset emails.
 */
return [
    // --- SMTP server ---
    'host'       => 'smtp.example.com',
    'port'       => 587,          // 587 for 'tls' (STARTTLS), 465 for 'ssl', 25 for 'none'
    'encryption' => 'tls',        // 'tls' | 'ssl' | 'none'
    'username'   => 'your-smtp-username',   // leave '' if the server needs no login
    'password'   => 'your-smtp-password',

    // --- Sender shown on the email ---
    'from_email' => 'no-reply@example.com',
    'from_name'  => 'Student Manager',

    // --- Public address of this app, used to build the reset link (no trailing slash) ---
    'app_url'    => 'https://ownlms.mussa.to',
];
