<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Emails con rol root
    |--------------------------------------------------------------------------
    |
    | Al registrarse, un usuario con uno de estos emails recibe USER_ROOT.
    | Se configura por env (separados por coma) para no tener que tocar el
    | controlador. El default mantiene el comportamiento histórico.
    |
    */

    'root_emails' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ROOT_EMAILS',
            'diego.barrueta@gmail.com,marcelo.j.callo@gmail.com,marcelo.callao@piamondsa.com.ar'
        ))
    ))),

];
