<?php

namespace App\Src\Helpers;

class Afip
{

    public static function getErrValues($res)
    {
        $json = json_encode($res);

        $array = json_decode($json, TRUE);

        $errValues = [];

        foreach ($array as $key => $value) {
            if ($key === 'Err') {
                foreach ($value as $err) {
                    $errValues[] = $err;
                }
            } elseif (is_array($value)) {
                $nestedErrValues = self::getErrValues($value);

                if (!empty($nestedErrValues)) {
                    $errValues = array_merge($errValues, $nestedErrValues);
                }
            }
        }

        foreach ($array as $clave => $valor) {
            if ($clave === "Observaciones") {
                $errValues[] = $array[$clave]['Obs'][0];
            } elseif (is_array($valor)) {
                $nestedErrValues = self::getErrValues($valor);
            }
        }

        if (empty($errValues)) {
            return false;
        } else {
            return $errValues;
        }
    }
}
