<?php

namespace App\Console\Commands;

use App\Services\PaymentGateway\RevenueMonsterService;
use Illuminate\Console\Command;

/**
 * php artisan automaid:rm-check
 * Checks the Revenue Monster setup without moving any money: credentials
 * (access token), request signing (private key) and the store ID.
 */
class RevenueMonsterCheck extends Command
{
    protected $signature = 'automaid:rm-check';
    protected $description = 'Check Revenue Monster credentials, signing key and store ID (no payment is made)';

    public function handle(): int
    {
        $c = config('services.revenuemonster');
        $this->line('Environment: ' . ($c['environment'] ?? 'production'));
        foreach (['client_id' => 'RM_CLIENT_ID', 'client_secret' => 'RM_CLIENT_SECRET', 'store_id' => 'RM_STORE_ID'] as $k => $env) {
            $this->line(sprintf('%-18s %s', $env, empty($c[$k]) ? '<error>MISSING</error>' : 'set'));
        }
        $priv = $c['private_key_path'] ? (is_readable($c['private_key_path']) ? 'file ok' : '<error>file not readable: ' . $c['private_key_path'] . '</error>') : ($c['private_key'] ? 'inline' : '<error>MISSING</error>');
        $pub = $c['public_key_path'] ? (is_readable($c['public_key_path']) ? 'file ok' : '<error>file not readable</error>') : ($c['public_key'] ? 'inline' : 'not set (optional)');
        $this->line(sprintf('%-18s %s', 'Private key', $priv));
        $this->line(sprintf('%-18s %s', 'RM public key', $pub));

        $rm = new RevenueMonsterService();
        try {
            $res = $rm->call('get', 'v3/store/' . rawurlencode((string) $c['store_id']));
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        if (!empty($res['error'])) {
            $this->error('Revenue Monster replied: ' . ($res['error']['code'] ?? '') . ' ' . ($res['error']['message'] ?? ''));
            $this->line('Check the client ID/secret, that the public key uploaded in the RM portal matches your private key, and the store ID.');
            return self::FAILURE;
        }
        $this->info('OK — signed in, request signature accepted, store: ' . ($res['item']['name'] ?? $res['item']['id'] ?? 'found'));
        $this->line('Notify URL to register with Revenue Monster (if asked): ' . route('webhook.rm.notify'));
        return self::SUCCESS;
    }
}
