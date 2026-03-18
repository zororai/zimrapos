<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account — FiskalZW</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center font-['Instrument_Sans']">

    <div class="w-full max-w-md px-6 py-10">

        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center space-x-2">
                <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-2xl font-bold text-gray-900">FiskalZW</span>
            </a>
            <p class="text-gray-500 mt-2 text-sm">Create your account — 14 days free, no card required</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">Full name</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                        class="w-full px-4 py-2.5 border {{ $errors->has('name') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    @error('name')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                        class="w-full px-4 py-2.5 border {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="new-password"
                        class="w-full px-4 py-2.5 border {{ $errors->has('password') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    @error('password')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                        class="w-full px-4 py-2.5 border {{ $errors->has('password_confirmation') ? 'border-red-400 bg-red-50' : 'border-gray-300' }} rounded-xl text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    @error('password_confirmation')
                        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Submit -->
                <button type="submit"
                    class="w-full py-3 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    Create Account — Start Free Trial
                </button>

                <p class="text-center text-xs text-gray-400">By registering you agree to our terms of service.</p>
            </form>
        </div>

        <p class="text-center text-sm text-gray-500 mt-6">
            Already have an account?
            <a href="{{ route('login') }}" class="text-green-600 font-semibold hover:text-green-700">Sign in</a>
        </p>

    </div>

</body>
</html>
