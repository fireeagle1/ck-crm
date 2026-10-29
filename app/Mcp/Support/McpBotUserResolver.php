<?php

namespace App\Mcp\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves the dedicated, least-privilege MCP bot user.
 *
 * MCP-originated ticket writes need a real {@see User} row to attribute
 * `ticket_replies.user_id` / `ticket_activities.user_id` (both non-nullable
 * FKs) and to keep `TicketActivity::getDescriptionAttribute()` rendering a
 * sensible actor name ("MCP Assistant ..."). This resolver returns that bot
 * user, creating it on first use if the seeder has not run.
 *
 * The bot user is deliberately NOT an admin and is flagged
 * `is_login_disabled = true` with no usable password, so it can never
 * authenticate to the web app or the Sanctum admin API. Authorization for MCP
 * tool calls derives solely from OAuth scopes, never from this user's role.
 *
 * The resolved user is cached in-process for the lifetime of the request so
 * repeated write tools in a single MCP call share one lookup.
 *
 * @see \Database\Seeders\McpBotUserSeeder
 */
final class McpBotUserResolver
{
    /**
     * The stable email used to identify the bot user row.
     */
    public const BOT_EMAIL = 'mcp-bot@system.local';

    /**
     * The bot user's first name (renders in ticket activity descriptions).
     */
    public const BOT_FIRST_NAME = 'MCP';

    /**
     * The bot user's last name (renders in ticket activity descriptions).
     */
    public const BOT_LAST_NAME = 'Assistant';

    /**
     * In-process cache of the resolved bot user.
     */
    private ?User $botUser = null;

    /**
     * Resolve the MCP bot user, creating it if it does not yet exist.
     *
     * Uses `firstOrCreate` semantics keyed on {@see self::BOT_EMAIL} so the
     * resolver is safe whether or not the seeder has run, and never produces
     * a duplicate row. The guarded `is_admin` / `is_login_disabled` attributes
     * are set explicitly on the instance (they are intentionally excluded from
     * the model's `$fillable`).
     */
    public function resolve(): User
    {
        if ($this->botUser !== null) {
            return $this->botUser;
        }

        $user = User::firstOrNew(
            ['email' => self::BOT_EMAIL],
            [
                'name' => self::BOT_FIRST_NAME . ' ' . self::BOT_LAST_NAME,
                'first_name' => self::BOT_FIRST_NAME,
                'last_name' => self::BOT_LAST_NAME,
            ]
        );

        if (! $user->exists) {
            // Guarded attributes must be assigned explicitly (not mass-assignable).
            $user->is_admin = false;
            $user->is_login_disabled = true;
            // No usable password: a random hash that nothing can reproduce.
            $user->password = Hash::make(Str::random(64));
            $user->save();
        }

        return $this->botUser = $user;
    }

    /**
     * Clear the in-process cache (primarily for tests).
     */
    public function forget(): void
    {
        $this->botUser = null;
    }
}
