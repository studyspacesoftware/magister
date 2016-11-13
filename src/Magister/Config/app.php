<?php

return [

    /*
     |--------------------------------------------------------------------------
     | Encryption Key
     |--------------------------------------------------------------------------
     */
    'key' => 'f320ee9033098c804cd31b9b',

    /*
     |--------------------------------------------------------------------------
     | Auto-loaded Service Providers
     |--------------------------------------------------------------------------
     */
    'providers' => [

        'Magister\Services\Exception\ExceptionServiceProvider',
        'Magister\Services\Filesystem\FilesystemServiceProvider',
        'Magister\Services\Cookie\CookieServiceProvider',
        'Magister\Services\Encryption\EncryptionServiceProvider',
        'Magister\Services\Http\HttpServiceProvider',
        'Magister\Services\Database\DatabaseServiceProvider',
        'Magister\Services\Events\EventServiceProvider',
        'Magister\Services\Auth\AuthServiceProvider',
        'Magister\Services\Translation\TranslatorServiceProvider',

    ],

    /*
     |--------------------------------------------------------------------------
     | Class Aliases
     |--------------------------------------------------------------------------
     */
    'aliases' => [

        'App'       => 'Magister\Services\Support\Surrogates\App',
        'Auth'      => 'Magister\Services\Support\Surrogates\Auth',
        'Config'    => 'Magister\Services\Support\Surrogates\Config',
        'Cookie'    => 'Magister\Services\Support\Surrogates\Cookie',
        'Crypt'     => 'Magister\Services\Support\Surrogates\Crypt',
        'Event'     => 'Magister\Services\Support\Surrogates\Event',
        'File'      => 'Magister\Services\Support\Surrogates\File',
        'Http'      => 'Magister\Services\Support\Surrogates\Http',

    ],

];
