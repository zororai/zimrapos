<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pricing — FiskalZW</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white text-gray-900 font-['Instrument_Sans']">

    <!-- Nav -->
    <nav class="border-b border-gray-100 px-6 py-4">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-gray-900">FiskalZW</span>
            </a>
            <div class="flex items-center space-x-4">
                <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">Sign In</a>
                <a href="{{ route('register') }}" class="px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition">Start Free Trial</a>
            </div>
        </div>
    </nav>

    @if(session('expired'))
    <div class="bg-red-50 border-b border-red-200 px-6 py-4 text-center text-red-700 text-sm font-medium">
        Your 14-day trial has expired. Choose a plan below to continue.
    </div>
    @endif

    <section class="max-w-5xl mx-auto px-6 py-20">
        <div class="text-center mb-14">
            <h1 class="text-4xl font-extrabold text-gray-900 mb-4">Simple Pricing</h1>
            <p class="text-gray-500 text-lg">Start free. No credit card required for your trial.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Trial -->
            <div class="p-8 rounded-2xl border border-gray-200 flex flex-col">
                <div class="flex-1">
                    <p class="font-bold text-gray-400 text-xs uppercase tracking-wider mb-3">Free Trial</p>
                    <p class="text-4xl font-extrabold text-gray-900 mb-1">$0</p>
                    <p class="text-gray-400 text-sm mb-8">14 days · no card needed</p>
                    <ul class="space-y-3 text-sm text-gray-600 mb-8">
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>1 company</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>All features unlocked</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>ZIMRA device registration</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Fiscal day management</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Receipt submission</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Email support</li>
                    </ul>
                </div>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 border border-gray-300 rounded-xl font-semibold text-gray-700 hover:border-green-500 hover:text-green-600 transition">
                    Start Free Trial
                </a>
            </div>

            <!-- Basic -->
            <div class="p-8 rounded-2xl border-2 border-green-500 relative shadow-xl shadow-green-100 flex flex-col">
                <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 px-4 py-1.5 bg-green-600 text-white text-xs font-bold rounded-full shadow">MOST POPULAR</div>
                <div class="flex-1">
                    <p class="font-bold text-green-600 text-xs uppercase tracking-wider mb-3">Basic</p>
                    <p class="text-4xl font-extrabold text-gray-900 mb-1">$20<span class="text-lg font-normal text-gray-400">/mo</span></p>
                    <p class="text-gray-400 text-sm mb-8">Per account · billed monthly</p>
                    <ul class="space-y-3 text-sm text-gray-600 mb-8">
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Up to 3 companies</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Unlimited receipts</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>All document types</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>REST API access</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>ZIMRA device management</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Priority support</li>
                    </ul>
                </div>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition">
                    Get Started
                </a>
            </div>

            <!-- Pro -->
            <div class="p-8 rounded-2xl border border-gray-200 flex flex-col">
                <div class="flex-1">
                    <p class="font-bold text-gray-400 text-xs uppercase tracking-wider mb-3">Pro</p>
                    <p class="text-4xl font-extrabold text-gray-900 mb-1">$50<span class="text-lg font-normal text-gray-400">/mo</span></p>
                    <p class="text-gray-400 text-sm mb-8">Per account · billed monthly</p>
                    <ul class="space-y-3 text-sm text-gray-600 mb-8">
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span><strong>Unlimited</strong> companies</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Unlimited receipts</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>REST API + Webhooks</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Custom branding / white-label</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>Dedicated account manager</li>
                        <li class="flex items-start"><span class="text-green-500 mr-2 mt-0.5">✓</span>SLA guarantee</li>
                    </ul>
                </div>
                <a href="{{ route('register') }}" class="block text-center px-6 py-3 border border-gray-300 rounded-xl font-semibold text-gray-700 hover:border-green-500 hover:text-green-600 transition">
                    Get Started
                </a>
            </div>
        </div>

        <!-- FAQ -->
        <div class="mt-20">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-10">Frequently Asked Questions</h2>
            <div class="max-w-2xl mx-auto space-y-6">
                @php $faqs = [
                    ['q'=>'Do I need a physical ZIMRA device?','a'=>'Yes, you need a ZIMRA-issued device ID and activation key. FiskalZW connects your software to that device via the ZIMRA FDMS API.'],
                    ['q'=>'Can I use this with my existing POS?','a'=>'Yes. Our REST API integrates with any POS, ERP, or accounting software. We also offer a web dashboard for manual submissions.'],
                    ['q'=>'What happens after my trial ends?','a'=>'Your account is paused until you subscribe. Your data is preserved. Choose any plan to continue from where you left off.'],
                    ['q'=>'How do I pay?','a'=>'Currently we accept EcoCash, bank transfer, and Innbucks. Contact us after signing up to activate your subscription.'],
                    ['q'=>'Is my data secure?','a'=>'All data is encrypted at rest and in transit. Private keys are stored securely and never exposed via the API.'],
                ]; @endphp
                @foreach($faqs as $faq)
                <div class="border border-gray-100 rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 mb-2">{{ $faq['q'] }}</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">{{ $faq['a'] }}</p>
                </div>
                @endforeach
            </div>
        </div>

        <div class="text-center mt-14">
            <p class="text-gray-500 mb-4">Have a question? We're happy to help.</p>
            <a href="mailto:hello@fiskalzw.co.zw" class="text-green-600 font-semibold hover:text-green-700">hello@fiskalzw.co.zw</a>
        </div>
    </section>

</body>
</html>
