<?php

namespace Wyckie\EcommercePlatform;

// Use the Intervention Version 3 ImageManager architectures
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        // Initialize the manager using the local GD graphics driver
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Resize a product image to standard catalog thumbnail dimensions
     *
     * @param string $inputPath Path to the raw uploaded image file
     * @param string $outputPath Path where the resized thumbnail should be saved
     * @param int $width Target width in pixels
     * @param int $height Target height in pixels
     */
    public function createThumbnail(string $inputPath, string $outputPath, int $width = 300, int $height = 300): void
    {
        if (!file_exists($inputPath)) {
            throw new \Exception("Source product image file not found: " . $inputPath);
        }

        // Read the image file, scale it, and crop it to fit perfectly
        $image = $this->manager->read($inputPath);
        
        // Cover ensures it crops evenly without stretching your products
        $image->cover($width, $height);
        
        // Save the optimized version to disk
        $image->save($outputPath);
    }
}
