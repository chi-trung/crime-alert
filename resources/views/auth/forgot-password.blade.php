<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{-- Issue #335: this page rendered fully in English while the app
             runs locale vi. The raw Breeze key had no vi entry and Laravel
             silently returned the English source string (fallback_locale en
             masks the gap — no exception, green CI), so the Vietnamese
             string that already sat in messages.php was never reached. --}}
        {{ __('messages.forgot_password_notice') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('messages.email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('messages.send_password_reset_link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
