<?php

/**
 * DON'T MODIFY THIS FILE!!! READ "conf/README.md" BEFORE.
 */

// Inbenta Hyperchat configuration
return [
    'chat' => [
        'enabled' => true,
        'version' => '1',
        'appId' => '',
        'secret' => '',
        'roomId' => 1,             // Numeric value, no string (without quotes)
        'lang' => 'en',
        'source' => 3,             // Numeric value, no string (without quotes)
        'guestName' => '',
        'guestContact' => '',
        'regionServer' => 'us',
        'server' => '<server>',    // Your HyperChat server URL (ask your contact person at Inbenta)
        'server_port' => 443,
        'workTimeTableActive' => false, // if set to FALSE then chat is 24/7, if TRUE then we get the working hours from API
    ],
    'triesBeforeEscalation' => 2,
    'negativeRatingsBeforeEscalation' => 0,

    //Add here the number or numbers needed when transfer a call (only for Voice)
    'transfer_options' => [
        'validate_on_transfer' => '', //posible value: 'variable' or empty '' for no validation
        'variables_to_check' => [], //Applies when 'validate_on_transfer' is 'variable', otherwise empty array is correct
        'transfer_numbers' => [
            'default' => '-', //Default transfer number ("-" (hyphen) if no number to transfer)
            //'transfer_phone2' => '',
            //'transfer_phone2' => '',
            //... more transfer phones
        ]
    ]
];
