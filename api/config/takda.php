<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Frontend URL
    |---------------------------------------------------------------------------
    |
    | Where the customer-facing web app is served. Used to build QR deep links
    | and returned to the client so the SPA can talk to this API cross-origin.
    |
    */

    'frontend_url' => env('TAKDA_FRONTEND_URL', 'http://localhost:5173'),

    /*
    |---------------------------------------------------------------------------
    | Turn Alerts
    |---------------------------------------------------------------------------
    |
    | How many places from the front a customer is when we notify them their
    | turn is approaching.
    |
    */

    'turn_approaching_threshold' => env('TAKDA_TURN_THRESHOLD', 3),

];
