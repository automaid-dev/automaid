<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\OrderStepPhoto;
use App\Models\Booking;
use App\Models\Banner;
use App\Models\Ticket;
use App\Models\Announcement;
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

    /**
     * Serves a single order handoff photo (rider pickup, merchant wash
     * start/complete, rider delivery, etc.) the same way — see the
     * class-level doc comment for why. Looked up by hashslug rather
     * than numeric id, matching the unguessable-URL convention already
     * used throughout this app for anything shared outside an
     * authenticated API call.
     */
    public function stepPhoto(string $hashslug): Response
    {
        $photo = OrderStepPhoto::where('hashslug', $hashslug)->first();

        if (!$photo || !$photo->image_path || !Storage::disk('s3')->exists($photo->image_path)) {
            abort(404, 'Photo not found.');
        }

        $contents = Storage::disk('s3')->get($photo->image_path);
        $mime = Storage::disk('s3')->mimeType($photo->image_path) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Serves the customer's pickup-handoff photo (e.g. laundry left at
     * a hotel lobby) — same S3 Block Public Access reasoning as
     * stepPhoto() above.
     */
    public function pickupPhoto(string $hashslug): Response
    {
        $booking = Booking::where('hashslug', $hashslug)->first();

        if (!$booking || !$booking->pickup_photo_path || !Storage::disk('s3')->exists($booking->pickup_photo_path)) {
            abort(404, 'Photo not found.');
        }

        $contents = Storage::disk('s3')->get($booking->pickup_photo_path);
        $mime = Storage::disk('s3')->mimeType($booking->pickup_photo_path) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Serves the customer's landmark picture (an older field, set
     * alongside a text landmark description to help the rider find
     * the address) — same S3 Block Public Access reasoning as
     * pickupPhoto() above. This field's own accessor was returning a
     * raw, broken S3 URL until now.
     */
    public function landmarkPicture(string $hashslug): Response
    {
        $booking = Booking::where('hashslug', $hashslug)->first();

        if (!$booking || !$booking->landmark_picture || !Storage::disk('s3')->exists($booking->landmark_picture)) {
            abort(404, 'Photo not found.');
        }

        $contents = Storage::disk('s3')->get($booking->landmark_picture);
        $mime = Storage::disk('s3')->mimeType($booking->landmark_picture) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Serves a dashboard promotional banner image — same S3 Block
     * Public Access reasoning as stepPhoto()/pickupPhoto() above.
     */
    public function bannerImage(string $hashslug): Response
    {
        $banner = Banner::where('hashslug', $hashslug)->first();

        if (!$banner || !$banner->image_path || !Storage::disk('s3')->exists($banner->image_path)) {
            abort(404, 'Image not found.');
        }

        $contents = Storage::disk('s3')->get($banner->image_path);
        $mime = Storage::disk('s3')->mimeType($banner->image_path) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            // Banners change far less often than step/pickup photos and
            // are shown to every user on every dashboard load — a
            // longer cache window meaningfully cuts repeat S3 fetches.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Serves a support ticket's attached photo — same S3 Block Public
     * Access reasoning as the other proxy methods above.
     */
    public function ticketImage(string $hashslug): Response
    {
        $ticket = Ticket::where('hashslug', $hashslug)->first();

        if (!$ticket || !$ticket->image || !Storage::disk('s3')->exists($ticket->image)) {
            abort(404, 'Image not found.');
        }

        $contents = Storage::disk('s3')->get($ticket->image);
        $mime = Storage::disk('s3')->mimeType($ticket->image) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Serves an announcement's image — same S3 Block Public Access
     * reasoning as the other proxy methods above.
     */
    public function announcementImage(string $hashslug): Response
    {
        $announcement = Announcement::where('hashslug', $hashslug)->first();

        if (!$announcement || !$announcement->image_url || !Storage::disk('s3')->exists($announcement->image_url)) {
            abort(404, 'Image not found.');
        }

        $contents = Storage::disk('s3')->get($announcement->image_url);
        $mime = Storage::disk('s3')->mimeType($announcement->image_url) ?? 'image/jpeg';

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
