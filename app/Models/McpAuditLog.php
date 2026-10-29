<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'access_token_id',
        'mcp_identity',
        'tool_name',
        'scope_used',
        'outcome',
        'reason',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
