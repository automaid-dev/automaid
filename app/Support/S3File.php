<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;

/**
 * Link to a private S3 object, served through Laravel
 * (PublicDocumentController::file) instead of a direct S3 URL.
 *
 * The S3 bucket is private (Block Public Access on, ACLs disabled), so
 * \App\Support\S3File::url() links return AccessDenied. The token is the
 * object path encrypted with APP_KEY, so links cannot be guessed or
 * changed to point at another file.
 */
class S3File
{
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        // Already a full URL (very old rows) — leave it alone.
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return route('documents.file', ['token' => Crypt::encryptString($path)]);
    }

    public static function pathFromToken(string $token): ?string
    {
        try {
            $path = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            return null;
        }
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        return ltrim($path, '/');
    }
}
