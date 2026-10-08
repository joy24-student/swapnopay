<?php
/**
 * Supabase Storage Integration Helper
 * Stores and manages storefront assets and product images directly in Supabase Storage.
 */

if (!defined('SUPABASE_STORAGE_URL')) {
    $storageUrl = defined('SUPABASE_URL') && SUPABASE_URL 
        ? SUPABASE_URL 
        : (getenv('SUPABASE_STORAGE_URL') ?: getenv('SUPABASE_URL') ?: '');
    define('SUPABASE_STORAGE_URL', rtrim((string)$storageUrl, '/'));
}
if (!defined('SUPABASE_STORAGE_ANON_KEY')) {
    $storageKey = defined('SUPABASE_ANON_KEY') && SUPABASE_ANON_KEY 
        ? SUPABASE_ANON_KEY 
        : (getenv('SUPABASE_STORAGE_ANON_KEY') ?: getenv('SUPABASE_ANON_KEY') ?: '');
    define('SUPABASE_STORAGE_ANON_KEY', (string)$storageKey);
}
if (!defined('SUPABASE_STOREFRONT_BUCKET')) {
    define('SUPABASE_STOREFRONT_BUCKET', 'storefront');
}

/**
 * Upload a file directly to Supabase Storage bucket.
 * 
 * @param string $localFilePath Path to temporary or local file
 * @param string $destinationFilename Filename (e.g. 'hero_banner.jpg')
 * @param string $folder Subfolder within bucket, defaults to 'assets'
 * @param string $bucket Bucket name, defaults to 'storefront'
 * @return string|false Public URL on success, false on failure
 */
function uploadFileToSupabase($localFilePath, $destinationFilename, $folder = 'assets', $bucket = SUPABASE_STOREFRONT_BUCKET) {
    if (!file_exists($localFilePath) || empty(SUPABASE_STORAGE_URL) || empty(SUPABASE_STORAGE_ANON_KEY)) {
        return false;
    }
    
    $cleanFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($destinationFilename));
    $remotePath = trim($folder, '/') . '/' . $cleanFilename;
    $uploadUrl = SUPABASE_STORAGE_URL . '/storage/v1/object/' . $bucket . '/' . $remotePath;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $localFilePath) ?: 'application/octet-stream';
    finfo_close($finfo);

    $fileData = file_get_contents($localFilePath);
    if ($fileData === false) {
        return false;
    }

    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileData,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_STORAGE_ANON_KEY,
            'Authorization: Bearer ' . SUPABASE_STORAGE_ANON_KEY,
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

    error_log("Supabase upload failed ($httpCode): $response");
    return false;
}

/**
 * Helper to get clean image URL (supports Supabase public URLs and legacy filenames).
 */
function get_media_url($photoPath, $default = 'assets/images/no-image.png') {
    if (empty($photoPath)) {
        return (defined('BASE_URL') ? BASE_URL : '') . $default;
    }
    if (str_starts_with($photoPath, 'http://') || str_starts_with($photoPath, 'https://')) {
        return $photoPath;
    }
    return (defined('BASE_URL') ? BASE_URL : '') . 'assets/uploads/' . ltrim($photoPath, '/');
}

