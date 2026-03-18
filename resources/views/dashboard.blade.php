<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — FiskalZW</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen font-['Instrument_Sans']">

    <!-- Nav -->
    <nav class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="font-bold text-gray-900">FiskalZW</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Sign out</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10">

        <!-- Trial banner -->
        @if(auth()->user()->onTrial())
        <div class="mb-6 flex items-center justify-between bg-amber-50 border border-amber-200 rounded-xl px-6 py-4">
            <div class="flex items-center space-x-3">
                <svg class="w-5 h-5 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-sm text-amber-800 font-medium">
                    You have <strong>{{ auth()->user()->trialDaysLeft() }} days</strong> left on your free trial.
                </p>
            </div>
            <a href="{{ route('pricing') }}" class="px-4 py-2 bg-amber-500 text-white text-sm font-semibold rounded-lg hover:bg-amber-600 transition">
                Upgrade Now
            </a>
        </div>
        @endif

        <!-- Welcome / Onboarding (no companies yet) -->
        @if($companies->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center mb-8">
            <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-3">Welcome to FiskalZW, {{ auth()->user()->name }}!</h2>
            <p class="text-gray-500 mb-8 max-w-md mx-auto">
                Let's get your first company connected to ZIMRA. It takes less than 5 minutes.
            </p>

            <!-- Steps -->
            <div class="flex items-start justify-center gap-4 mb-10 max-w-2xl mx-auto">
                @php $steps = [
                    ['n'=>1,'label'=>'Add Company','sub'=>'Enter your TIN & details','active'=>true],
                    ['n'=>2,'label'=>'Register Device','sub'=>'Input ZIMRA device ID','active'=>false],
                    ['n'=>3,'label'=>'Open Fiscal Day','sub'=>'Start submitting receipts','active'=>false],
                ]; @endphp
                @foreach($steps as $i => $step)
                    @if($i > 0)<div class="flex-none pt-4 text-gray-300 text-xl">→</div>@endif
                    <div class="flex-1 text-center">
                        <div class="w-10 h-10 {{ $step['active'] ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400' }} rounded-full flex items-center justify-center font-bold mx-auto mb-3">{{ $step['n'] }}</div>
                        <p class="text-sm font-semibold {{ $step['active'] ? 'text-gray-900' : 'text-gray-500' }} mb-1">{{ $step['label'] }}</p>
                        <p class="text-xs text-gray-400">{{ $step['sub'] }}</p>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('zimra') }}" class="inline-block px-8 py-4 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition text-lg shadow-lg shadow-green-100">
                Add Your First Company →
            </a>
        </div>

        @else

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="text-gray-500 text-sm mt-1">Manage your ZIMRA companies and devices</p>
            </div>
            <a href="{{ route('zimra') }}" class="px-5 py-2.5 bg-green-600 text-white font-semibold rounded-xl hover:bg-green-700 transition text-sm">
                Open ZIMRA Console →
            </a>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <p class="text-sm text-gray-500 mb-1">Companies</p>
                <p class="text-3xl font-bold text-gray-900">{{ $companies->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <p class="text-sm text-gray-500 mb-1">Registered Devices</p>
                <p class="text-3xl font-bold text-gray-900">{{ $companies->whereNotNull('device_id')->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <p class="text-sm text-gray-500 mb-1">Fiscal Day</p>
                <p class="text-3xl font-bold {{ $companies->where('fiscal_day_status', 'FiscalDayOpened')->count() > 0 ? 'text-green-600' : 'text-gray-400' }}">
                    {{ $companies->where('fiscal_day_status', 'FiscalDayOpened')->count() > 0 ? 'Open' : 'Closed' }}
                </p>
            </div>
        </div>

        <!-- Company list -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Your Companies</h2>
                <a href="{{ route('zimra') }}" class="text-sm text-green-600 hover:text-green-700 font-medium">+ Add Company</a>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fiscal Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($companies as $company)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $company->company_name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $company->device_id ? '#'.$company->device_id : '—' }}</td>
                        <td class="px-6 py-4">
                            @if($company->fiscal_day_status === 'FiscalDayOpened')
                                <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded-full text-xs font-medium">Open</span>
                            @else
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">Closed</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($company->is_active)
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">Active</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('zimra') }}" class="text-green-600 hover:text-green-700 text-sm font-medium">Manage →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </main>

</body>
</html>
