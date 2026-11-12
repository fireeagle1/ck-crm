<?php

return [
    // Maximum cPanel username length. 8 keeps database-user prefixes within MySQL limits.
    'username_max_length' => (int) env('HOSTING_USERNAME_MAX_LENGTH', 8),

    // Usernames that must never be generated.
    'username_reserved' => [
        'root', 'admin', 'administrator', 'mysql', 'test', 'cpanel', 'whm', 'nobody',
        'postgres', 'mail', 'ftp', 'www', 'web', 'wheel', 'daemon', 'bin', 'sys',
        'system', 'user', 'users', 'default', 'localhost', 'support', 'webmaster',
        'postmaster', 'hostmaster', 'dovecot', 'apache', 'nginx', 'named', 'cpanelhorde',
        'cpses', 'cphulk', 'cpaneleximfilter', 'cpanellogin', 'dirs', 'spamd', 'sshd',
    ],
];
