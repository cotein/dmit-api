<?php

namespace App\Src\Helpers;

class ZeroLeft
{

    /**
     * Pads a string on the left with zeros.
     *
     * @param string $data The string to pad.
     * @param int $lenght The length of the padded string.
     * @return string The padded string.
     */
    public static function print($data, $lenght)
    {
        return str_pad($data, $lenght, "0", STR_PAD_LEFT);
    }
}
