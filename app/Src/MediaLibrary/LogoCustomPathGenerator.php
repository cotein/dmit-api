<?php

namespace App\Src\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class LogoCustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        // Usa una propiedad personalizada del modelo para generar la ruta
        return 'companies/' . $media->model->afip_number . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media) . 'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media) . 'responsive-images/';
    }
}
