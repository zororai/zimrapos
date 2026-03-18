<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FiskalZW — ZIMRA Fiscalization Made Simple</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-900 font-['Instrument_Sans']">

    <!-- Nav -->
    <nav class="border-b border-gray-100 px-6 py-4">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-gray-900">FiskalZW</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="{{ route('pricing') }}" class="text-sm text-gray-600 hover:text-gray-900">Pricing</a>
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">Sign In</a>
                <a href="{{ route('register') }}" class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">
                    Start Free Trial
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="max-w-6xl mx-auto px-6 py-20 text-center">
        <div class="inline-flex items-center px-3 py-1 rounded-full bg-green-50 border border-green-200 text-green-700 text-sm font-medium mb-6">
            ✓ ZIMRA Compliant &nbsp;·&nbsp; 14-Day Free Trial
        </div>
        <h1 class="text-5xl font-extrabold text-gray-900 leading-tight mb-6">
            ZIMRA Fiscalization<br>for Every Zimbabwean Business
        </h1>
        <p class="text-xl text-gray-500 max-w-2xl mx-auto mb-10">
            Connect your POS or accounting system to ZIMRA's fiscal device in minutes.
            No technical expertise needed. Stay compliant, avoid penalties.
        </p>
        <div class="flex items-center justify-center space-x-4">
            <a href="{{ route('register') }}" class="px-8 py-4 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition text-lg shadow-lg shadow-green-200">
                Start Free — 14 Days
            </a>
            <a href="{{ route('pricing') }}" class="px-8 py-4 border border-gray-200 text-gray-700 font-semibold rounded-xl hover:border-gray-300 transition text-lg">
                See Pricing
            </a>
        </div>
        <p class="text-sm text-gray-400 mt-4">No credit card required. Cancel anytime.</p>
    </section>

    <!-- Social proof -->
    <section class="bg-gray-50 border-y border-gray-100 py-10">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <p class="text-gray-500 text-sm font-medium uppercase tracking-wider mb-6">Trusted by businesses across Zimbabwe</p>
            <div class="grid grid-cols-3 md:grid-cols-6 gap-6 items-center opacity-40">
                @foreach(['Retail', 'Hotels', 'Clinics', 'Restaurants', 'Wholesale', 'Pharmacies'] as $industry)
                <div class="text-center text-xs font-semibold text-gray-600 uppercase tracking-wide">{{ $industry }}</div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="max-w-6xl mx-auto px-6 py-20">
        <div class="text-center mb-14">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Everything you need to stay compliant</h2>
            <p class="text-gray-500 text-lg">Built specifically for ZIMRA's FDMS v7.2 requirements</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @php
            $features = [
                ['icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title'=>'Automatic Receipt Signing', 'desc'=>'Digital signatures using ECDSA P-256. Every receipt is cryptographically signed and verified by ZIMRA automatically.'],
                ['icon'=>'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', 'title'=>'Multi-Company Support', 'desc'=>'Manage multiple companies and devices under one account. Perfect for accountants and businesses with multiple branches.'],
                ['icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', 'title'=>'Fiscal Day Management', 'desc'=>'Automated open and close of fiscal days. Never miss a deadline or face penalties for improper day closures.'],
                ['icon'=>'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 3.5a.5.5 0 11-1 0 .5.5 0 011 0z', 'title'=>'QR Code Receipts', 'desc'=>'Every receipt gets a ZIMRA-compliant QR code for instant customer verification. Works offline too.'],
                ['icon'=>'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'title'=>'All Document Types', 'desc'=>'Sales, invoices, credit notes, debit notes, delivery notes, and quotations — all fiscalized and submitted to ZIMRA.'],
                ['icon'=>'M13 10V3L4 14h7v7l9-11h-7z', 'title'=>'Instant API Integration', 'desc'=>'REST API lets your existing POS or ERP connect in hours. Detailed docs included. SDKs coming soon.'],
            ];
            @endphp
            @foreach($features as $f)
            <div class="p-6 rounded-2xl border border-gray-100 hover:border-green-200 hover:shadow-sm transition">
                <div class="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $f['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-900 mb-2">{{ $f['title'] }}</h3>
                <p class="text-gray-500 text-sm leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    <!-- How it works -->
    <section class="bg-gray-50 border-y border-gray-100 py-20">
        <div class="max-w-4xl mx-auto px-6">
            <div class="text-center mb-14">
                <h2 class="text-3xl font-bold text-gray-900 mb-3">Up and running in 4 steps</h2>
                <p class="text-gray-500">From sign-up to your first fiscalized receipt</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                @php
                $steps = [
                    ['n'=>'1','title'=>'Create Account','desc'=>'Register and start your 14-day free trial instantly.'],
                    ['n'=>'2','title'=>'Add Your Company','desc'=>'Enter your company details and ZIMRA TIN number.'],
                    ['n'=>'3','title'=>'Register Device','desc'=>'Input your ZIMRA device ID and activation key.'],
                    ['n'=>'4','title'=>'Start Fiscalizing','desc'=>'Submit receipts via our dashboard or REST API.'],
                ];
                @endphp
                @foreach($steps as $step)
                <div class="text-center">
                    <div class="w-12 h-12 bg-green-600 text-white rounded-full flex items-center justify-center font-bold text-lg mx-auto mb-4">
                        {{ $step['n'] }}
                    </div>
                    <h3 class="font-bold text-gray-900 mb-2">{{ $step['title'] }}</h3>
                    <p class="text-gray-500 text-sm">{{ $step['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Pricing preview -->
    <section class="max-w-6xl mx-auto px-6 py-20">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-900 mb-3">Simple, transparent pricing</h2>
            <p class="text-gray-500">Start free. Pay only when you're ready.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
            <!-- Trial -->
            <div class="p-8 rounded-2xl border border-gray-200">
                <p class="font-bold text-gray-500 mb-2">FREE TRIAL</p>
                <p class="text-4xl font-extrabold text-gray-900 mb-1">$0</p>
                <p class="text-gray-400 text-sm mb-6">14 days, no card needed</p>
                <ul class="space-y-3 text-sm text-gray-600 mb-8">
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> 1 company</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Full feature access</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Email support</li>
                </ul>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 border border-gray-300 rounded-xl font-semibold text-gray-700 hover:border-green-500 hover:text-green-600 transition">
                    Start Free Trial
                </a>
            </div>
            <!-- Basic -->
            <div class="p-8 rounded-2xl border-2 border-green-500 relative shadow-lg shadow-green-100">
                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 px-4 py-1 bg-green-600 text-white text-xs font-bold rounded-full">MOST POPULAR</div>
                <p class="font-bold text-green-600 mb-2">BASIC</p>
                <p class="text-4xl font-extrabold text-gray-900 mb-1">$20<span class="text-lg font-normal text-gray-400">/mo</span></p>
                <p class="text-gray-400 text-sm mb-6">Per company</p>
                <ul class="space-y-3 text-sm text-gray-600 mb-8">
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Up to 3 companies</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Unlimited receipts</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> REST API access</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Priority support</li>
                </ul>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition">
                    Get Started
                </a>
            </div>
            <!-- Pro -->
            <div class="p-8 rounded-2xl border border-gray-200">
                <p class="font-bold text-gray-500 mb-2">PRO</p>
                <p class="text-4xl font-extrabold text-gray-900 mb-1">$50<span class="text-lg font-normal text-gray-400">/mo</span></p>
                <p class="text-gray-400 text-sm mb-6">Per account</p>
                <ul class="space-y-3 text-sm text-gray-600 mb-8">
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Unlimited companies</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Unlimited receipts</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> REST API + Webhooks</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> Dedicated support</li>
                    <li class="flex items-center"><span class="text-green-500 mr-2">✓</span> White-label option</li>
                </ul>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 border border-gray-300 rounded-xl font-semibold text-gray-700 hover:border-green-500 hover:text-green-600 transition">
                    Get Started
                </a>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-green-600 py-20">
        <div class="max-w-3xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-white mb-4">Ready to stay ZIMRA compliant?</h2>
            <p class="text-green-100 text-lg mb-8">Join businesses across Zimbabwe using FiskalZW. Start your free 14-day trial today.</p>
            <a href="{{ route('register') }}" class="inline-block px-10 py-4 bg-white text-green-700 font-bold rounded-xl hover:bg-green-50 transition text-lg shadow-lg">
                Start Free Trial — No Card Required
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-gray-100 py-10">
        <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between text-sm text-gray-400">
            <div class="flex items-center space-x-2 mb-4 md:mb-0">
                <div class="w-6 h-6 bg-green-600 rounded flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="font-semibold text-gray-600">FiskalZW</span>
            </div>
            <p>© {{ date('Y') }} FiskalZW. ZIMRA Fiscal Device Management System.</p>
            <div class="flex space-x-4 mt-4 md:mt-0">
                <a href="{{ route('pricing') }}" class="hover:text-gray-600">Pricing</a>
                <a href="{{ route('login') }}" class="hover:text-gray-600">Login</a>
                <a href="{{ route('register') }}" class="hover:text-gray-600">Register</a>
            </div>
        </div>
    </footer>

</body>
</html>
