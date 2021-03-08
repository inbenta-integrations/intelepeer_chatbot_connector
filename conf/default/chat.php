<?php

/**
 * DON'T MODIFY THIS FILE!!! READ "conf/README.md" BEFORE.
 */

// Inbenta Hyperchat configuration
return [
    'chat' => [
        'enabled' => true
    ],
    'triesBeforeEscalation' => 2,
    'negativeRatingsBeforeEscalation' => 0,

    //Add here the number or numbers needed when transfer a call
    'transfer_options' => [
        'validate_on_transfer' => '', //posible values: '' (empty string for no transfer), 'directCall' or 'variable'
        'variable_to_check' => '', //Applies when 'validate_on_transfer' is 'variable', otherwise empty array is correct
        'transfer_numbers' => [
            'default' => '-', //Default transfer number ("-" (dash) if no number to transfer)
            //'transfer_phone2' => '',
            //'transfer_phone2' => '',
            //... more transfer phones
        ]
    ]
];
