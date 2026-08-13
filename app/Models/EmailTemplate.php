<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $guarded = ['id'];
    protected $table = 'email_templates';

    const USER_APPROVED = 'user_approved';
    const CUSTOMER_WELCOME = 'customer_welcome';

    /**
     * Renders a template by key with the given placeholder values,
     * substituting {{name}}-style tokens in both subject and body.
     * Falls back to a safe generic message if the key doesn't exist in
     * the database for any reason (e.g. migration not yet run) — so a
     * missing template never blocks the email from sending outright,
     * it just sends something more generic.
     *
     * @param  string $key
     * @param  array  $replacements  e.g. ['name' => 'John']
     * @return array{subject: string, body: string}
     */
    public static function render(string $key, array $replacements = []): array
    {
        $template = self::where('key', $key)->first();

        $subject = $template->subject ?? 'Auto Maid: Notification';
        $body = $template->body ?? '<p>Dear {{name}},</p><p>You have a new update from Auto Maid.</p>';

        foreach ($replacements as $token => $value) {
            $subject = str_replace('{{' . $token . '}}', $value ?? '', $subject);
            $body = str_replace('{{' . $token . '}}', $value ?? '', $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }
}
