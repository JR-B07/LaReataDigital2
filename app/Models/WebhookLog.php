<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $table = 'mercadopago_webhook_logs';

    protected $fillable = [
        'type',
        'resource_id',
        'order_id',
        'status',
        'payload',
        'response',
        'error_message',
        'retry_count',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Relación con la orden
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Scope para webhooks pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para webhooks fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope para webhooks exitosos
     */
    public function scopeSuccess($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Marcar como procesado exitosamente
     */
    public function markAsSuccess(array $response = []): void
    {
        $this->update([
            'status' => 'success',
            'response' => $response,
            'processed_at' => now(),
        ]);
    }

    /**
     * Marcar como fallido
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->increment('retry_count');
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'processed_at' => now(),
        ]);
    }

    /**
     * Marcar como procesando
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }
}
