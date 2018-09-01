<?php
return [
    // Mail Driver
    'driver' => env('MAIL_DRIVER', 'SMTP'),

    //SMTP Host Address
    'host' => env('MAIL_HOST', 'smtp.126.com'),

    // SMTP Host Port
    'port' => env('MAIL_PORT', 25),

    // Global "From" Address
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'billsion@126.com'),
        'name' => env('MAIL_FROM_NAME', 'billsion@126.com'),
    ],

    // E-Mail Encryption Protocol
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    // SMTP Server Username
    'username' => env('MAIL_USERNAME', 'billsion@126.com'),

    'password' => env('MAIL_PASSWORD', '1a2s3d4f5g6h'),

    // Sendmail System Path
    'sendmail' => '/usr/sbin/sendmail -bs',

    // Markdown Mail Settings
    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
];
