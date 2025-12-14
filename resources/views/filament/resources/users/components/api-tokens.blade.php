@php
    $record = $getRecord();
    $tokens = $record ? $record->tokens()
        ->where(function($query) {
            $query->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
        })
        ->orderBy('created_at', 'desc')
        ->get() : collect();
@endphp

@if($tokens->isEmpty())
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No active API tokens found.
    </div>
@else
    <div class="mb-4 flex justify-end">
        <button
            type="button"
            wire:click="clearAllTokens"
            wire:confirm="Are you sure you want to clear all API tokens? This will revoke all active tokens and users will need to log in again. This action cannot be undone."
            class="inline-flex items-center gap-2 rounded-lg bg-danger-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-danger-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger-600 dark:bg-danger-500 dark:hover:bg-danger-600"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75 14.25 12m0 0 2.25 2.25M14.25 12l2.25-2.25M14.25 12H3m6 6.75h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H9m-6 0a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 3 18.75m6 0V21a2.25 2.25 0 0 0 2.25 2.25h4.5A2.25 2.25 0 0 0 18 21v-2.25m-6 0h6" />
            </svg>
            Clear All Tokens
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Token Name</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Created At</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Last Used</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Expires At</th>
                    <th class="text-left py-2 px-3 font-semibold text-gray-700 dark:text-gray-300">Abilities</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tokens as $token)
                    <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="py-2 px-3">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $token->name }}</span>
                        </td>
                        <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                            {{ $token->created_at->format('Y-m-d H:i:s') }}
                            <div class="text-xs text-gray-500 dark:text-gray-500">
                                {{ $token->created_at->diffForHumans() }}
                            </div>
                        </td>
                        <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                            @if($token->last_used_at)
                                {{ $token->last_used_at->format('Y-m-d H:i:s') }}
                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                    {{ $token->last_used_at->diffForHumans() }}
                                </div>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Never</span>
                            @endif
                        </td>
                        <td class="py-2 px-3 text-gray-600 dark:text-gray-400">
                            @if($token->expires_at)
                                @if($token->expires_at->isPast())
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        Expired
                                    </span>
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $token->expires_at->format('Y-m-d H:i:s') }}
                                    </div>
                                @else
                                    {{ $token->expires_at->format('Y-m-d H:i:s') }}
                                    <div class="text-xs text-gray-500 dark:text-gray-500">
                                        {{ $token->expires_at->diffForHumans() }}
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-400 dark:text-gray-500">Never</span>
                            @endif
                        </td>
                        <td class="py-2 px-3">
                            @if(empty($token->abilities) || in_array('*', $token->abilities))
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    All Abilities
                                </span>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach($token->abilities as $ability)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                            {{ $ability }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Total: {{ $tokens->count() }} active token(s)
    </div>
@endif
