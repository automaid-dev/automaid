<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the Terms & Conditions PDF by fetching it from S3 server-side
 * and streaming it back — rather than requiring the S3 object itself to
 * be publicly readable.
 *
 * Why: modern S3 buckets commonly ship with "Block Public Access"
 * enabled and/or ACLs disabled ("Bucket owner enforced" object
 * ownership) as AWS's current security defaults. When that's the case,
 * Laravel's `visibility: public` setting on upload gets silently
 * ignored — the object simply can never become public via ACL,
 * regardless of what the application code does, which is exactly what
 * produced the AccessDenied error here.
 *
 * Deliberately not fixed by loosening bucket-wide settings (disabling
 * Block Public Access, or adding a public bucket policy) — this same
 * bucket also stores sensitive rider/merchant verification documents
 * (IC, license, etc.), and a bucket-wide public-access change is a much
 * bigger, riskier change than this one document actually needs. The
 * app's own credentials can already read the file (it uploaded it) —
 * this route just uses that same access server-side and hands the
 * bytes to whoever's asking, without the bucket needing to be public
 * at all.
 */
class PublicDocumentController extends Controller
{
    public function termsConditions(): Response
    {
        $setting = Setting::find(1);

        if (!$setting || !$setting->terms_conditions || !Storage::disk('s3')->exists($setting->terms_conditions)) {
            abort(404, 'Terms & Conditions document not found.');
        }

        $contents = Storage::disk('s3')->get($setting->terms_conditions);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="terms-and-conditions.pdf"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
