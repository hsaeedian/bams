<?php
// Advanced/manual deployment only. The cPanel installer writes the real private file.
// Never put operational database credentials in source or public_html.
return [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'example_bams',
    'username' => 'example_bamsuser',
    'password' => 'FICTITIOUS-REPLACE-PRIVATELY',
];
// Existing optional SQLite deployments can still return:
// ['driver' => 'sqlite', 'database' => '/srv/bams-private/bams.sqlite'];
