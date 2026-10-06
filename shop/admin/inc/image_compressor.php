<?php
/**
 * Universal Image Compression & Optimization Engine
 * Handles high-resolution smartphone photos, EXIF rotation, WebP conversion, and size reduction.
 */

if (!defined('IMAGE_COMPRESSOR_LOADED')) {
    define('IMAGE_COMPRESSOR_LOADED', true);
}

class ImageCompressorEngine {

    /**
     * Compress and optimize an image file.
     *
     * @param string $sourcePath Path to source file
     * @param string $destinationPath Path to save compressed file (leave empty to overwrite source)
     * @param int $maxDimension Max width or height (default: 1600)
     * @param int $quality Compression quality (1-100, default: 82)
     * @param bool $convertToWebp Whether to output WebP if supported
     * @return array [success => bool, original_size => int, compressed_size => int, saved_pct => int, path => string, width => int, height => int, mime => string]
     */
    public static function compress($sourcePath, $destinationPath = null, $maxDimension = 1600, $quality = 82, $convertToWebp = true) {
        if (!file_exists($sourcePath)) {
            return ['success' => false, 'error' => 'Source file does not exist.'];
        }

        $origSize = filesize($sourcePath);
        if ($origSize === 0) {
            return ['success' => false, 'error' => 'Source file is empty.'];
        }

        if (!extension_loaded('gd')) {
            // GD not available, fallback to copy
            if ($destinationPath && $destinationPath !== $sourcePath) {
                copy($sourcePath, $destinationPath);
            }
            return [
                'success' => true,
                'original_size' => $origSize,
                'compressed_size' => $origSize,
                'saved_pct' => 0,
                'path' => $destinationPath ?: $sourcePath,
                'note' => 'GD extension not available, kept original.'
            ];
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return ['success' => false, 'error' => 'Invalid image format.'];
        }

        $mime = $imageInfo['mime'];
        $origWidth = $imageInfo[0];
        $origHeight = $imageInfo[1];

        // Create GD resource based on type
        $srcImage = null;
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $srcImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $srcImage = @imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $srcImage = @imagecreatefromwebp($sourcePath);
                }
                break;
            case 'image/gif':
                $srcImage = @imagecreatefromgif($sourcePath);
                break;
        }

        if (!$srcImage) {
            if ($destinationPath && $destinationPath !== $sourcePath) {
                copy($sourcePath, $destinationPath);
            }
            return [
                'success' => true,
                'original_size' => $origSize,
                'compressed_size' => $origSize,
                'saved_pct' => 0,
                'path' => $destinationPath ?: $sourcePath
            ];
        }

        // Handle EXIF orientation for JPEGs
        if (($mime === 'image/jpeg' || $mime === 'image/pjpeg') && function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($sourcePath);
                if ($exif && !empty($exif['Orientation'])) {
                    switch ($exif['Orientation']) {
                        case 3:
                            $srcImage = imagerotate($srcImage, 180, 0);
                            break;
                        case 6:
                            $srcImage = imagerotate($srcImage, -90, 0);
                            $tmp = $origWidth;
                            $origWidth = $origHeight;
                            $origHeight = $tmp;
                            break;
                        case 8:
                            $srcImage = imagerotate($srcImage, 90, 0);
                            $tmp = $origWidth;
                            $origWidth = $origHeight;
                            $origHeight = $tmp;
                            break;
                    }
                }
            } catch (Throwable $e) {}
        }

        // Calculate aspect-ratio preserved dimensions
        $targetWidth = $origWidth;
        $targetHeight = $origHeight;

        if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
            if ($origWidth >= $origHeight) {
                $targetWidth = $maxDimension;
                $targetHeight = (int)round(($origHeight / $origWidth) * $maxDimension);
            } else {
                $targetHeight = $maxDimension;
                $targetWidth = (int)round(($origWidth / $origHeight) * $maxDimension);
            }
        }

        // Create true-color canvas
        $dstImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Retain alpha transparency for PNG & WebP
        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        imagefilledrectangle($dstImage, 0, 0, $targetWidth, $targetHeight, $transparent);

        // High quality bicubic resampling
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);

        // Determine destination file path & format
        $supportsWebp = function_exists('imagewebp');
        $outputWebp = $convertToWebp && $supportsWebp;

        if (!$destinationPath) {
            if ($outputWebp && !preg_match('/\.webp$/i', $sourcePath)) {
                $destinationPath = preg_replace('/\.[^.]+$/', '.webp', $sourcePath);
            } else {
                $destinationPath = $sourcePath;
            }
        } else {
            if ($outputWebp && !preg_match('/\.webp$/i', $destinationPath)) {
                $destinationPath = preg_replace('/\.[^.]+$/', '.webp', $destinationPath);
            }
        }

        $destDir = dirname($destinationPath);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        $savedOk = false;
        if ($outputWebp) {
            $savedOk = @imagewebp($dstImage, $destinationPath, $quality);
        } elseif ($mime === 'image/png') {
            // PNG compression level 0-9
            $pngQuality = (int)round(9 - (($quality / 100) * 9));
            $savedOk = @imagepng($dstImage, $destinationPath, $pngQuality);
        } else {
            $savedOk = @imagejpeg($dstImage, $destinationPath, $quality);
        }

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if (!$savedOk || !file_exists($destinationPath)) {
            return ['success' => false, 'error' => 'Failed to save compressed image.'];
        }

        $compSize = filesize($destinationPath);
        $savedPct = $origSize > 0 ? max(0, round((($origSize - $compSize) / $origSize) * 100)) : 0;

        return [
            'success' => true,
            'original_size' => $origSize,
            'compressed_size' => $compSize,
            'saved_pct' => $savedPct,
            'path' => $destinationPath,
            'filename' => basename($destinationPath),
            'width' => $targetWidth,
            'height' => $targetHeight,
            'format' => $outputWebp ? 'webp' : ($mime === 'image/png' ? 'png' : 'jpeg')
        ];
    }
}
