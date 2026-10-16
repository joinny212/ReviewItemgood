<?php

function productImageUrl(?string $image, string $localPrefix = ''): string
{
    if (!$image) {
        return '';
    }

    if (preg_match('#^https?://#i', $image)) {
        return $image;
    }

    $filename = basename(str_replace('\\', '/', $image));
    return $localPrefix . rawurlencode($filename);
}

function reviewImageUrl(?string $reviewImage, ?string $productImage, string $localPrefix, string $localDirectory): string
{
    foreach ([$reviewImage, $productImage] as $image) {
        if (!$image) {
            continue;
        }
        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        $filename = basename(str_replace('\\', '/', $image));
        if (is_file(rtrim($localDirectory, '/\\') . DIRECTORY_SEPARATOR . $filename)) {
            return $localPrefix . rawurlencode($filename);
        }
    }

    return '';
}

function normalizeProductImages(array $images): array
{
    $normalized = [];
    foreach ($images as $image) {
        if (!is_string($image) || trim($image) === '') {
            continue;
        }

        $image = trim($image);
        if (!in_array($image, $normalized, true)) {
            $normalized[] = $image;
        }
    }

    usort($normalized, static function (string $first, string $second): int {
        $firstIsRemote = preg_match('#^https?://#i', $first) === 1;
        $secondIsRemote = preg_match('#^https?://#i', $second) === 1;
        return $secondIsRemote <=> $firstIsRemote;
    });

    return $normalized;
}

function uploadImageToCloudinary(string $filePath, string $mimeType, string $folder): string
{
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');

    if (!$cloudName || !$apiKey || !$apiSecret) {
        throw new RuntimeException('Chưa cấu hình CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY và CLOUDINARY_API_SECRET trên Render.');
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Máy chủ chưa bật PHP cURL; hãy deploy lại Dockerfile mới nhất.');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $cloudName)) {
        throw new RuntimeException('CLOUDINARY_CLOUD_NAME không hợp lệ.');
    }

    $timestamp = time();
    $signature = sha1('folder=' . $folder . '&timestamp=' . $timestamp . $apiSecret);
    $postFields = [
        'file' => new CURLFile($filePath, $mimeType, basename($filePath)),
        'api_key' => $apiKey,
        'folder' => $folder,
        'timestamp' => $timestamp,
        'signature' => $signature,
    ];

    $curl = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload");
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 90,
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($responseBody === false) {
        error_log('Cloudinary upload request failed: ' . $curlError);
        throw new RuntimeException('Không thể kết nối Cloudinary để lưu ảnh. Vui lòng thử lại.');
    }

    try {
        $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        error_log('Cloudinary returned invalid JSON, HTTP status ' . $httpStatus);
        throw new RuntimeException('Cloudinary trả về phản hồi không hợp lệ.');
    }

    if ($httpStatus < 200 || $httpStatus >= 300 || empty($response['secure_url'])) {
        $message = $response['error']['message'] ?? 'Không rõ nguyên nhân';
        error_log('Cloudinary upload failed, HTTP status ' . $httpStatus . ': ' . $message);
        throw new RuntimeException('Cloudinary không lưu được ảnh: ' . $message);
    }

    return $response['secure_url'];
}

function uploadProductImageToCloudinary(string $filePath, string $mimeType): string
{
    return uploadImageToCloudinary($filePath, $mimeType, 'products');
}
