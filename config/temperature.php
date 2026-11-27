<?php

return [
    /*
    | Whether warehouse users must record the pending chamber temperatures before using the system. It is switched
    | off in the test suite (phpunit.xml) and turned on by the tests that cover it.
    */
    'enforce' => env('TEMPERATURE_GATE', true),

    /*
    | From this time (24 h, America/Mexico_City) the afternoon reading is reminded, never required, and a first
    | reading of the day counts as the afternoon one.
    */
    'afternoon_starts' => '14:00',

    /* The afternoon reading can be recorded from this time until the end of the same day. */
    'afternoon_opens' => '12:00',

    /*
    | Levels in °C, the same for every chamber. Colder than normal_from: too cold. From normal_from to normal_to
    | (both included): normal. Warmer, up to warning_to (included): yellow. Warmer than warning_to: red.
    */
    'levels' => [
        'normal_from' => -18,
        'normal_to' => -10,
        'warning_to' => -7,
    ],

    /* Values accepted when capturing, in °C. */
    'lowest' => -50,
    'highest' => 50,
];
