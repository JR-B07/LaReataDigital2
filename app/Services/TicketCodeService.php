<?php

namespace App\Services;

use App\Models\Ticket;

class TicketCodeService
{
    public function generateUniqueCode(): string
    {
        do {
            $code = 'LRD-'.strtoupper(bin2hex(random_bytes(4))).'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (Ticket::query()->where('codigo_qr', $code)->orWhere('codigo_barras', $code)->exists());

        return $code;
    }

    public function makePayload(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $json, (string) config('app.key'));

        return base64_encode(json_encode([
            'data' => $data,
            'sig' => $signature,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
