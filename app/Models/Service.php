<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'type',
        'name',
        'domain',
        'status',
        'url',
        'failed_reason',
        'admin_service_id',
        'provisioned_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'provisioned_at' => 'datetime',
            'synced_at'      => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }
}
