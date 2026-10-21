<?php
/**
 * Supabase Storage Integration Helper
 * Stores and manages storefront assets and product images directly in Supabase Storage.
 */

if (!defined('SUPABASE_STORAGE_URL')) {
    $storageUrl = defined('SUPABASE_URL') && SUPABASE_URL 
        ? SUPABASE_URL 
        : (getenv('SUPABASE_STORAGE_URL') ?: getenv('SUPABASE_URL') ?: getenv('ADMIN_SUPABASE_URL') ?: '');
    define('SUPABASE_STORAGE_URL', rtrim((string)$storageUrl, '/'));
}
if (!defined('SUPABASE_STORAGE_SERVICE_KEY')) {
    $storageServiceKey = defined('SUPABASE_SERVICE_KEY') && SUPABASE_SERVICE_KEY
        ? SUPABASE_SERVICE_KEY
        : (getenv('SUPABASE_SERVICE_ROLE_KEY') ?: getenv('ADMIN_SUPABASE_SERVICE_ROLE_KEY') ?: getenv('SUPABASE_SERVICE_KEY') ?: '');

    if (empty($storageServiceKey)) {
        $bEnv = dirname(__DIR__, 3) . '/swapnopay-backend/.env';
        if (is_file($bEnv) && is_readable($bEnv)) {
            $lines = @file($bEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $l) {
                    $tl = trim($l);
                    if (str_starts_with($tl, '#') || !str_contains($tl, '=')) continue;
                    [$k, $v] = explode('=', $tl, 2);
                    $k = trim($k);
                    if ($k === 'ADMIN_SUPABASE_SERVICE_ROLE_KEY' || $k === 'SUPABASE_SERVICE_ROLE_KEY') {
                        $storageServiceKey = trim($v, " \t\n\r\0\x0B\"'");
                        break;
                    }
                }
            }
        }
    }
    define('SUPABASE_STORAGE_SERVICE_KEY', (string)$storageServiceKey);
}
if (!defined('SUPABASE_STORAGE_ANON_KEY')) {
    $storageKey = defined('SUPABASE_ANON_KEY') && SUPABASE_ANON_KEY 
        ? SUPABASE_ANON_KEY 
        : (getenv('SUPABASE_STORAGE_ANON_KEY') ?: getenv('SUPABASE_ANON_KEY') ?: getenv('ADMIN_SUPABASE_ANON_KEY') ?: '');
    define('SUPABASE_STORAGE_ANON_KEY', (string)$storageKey);
}
if (!defined('SUPABASE_STOREFRONT_BUCKET')) {
    define('SUPABASE_STOREFRONT_BUCKET', 'storefront');
}

/**
 * Upload a file directly to Supabase Storage bucket with local filesystem fallback.
 * 
 * @param string $localFilePath Path to temporary or local file
 * @param string $destinationFilename Filename (e.g. 'hero_banner.jpg')
 * @param string $folder Subfolder within bucket, defaults to 'assets'
 * @param string $bucket Bucket name, defaults to 'storefront'
 * @return string|false Public URL or relative path on success, false on failure
 */
function uploadFileToSupabase($localFilePath, $destinationFilename, $folder = 'assets', $bucket = SUPABASE_STOREFRONT_BUCKET) {
    if (!file_exists($localFilePath)) {
        return false;
    }
    
    $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($destinationFilename));
    $remotePath = trim($folder, '/') . '/' . $cleanFilename;
    $authKey = !empty(SUPABASE_STORAGE_SERVICE_KEY) ? SUPABASE_STORAGE_SERVICE_KEY : SUPABASE_STORAGE_ANON_KEY;

    // 1. Attempt Supabase Storage upload if credentials exist
    if (!empty(SUPABASE_STORAGE_URL) && !empty($authKey)) {
        $uploadUrl = SUPABASE_STORAGE_URL . '/storage/v1/object/' . $bucket . '/' . $remotePath;
        
        $mimeType = 'application/octet-stream';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $localFilePath) ?: 'application/octet-stream';
                finfo_close($finfo);
            }
        }

        $fileData = @file_get_contents($localFilePath);
        if ($fileData !== false) {
            $ch = curl_init($uploadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fileData,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $authKey,
                    'Authorization: Bearer ' . $authKey,
                    'Content-Type: ' . $mimeType,
                    'x-upsert: true'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 || $httpCode === 201) {
                return SUPABASE_STORAGE_URL . '/storage/v1/object/public/' . $bucket . '/' . $remotePath;
            }

            error_log("Merchant Supabase upload notice ($httpCode): $response. Trying platform Supabase storage.");
        }
    }

    // 2. Attempt Platform Supabase Storage using verified service key
    $platformUrl = 'https://tldubojeokgyoclxnzkb.supabase.co';
    $platformKey = defined('SUPABASE_STORAGE_SERVICE_KEY') && !empty(SUPABASE_STORAGE_SERVICE_KEY)
        ? SUPABASE_STORAGE_SERVICE_KEY
        : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRsZHVib2plb2tneW9jbHhuemtiIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4Nzc2NzA4MywiZXhwIjoyMTAzMzQzMDgzfQ.ZVC72EuiJ2tV2RdU1HAogLteJqt_sfe98ZEGl-td8uY';

    if (!empty($platformKey) && (!defined('SUPABASE_STORAGE_URL') || SUPABASE_STORAGE_URL !== $platformUrl)) {
        $platformUploadUrl = $platformUrl . '/storage/v1/object/storefront/' . $remotePath;
        $mimeType = $mimeType ?? 'application/octet-stream';
        $fileData = $fileData ?? @file_get_contents($localFilePath);
        if ($fileData !== false) {
            $ch2 = curl_init($platformUploadUrl);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fileData,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $platformKey,
                    'Authorization: Bearer ' . $platformKey,
                    'Content-Type: ' . $mimeType,
                    'x-upsert: true'
                ]
            ]);
            $res2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($code2 === 200 || $code2 === 201) {
                return $platformUrl . '/storage/v1/object/public/storefront/' . $remotePath;
            }
            error_log("Platform Supabase storage upload notice ($code2): $res2. Engaging local storage fallback.");
        }
    }

    // 3. Seamless Local Storage Fallback: Guarantee upload always succeeds
    $targetDir = dirname(__DIR__, 2) . '/assets/uploads/' . trim($folder, '/') . '/';
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    }
    $targetFile = $targetDir . $cleanFilename;
    if (@copy($localFilePath, $targetFile) || @move_uploaded_file($localFilePath, $targetFile)) {
        @chmod($targetFile, 0644);
        return 'assets/uploads/' . trim($folder, '/') . '/' . $cleanFilename;
    }

    return false;
}

/**
 * Helper to get clean image URL (supports Supabase public URLs and legacy filenames).
 */
function get_media_url($photoPath, $default = 'assets/images/no-image.png') {
    if (empty($photoPath)) {
        return (defined('BASE_URL') ? BASE_URL : '') . ltrim($default, '/');
    }
    if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://') || str_starts_with($photoPath, '//')) {
        return $photoPath;
    }
    $clean = ltrim($photoPath, '/');
    if (str_starts_with($clean, 'assets/')) {
        return (defined('BASE_URL') ? BASE_URL : '') . $clean;
    }
    $baseUploadDir = dirname(__DIR__, 2) . '/assets/uploads/';
    if (!file_exists($baseUploadDir . $clean) && file_exists($baseUploadDir . 'product_photos/' . $clean)) {
        return (defined('BASE_URL') ? BASE_URL : '') . 'assets/uploads/product_photos/' . $clean;
    }
    return (defined('BASE_URL') ? BASE_URL : '') . 'assets/uploads/' . $clean;
}

