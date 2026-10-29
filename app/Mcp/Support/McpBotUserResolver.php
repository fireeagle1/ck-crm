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
 * sensible actor name ("Gordon ..."). This resolver returns that bot
 * user, creating it on first use if the seeder has not run.
 *
 * The bot presents to customers as "Gordon" from CK Enterprises Support, with
 * the job title "AI Support Agent" ({@see self::BOT_JOB_TITLE}).
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
     * The bot user's first name (renders in ticket activity descriptions and
     * as the reply author customers see).
     */
    public const BOT_FIRST_NAME = 'Gordon';

    /**
     * The bot user's last name. Intentionally empty so `full_name` renders as
     * just "Gordon" rather than "Gordon <surname>".
     */
    public const BOT_LAST_NAME = '';

    /**
     * The bot's job title, shown alongside the name on tickets and replies
     * (e.g. in signatures). Not persisted on the users table.
     */
    public const BOT_JOB_TITLE = 'AI Support Agent';

    /**
     * The support team / organisation the bot represents to customers.
     */
    public const BOT_ORGANISATION = 'CK Enterprises Support';

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

        $displayName = trim(self::BOT_FIRST_NAME . ' ' . self::BOT_LAST_NAME);

        $user = User::firstOrNew(
            ['email' => self::BOT_EMAIL],
            [
                'name' => $displayName,
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
        } elseif ($user->name !== $displayName
            || $user->first_name !== self::BOT_FIRST_NAME
            || $user->last_name !== self::BOT_LAST_NAME) {
            // Keep an already-seeded row's display identity in sync when the
            // configured name changes (e.g. the "MCP Assistant" -> "Gordon"
            // rename). Does not touch guarded security flags.
            $user->name = $displayName;
            $user->first_name = self::BOT_FIRST_NAME;
            $user->last_name = self::BOT_LAST_NAME;
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
