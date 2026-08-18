<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Traditional multipart file upload for admin settings, bypassing
 * Filament's Livewire FileUpload entirely — that goes through a special
 * AJAX endpoint (/livewire/upload-file) which Cloudflare's baseline
 * security heuristics were flagging and rejecting with a 401, even on
 * a free plan with no Zero Trust/security rules explicitly enabled.
 * This mirrors exactly how rider/merchant verification documents
 * already upload successfully — a plain form POST straight to a
 * regular Laravel route, no special AJAX upload mechanism involved.
 */
class SettingUploadController extends Controller
{
    public function uploadTerms(Request $request): RedirectResponse
    {
        $request->validate([
            'terms_conditions' => 'required|file|mimes:pdf|max:10240', // 10MB
        ]);

        $setting = Setting::find(1);

        // Clean up the previous file, if any, so old PDFs don't pile up in S3.
        if ($setting->terms_conditions) {
            Storage::disk('s3')->delete($setting->terms_conditions);
        }

        $file = $request->file('terms_conditions');
        // 'public' visibility is required here — without it, the file
        // uploads successfully but as a private S3 object, which is
        // exactly why the customer app couldn't load it afterward (a
        // 403 on fetch). Matches every other working upload in this
        // codebase (e.g. RiderController's document uploads), which
        // all explicitly set this.
        $path = $file->store('settings', ['disk' => 's3', 'visibility' => 'public']);

        $setting->terms_conditions = $path;
        $setting->save();

        return redirect()
            ->back()
            ->with('success', 'Terms & Conditions PDF uploaded successfully.');
    }
}
