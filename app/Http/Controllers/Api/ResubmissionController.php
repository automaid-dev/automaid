<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramService;
use App\Support\RegistrationDocuments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Rider/merchant re-upload after admin rejected the registration
 * (EditUser "Reject" action → users.rejection_details).
 *
 *   POST /profile/resubmission         what admin asked for
 *   POST /profile/resubmission/submit  new photos (+ expiry dates)
 *
 * After a successful submit the account goes back to "onboarding", so it
 * shows up again for admin to verify and approve.
 */
class ResubmissionController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->status !== User::REJECTED) {
            return response()->json(['status' => false, 'message' => 'There is nothing to resubmit.', 'user_status' => $user->status]);
        }

        $details = $this->details($user);
        $catalogue = RegistrationDocuments::forUser($user);
        $entity = $this->entity($user);

        $documents = [];
        foreach ($details['documents'] as $key) {
            if (!isset($catalogue[$key])) continue;
            [$label, $expiryField] = $catalogue[$key];
            $path = $entity?->$key;
            $documents[] = [
                'key' => $key,
                'label' => $label,
                'current_url' => $path ? \App\Support\S3File::url($path) : null,
                'expiry_field' => $expiryField,
            ];
        }

        $expiryFields = [];
        foreach (array_unique(array_filter(array_column($documents, 'expiry_field'))) as $field) {
            $current = $entity?->$field;
            $expiryFields[] = [
                'key' => $field,
                'label' => RegistrationDocuments::EXPIRY_LABELS[$field] ?? $field,
                'current' => $current ? Carbon::parse($current)->toDateString() : null,
                // JPJ grant may have no printed expiry; licence always does.
                'required' => $field === 'license_expiry',
            ];
        }

        return response()->json([
            'status' => true,
            'data' => [
                'can_resubmit' => (bool) $details['can_resubmit'] && !empty($documents),
                'summary' => $user->rejected_reason,
                'issues' => array_values(array_filter(array_map(fn ($k) => RegistrationDocuments::ISSUES[$k] ?? null, $details['issues']))),
                'note' => $details['note'],
                'documents' => $documents,
                'expiry_fields' => $expiryFields,
                'min_days_valid' => RegistrationDocuments::MIN_DAYS_VALID,
                'rejected_at' => $details['rejected_at'],
                'resubmission_count' => (int) $user->resubmission_count,
            ],
        ]);
    }

    public function submit(Request $request)
    {
        $user = $request->user();
        if ($user->status !== User::REJECTED) {
            return response()->json(['status' => false, 'message' => 'Your application is not waiting for new documents.'], 409);
        }

        $details = $this->details($user);
        $catalogue = RegistrationDocuments::forUser($user);
        $requested = array_values(array_intersect($details['documents'], array_keys($catalogue)));
        if (!$details['can_resubmit'] || !$requested) {
            return response()->json(['status' => false, 'message' => 'This application cannot be resubmitted. Please contact support.'], 409);
        }

        $minDate = now()->addDays(RegistrationDocuments::MIN_DAYS_VALID)->toDateString();
        $rules = [];
        $messages = [];
        foreach ($requested as $key) {
            $rules[$key] = 'required|image|mimes:jpg,jpeg,png|max:10240';
            $messages["{$key}.required"] = 'Please add a new photo of your ' . $catalogue[$key][0] . '.';
        }
        $expiryFields = array_unique(array_filter(array_map(fn ($k) => $catalogue[$k][1], $requested)));
        foreach ($expiryFields as $field) {
            $label = RegistrationDocuments::EXPIRY_LABELS[$field] ?? $field;
            $rules[$field] = ($field === 'license_expiry' ? 'required' : 'nullable') . "|date|after_or_equal:{$minDate}";
            $messages["{$field}.required"] = "Please enter the {$label}.";
            $messages["{$field}.after_or_equal"] = "The {$label} must be at least " . RegistrationDocuments::MIN_DAYS_VALID . ' days from today. Please renew it first, then upload the new one.';
        }

        $validate = Validator::make($request->all(), $rules, $messages);
        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validate->errors()->first(),
                'errors' => $validate->errors(),
            ], 422);
        }

        $entity = $this->entity($user);
        if (!$entity) {
            return response()->json(['status' => false, 'message' => 'Registration details not found. Please contact support.'], 404);
        }

        $folder = $user->hasRole('merchant') ? 'merchants' : 'riders';
        $uploaded = [];
        foreach ($requested as $key) {
            $uploaded[$key] = $this->upload($request->file($key), $folder);
        }

        DB::transaction(function () use ($user, $entity, $uploaded, $expiryFields, $request, $details) {
            foreach ($uploaded as $key => $path) {
                $entity->$key = $path;
            }
            foreach ($expiryFields as $field) {
                if ($request->filled($field)) {
                    $entity->$field = Carbon::parse($request->input($field))->toDateString();
                }
            }
            $entity->save();

            $details['resubmitted_at'] = now()->toDateTimeString();
            $user->rejection_details = $details;
            $user->status = User::ONBOARDING;
            $user->resubmitted_at = now();
            $user->resubmission_count = (int) $user->resubmission_count + 1;
            $user->save();
        });

        $role = $user->hasRole('merchant') ? 'Merchant' : 'Rider';
        Log::info('Registration documents resubmitted', ['user_id' => $user->id, 'documents' => array_keys($uploaded)]);
        try {
            (new TelegramService())->notifyAdmin("📄 <b>{$role} resubmitted documents</b>\n" . e($user->name) . " (user #{$user->id}) uploaded: " . e(implode(', ', array_map(fn ($k) => $catalogue[$k][0], array_keys($uploaded)))) . ".\nPlease re-verify and approve in admin → Users.");
        } catch (\Throwable $e) {
            // optional
        }

        $user->refresh()->load('roles');
        return response()->json([
            'status' => true,
            'message' => 'Thank you! Your documents have been submitted for verification.',
            'user' => $user,
        ]);
    }

    /** Normalised rejection details, also for rejections made before this feature (free text only). */
    protected function details(User $user): array
    {
        $d = is_array($user->rejection_details) ? $user->rejection_details : [];
        $legacy = !$d; // rejected with the old free-text form
        return [
            // Old rejections didn't say which photos — let them redo all of them.
            'documents' => $legacy ? array_keys(RegistrationDocuments::forUser($user)) : array_values($d['documents'] ?? []),
            'issues' => array_values($d['issues'] ?? []),
            'note' => $d['note'] ?? ($legacy ? $user->rejected_reason : null),
            'can_resubmit' => $legacy ? true : (bool) ($d['can_resubmit'] ?? true),
            'rejected_at' => $d['rejected_at'] ?? null,
            'rejected_by' => $d['rejected_by'] ?? null,
        ];
    }

    protected function entity(User $user)
    {
        return $user->hasRole('merchant') ? $user->merchant : $user->rider;
    }

    protected function upload($file, string $folder): string
    {
        $path = '/automaid/images/' . $folder . '/' . uniqid() . date('Ymdhis') . '.' . $file->extension();
        $img = (new ImageManager(new Driver()))->read($file);
        Storage::disk('s3')->put($path, $img->encode()->toFilePointer());
        return $path;
    }
}
