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

        if (empty($errValues)) {
            return false;
        } else {
            return $errValues;
        }
    }

    public static function getObservaciones($res)
    {
        $json = json_encode($res);

        $array = json_decode($json, TRUE);

        $observacionesValues = [];

        foreach ($array as $clave => $valor) {
            if ($clave === "Observaciones") {
                $observacionesValues[] = $array[$clave]['Obs'][0];
            } elseif (is_array($valor)) {
                $nestedobservacionesValues = self::getObservaciones($valor);

                if (!empty($nestedobservacionesValues)) {
                    $observacionesValues = array_merge($observacionesValues, $nestedobservacionesValues);
                }
            }
        }

        if (empty($observacionesValues)) {
            return false;
        } else {
            return $observacionesValues;
        }
    }
}
