<?php

namespace Database\Seeders;

use App\Mcp\Support\McpBotUserResolver;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the dedicated, least-privilege MCP bot user.
 *
 * This system actor exists purely to attribute MCP-originated ticket writes
 * (replies / activities). It is not an admin and cannot log in: it is flagged
 * `is_login_disabled = true` with no usable password.
 *
 * The seed is idempotent (keyed on the bot email) so re-running it never
 * creates a duplicate row.
 *
 * @see \App\Mcp\Support\McpBotUserResolver
 */
class McpBotUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the MCP bot user.
     */
    public function run(): void
    {
        $displayName = trim(McpBotUserResolver::BOT_FIRST_NAME . ' ' . McpBotUserResolver::BOT_LAST_NAME);

        $user = User::firstOrNew(
            ['email' => McpBotUserResolver::BOT_EMAIL],
            [
                'name' => $displayName,
                'first_name' => McpBotUserResolver::BOT_FIRST_NAME,
                'last_name' => McpBotUserResolver::BOT_LAST_NAME,
            ]
        );

        if ($user->exists) {
            // Keep an already-seeded row's display identity current when the
            // configured name changes (e.g. the "MCP Assistant" -> "Gordon"
            // rename). Security flags are left untouched.
            if ($user->name !== $displayName
                || $user->first_name !== McpBotUserResolver::BOT_FIRST_NAME
                || $user->last_name !== McpBotUserResolver::BOT_LAST_NAME) {
                $user->name = $displayName;
                $user->first_name = McpBotUserResolver::BOT_FIRST_NAME;
                $user->last_name = McpBotUserResolver::BOT_LAST_NAME;
                $user->save();
            }

            return;
        }

        // Guarded attributes (excluded from $fillable) must be set explicitly.
        $user->is_admin = false;
        $user->is_login_disabled = true;
        // No usable password: a random hash that nothing can reproduce.
        $user->password = Hash::make(Str::random(64));
        $user->save();
    }
}
