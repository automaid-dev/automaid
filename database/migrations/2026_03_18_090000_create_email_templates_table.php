<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Admin-editable email content. Keyed by a fixed set of identifiers
     * the code looks up by name (see EmailTemplate::render()) — this
     * isn't a free-form CRUD table, the app relies on specific keys
     * existing, so rows are seeded here rather than created ad-hoc.
     *
     * `body` supports simple {{name}} style placeholders, substituted
     * per-recipient at send time. It's injected into the existing
     * branded email layout (logo/header/footer) rather than being raw
     * standalone HTML, so admin edits can't break the overall look.
     */
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label'); // human-readable name shown in the admin list
            $table->string('subject');
            $table->text('body');
            $table->text('description')->nullable(); // shown to admin: when this email is sent, which placeholders are available
            $table->timestamps();
        });

        DB::table('email_templates')->insert([
            [
                'key' => 'user_approved',
                'label' => 'Rider/Merchant Approved',
                'subject' => 'Auto Maid: Your Registration Status: Approved',
                'body' => "<p>Dear <strong>{{name}}</strong>,</p>\n\n<p>Congratulations, your application has been approved! Please login to your app now and start taking orders now.</p>\n\n<p>If you have any issues or enquiries, please do not hesitate to contact our customer support via the mobile app. We are happy to assist you.</p>",
                'description' => 'Sent when an admin approves a pending rider or merchant application (Users list, the checkmark action). Available placeholder: {{name}}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'customer_welcome',
                'label' => 'Customer Welcome',
                'subject' => 'Welcome to Auto Maid, "{{name}}"',
                'body' => "<p>Dear <strong>{{name}}</strong>,</p>\n\n<p>Welcome to Auto Maid! Your account is ready — book your first pickup any time from the app.</p>\n\n<p>If you have any questions, our support team is happy to help.</p>",
                'description' => 'Sent right after a new customer verifies their OTP and their account becomes active. Available placeholder: {{name}}',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
