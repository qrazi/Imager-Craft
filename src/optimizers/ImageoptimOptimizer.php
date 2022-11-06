<?php

namespace aelvan\imager\optimizers;

//use Craft;
//use ImageOptim\API;

class ImageoptimOptimizer implements ImagerOptimizeInterface
{

    public static function optimize(string $file, array $settings)
    {
        // Disable ImageOptim as it's not PHP 8.x compatible
//        $api = new API($settings['apiUsername']);
//        $image = $api->imageFromPath($file);
//        $image->quality($settings['quality'] ?? 'medium');
//        $imageData = $image->getBytes();
//        file_put_contents($file, $imageData);
    }
}
