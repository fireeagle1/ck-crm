<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Passport\Token;

/**
 * Admin-only management of the OAuth access tokens issued to the ChatGPT MCP
 * connector.
 *
 * This screen lives inside the existing admin area (auth + verified +
 * EnsureIsAdmin middleware applied by the route group) and is NEVER exposed to
 * the MCP surface itself. It lists issued MCP tokens and lets an administrator
 * revoke them (Requirement 1.5). Token values are never stored by Passport and
 * are never displayed here — only metadata (id, scopes, created, last used,
 * expiry) is shown.
 */
class McpTokenController extends Controller
{
    /**
     * List the OAuth access tokens issued to MCP clients.
     *
     * Exposes id, scopes, created_at, last used (updated_at) and expires_at.
     * Never exposes the token value (Passport does not persist it).
     */
    public function index(): View
    {
        $tokens = Token::query()
            ->with('client')
            ->whereHas('client', fn ($query) => $query->whereIn('name', $this->mcpClientNames()))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Token $token) => [
                'id'         => $token->getKey(),
                'client'     => $token->client?->name ?? '—',
                'scopes'     => $token->scopes ?? [],
                'revoked'    => (bool) $token->revoked,
                'created_at' => $token->created_at,
                'last_used'  => $token->updated_at, // Passport has no dedicated last-used column; updated_at is the closest signal.
                'expires_at' => $token->expires_at,
            ]);

        return view('admin.mcp.tokens', [
            'tokens' => $tokens,
        ]);
    }

    /**
     * Revoke a single MCP access token and its associated refresh token.
     *
     * After revocation the auth:api (Passport) guard rejects any subsequent call
     * presenting this token (Requirement 1.5).
     */
    public function revoke(string $id): RedirectResponse
    {
        $token = Token::query()
            ->with(['client', 'refreshToken'])
            ->whereHas('client', fn ($query) => $query->whereIn('name', $this->mcpClientNames()))
            ->findOrFail($id);

        // Revoke the refresh token first so no new access token can be minted,
        // then revoke the access token itself.
        $token->refreshToken?->revoke();
        $token->revoke();

        return redirect()
            ->route('admin.mcp.tokens.index')
            ->with('success', 'MCP token revoked. The connector must re-authorize to regain access.');
    }

    /**
     * Client names that identify tokens belonging to the MCP connector.
     *
     * @return array<int, string>
     */
    protected function mcpClientNames(): array
    {
        return ['mcp_client'];
    }
}
