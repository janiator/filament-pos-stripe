@php
    $reviewData = $this->getReviewData();
@endphp

<div class="space-y-6">
    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Store Information</h3>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Store Name</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['store']['name'] }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Email</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['store']['email'] }}</dd>
            </div>
            @if($reviewData['store']['organisasjonsnummer'])
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Organisasjonsnummer</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['store']['organisasjonsnummer'] }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Commission Configuration</h3>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Commission Type</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ ucfirst($reviewData['commission']['type']) }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Commission Rate</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ $reviewData['commission']['rate'] }}
                    {{ $reviewData['commission']['type'] === 'percentage' ? '%' : 'units' }}
                </dd>
            </div>
        </dl>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Stripe Account</h3>
        <dl class="grid grid-cols-1 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Setup Type</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    {{ $reviewData['stripe']['setup_type'] === 'create' ? 'Create New Stripe Account' : 'Link Existing Stripe Account' }}
                </dd>
            </div>
            @if($reviewData['stripe']['setup_type'] === 'link' && $reviewData['stripe']['account_id'])
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Stripe Account ID</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $reviewData['stripe']['account_id'] }}</dd>
            </div>
            @endif
        </dl>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Settings</h3>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Currency</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white uppercase">{{ $reviewData['settings']['currency'] }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Timezone</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['settings']['timezone'] }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Locale</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['settings']['locale'] }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Default VAT Rate</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ number_format($reviewData['settings']['default_vat_rate'], 2) }}%</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tax Included</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['settings']['tax_included'] ? 'Yes' : 'No' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tips Enabled</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $reviewData['settings']['tips_enabled'] ? 'Yes' : 'No' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Assigned Users</h3>
        <ul class="space-y-2">
            @forelse($reviewData['users'] as $user)
                <li class="text-sm text-gray-900 dark:text-white">
                    {{ $user->name }} ({{ $user->email }})
                </li>
            @empty
                <li class="text-sm text-gray-500 dark:text-gray-400">No users assigned</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 dark:bg-blue-900/20 dark:border-blue-800">
        <p class="text-sm text-blue-800 dark:text-blue-200">
            <strong>Ready to complete setup?</strong> Click the "Complete Setup" button below to create the store with all the configured settings.
        </p>
    </div>
</div>



