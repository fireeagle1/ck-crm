<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Passport\ClientRepository;

/**
 * Creates the public (PKCE, no-secret) OAuth client that OpenAI's custom MCP
 * connector authenticates as.
 *
 * This is a thin, documented wrapper around Passport's authorization-code grant
 * client creation with a null secret. A public client ships no client secret to
 * OpenAI, which is why the authorization code flow must use PKCE
 * (Requirement 1.2). Equivalent to the stock `php artisan passport:client --public`
 * command, but pre-named `mcp_client` and scriptable for the cPanel no-SSH cron
 * workflow documented in DEPLOYMENT.md.
 */
class CreateMcpClientCommand extends Command
{
    protected $signature = 'mcp:create-client
        {--name=mcp_client : The display name for the client}
        {--redirect-uri=* : One or more OAuth redirect URIs (OpenAI connector callback URLs)}';

    protected $description = 'Create the public (PKCE, no-secret) OAuth client used by the ChatGPT MCP connector';

    public function handle(ClientRepository $clients): int
    {
        $name = (string) $this->option('name');

        $redirectUris = $this->option('redirect-uri');

        if (empty($redirectUris)) {
            $redirectUris = [$this->ask('Where should the OAuth connector be redirected after authorization? (redirect URI)')];
        }

        $redirectUris = array_values(array_filter(array_map('trim', (array) $redirectUris)));

        if (empty($redirectUris)) {
            $this->error('At least one redirect URI is required.');

            return self::FAILURE;
        }

        // confidential: false => no client secret is generated (public client).
        // A public client cannot keep a secret, so the authorization code flow
        // is protected by PKCE instead.
        $client = $clients->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: $redirectUris,
            confidential: false,
        );

        $this->info('Public MCP OAuth client created (no client secret — PKCE required).');
        $this->newLine();
        $this->table(
            ['Field', 'Value'],
            [
                ['Client ID', $client->getKey()],
                ['Name', $client->name],
                ['Redirect URI(s)', implode(', ', $redirectUris)],
                ['Secret', '(none — public client)'],
            ]
        );
        $this->newLine();
        $this->line('Configure OpenAI\'s custom MCP connector with the Client ID above.');
        $this->line('No client secret is required; the connector must use PKCE.');

        return self::SUCCESS;
    }
}
