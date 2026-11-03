<?php

return [
    'producer_name' => env('BILLING_PRODUCER_NAME'),
    'producer_nif' => env('BILLING_PRODUCER_NIF'),
    'system_name' => env('BILLING_SYSTEM_NAME', 'Gestion de obras'),
    'system_id' => env('BILLING_SYSTEM_ID', '01'),
    'version' => env('BILLING_SYSTEM_VERSION', '0.1.0'),
    'installation' => env('BILLING_INSTALLATION_ID'),
    'certificate_path' => env('VERIFACTU_CERTIFICATE_PATH'),
    'certificate_password' => env('VERIFACTU_CERTIFICATE_PASSWORD'),
    'private_key_path' => env('VERIFACTU_PRIVATE_KEY_PATH'),
    'private_key_password' => env('VERIFACTU_PRIVATE_KEY_PASSWORD'),
    'send_enabled' => (bool) env('VERIFACTU_SEND_ENABLED', false),
    'responsible_declaration_path' => env('BILLING_RESPONSIBLE_DECLARATION_PATH'),
];
