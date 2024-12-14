<?php

namespace App\Src\Traits;

trait ImageBase64Trait
{
    /**
     * Convierte una imagen a una cadena Base64.
     *
     * @param \Illuminate\Http\UploadedFile $image
     * @return string
     */
    public function convertImageToBase64($image)
    {
        $imagePath = $image->getPath();
        $imageContent = file_get_contents($imagePath);
        $base64Image = base64_encode($imageContent);
        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $logo_base64 = 'data:image/' . $extension . ';base64,' . $base64Image;

        return $logo_base64;
    }
}
