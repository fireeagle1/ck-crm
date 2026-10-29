{{--
    OAuth 2.0 authorization (consent) screen.

    Rendered by Laravel\Passport\Http\Controllers\AuthorizationController@authorize
    via the binding registered in App\Providers\PassportServiceProvider with
    Passport::authorizationView('oauth.authorize'). The controller passes:
      $client    — the Passport Client requesting access (the ChatGPT MCP client)
      $user      — the currently authenticated user granting access
      $scopes    — array of Laravel\Passport\Scope the client is requesting
      $request   — the current Request (carries the OAuth query params)
      $authToken — one-time token tying this form to the session auth request

    Approve  = POST   /oauth/authorize  (passport.authorizations.approve)
    Deny     = DELETE /oauth/authorize  (passport.authorizations.deny)
    Both read the pending request from the session, so the only field they need
    is auth_token (plus CSRF).
--}}
<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Authorization request</h2>
        <p class="mt-2 text-sm text-gray-600">
            <span class="font-semibold">{{ $client->name }}</span> is requesting permission to access your
            {{ \App\Models\Setting::get('site_name', 'CK Enterprises') }} account
            as <span class="font-semibold">{{ $user->name ?? $user->email }}</span>.
        </p>
    </div>

    @if (count($scopes) > 0)
        <div class="mb-6">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400 mb-2">This will allow it to:</p>
            <ul class="space-y-2">
                @foreach ($scopes as $scope)
                    <li class="flex items-start gap-2 text-sm text-gray-700">
                        <svg class="w-4 h-4 mt-0.5 shrink-0 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $scope->description }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="mb-6">
            <p class="text-sm text-gray-700">This will allow it to access your account.</p>
        </div>
    @endif

    <div class="rounded-md bg-gray-50 border border-gray-200 p-3 mb-6 text-xs text-gray-500">
        Only approve if you started this connection from ChatGPT. You can revoke access at any time from
        the admin area. No password is shared with the connecting application.
    </div>

    <div class="flex items-center gap-3">
        {{-- Approve --}}
        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-md text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 transition">
                Authorize
            </button>
        </form>

        {{-- Deny --}}
        <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit"
                    class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-md text-sm font-semibold bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 transition">
                Cancel
            </button>
        </form>
    </div>
</x-guest-layout>
