<?php
// Konfigurasi Supabase
$supabaseUrl = 'https://voetxxrzcfmipxijcxzz.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZvZXR4eHJ6Y2ZtaXB4aWpjeHp6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjAxMDAyOTQsImV4cCI6MjA3NTY3NjI5NH0.wEmsHiQxVyZTrYtPnAhjPdWEC4Z0A7K8M_noNI0h4Q0';

// Storage Configuration
$supabaseStorageBucket = 'uploads'; // Nama bucket di Supabase Storage
$supabaseStorageUrl = $supabaseUrl . '/storage/v1';

// Helper function untuk upload file
function uploadToSupabase($file, $supabaseStorageUrl, $supabaseKey, $bucket)
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $timestamp = time();
    $random = substr(md5(uniqid(rand(), true)), 0, 8);
    $filename = "{$random}_{$timestamp}.{$extension}";

    $filePath = $bucket . '/' . $filename;
    $uploadUrl = $supabaseStorageUrl . '/object/' . $filePath;

    $fileContent = file_get_contents($file['tmp_name']);
    $mimeType = mime_content_type($file['tmp_name']);

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $supabaseKey,
        'Content-Type: ' . $mimeType,
        'x-upsert: false'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        // PERBAIKAN: Return URL publik yang benar
        return $supabaseStorageUrl . '/object/public/' . $filePath;
    }

    return null;
}

// Helper function untuk delete file
function deleteFromSupabase($filename)
{
    global $supabaseStorageUrl, $supabaseKey, $supabaseStorageBucket;

    $filePath = $supabaseStorageBucket . '/' . $filename;
    $deleteUrl = $supabaseStorageUrl . '/object/' . $filePath;

    $ch = curl_init($deleteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $supabaseKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}
