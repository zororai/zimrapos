<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ZIMRA Fiscalization - {{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 min-h-screen" x-data="zimraApp()" x-init="init()">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">ZIMRA Fiscalization</h1>
                        <p class="text-sm text-gray-500">Fiscal Device Management System</p>
                    </div>
                </div>
                <a href="/" class="text-sm text-gray-600 hover:text-gray-900 flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Back to Home</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Status Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <!-- Config Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Configuration</p>
                        <p class="text-lg font-semibold" :class="config ? 'text-green-600' : 'text-red-600'" x-text="config ? 'Active' : 'Not Set'"></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="config ? 'bg-green-100' : 'bg-red-100'">
                        <svg class="w-5 h-5" :class="config ? 'text-green-600' : 'text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Device Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Device</p>
                        <p class="text-lg font-semibold" :class="config?.device_id ? 'text-green-600' : 'text-yellow-600'" x-text="config?.device_id ? 'Registered' : 'Not Registered'"></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="config?.device_id ? 'bg-green-100' : 'bg-yellow-100'">
                        <svg class="w-5 h-5" :class="config?.device_id ? 'text-green-600' : 'text-yellow-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Fiscal Day Status -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Fiscal Day</p>
                        <p class="text-lg font-semibold" :class="fiscalDay?.is_open ? 'text-green-600' : 'text-gray-600'" x-text="fiscalDay?.is_open ? 'Open #' + fiscalDay.fiscal_day?.fiscal_day_no : 'Closed'"></p>
                    </div>
                    <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="fiscalDay?.is_open ? 'bg-green-100' : 'bg-gray-100'">
                        <svg class="w-5 h-5" :class="fiscalDay?.is_open ? 'text-green-600' : 'text-gray-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Receipt Counter -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Today's Receipts</p>
                        <p class="text-lg font-semibold text-blue-600" x-text="fiscalDay?.fiscal_day?.receipt_counter || 0"></p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <template x-if="message">
            <div class="mb-6 p-4 rounded-lg" :class="messageType === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" :class="messageType === 'success' ? 'text-green-600' : 'text-red-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="messageType === 'success'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        <path x-show="messageType === 'error'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span :class="messageType === 'success' ? 'text-green-800' : 'text-red-800'" x-text="message"></span>
                    <button @click="message = ''" class="ml-auto">
                        <svg class="w-4 h-4 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button @click="activeTab = 'config'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'config' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Configuration
                    </button>
                    <button @click="activeTab = 'device'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'device' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Device Registration
                    </button>
                    <button @click="activeTab = 'fiscal'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'fiscal' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Fiscal Day
                    </button>
                    <button @click="activeTab = 'receipts'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'receipts' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Submit Receipt
                    </button>
                    <button @click="activeTab = 'submitfile'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'submitfile' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Submit File
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <!-- Configuration Tab -->
                <div x-show="activeTab === 'config'" x-cloak>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">ZIMRA Configuration</h2>
                    
                    <form @submit.prevent="saveConfig()" class="space-y-4 max-w-xl">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Base URL</label>
                            <input type="url" x-model="configForm.base_url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="https://fdmsapitest.zimra.co.zw" required>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Model</label>
                                <input type="text" x-model="configForm.device_model" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="Server" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Device Version</label>
                                <input type="text" x-model="configForm.device_version" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="v1" required>
                            </div>
                        </div>
                        <div>
                            <button type="submit" :disabled="loading" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="config ? 'Update Configuration' : 'Save Configuration'"></span>
                            </button>
                        </div>
                    </form>

                    <template x-if="config">
                        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">Current Configuration</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div><span class="text-gray-500">Base URL:</span> <span class="font-medium" x-text="config.base_url"></span></div>
                                <div><span class="text-gray-500">Device Model:</span> <span class="font-medium" x-text="config.device_model"></span></div>
                                <div><span class="text-gray-500">Device Version:</span> <span class="font-medium" x-text="config.device_version"></span></div>
                                <div><span class="text-gray-500">Device ID:</span> <span class="font-medium" x-text="config.device_id || 'Not registered'"></span></div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Device Registration Tab -->
                <div x-show="activeTab === 'device'" x-cloak>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Device Registration</h2>
                    
                    <template x-if="!config">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please configure ZIMRA settings first before registering a device.
                        </div>
                    </template>

                    <template x-if="config && config.device_id">
                        <div class="space-y-4">
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div class="flex items-center justify-between text-green-800 mb-3">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="font-semibold">Device Registered Successfully</span>
                                    </div>
                                    <button @click="getDeviceStatus()" :disabled="loading" class="px-3 py-1 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700 disabled:opacity-50 flex items-center space-x-1">
                                        <svg x-show="loading" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Get Status</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm text-green-700">
                                    <div><span class="text-green-600">Device ID:</span> <strong x-text="config.device_id"></strong></div>
                                    <div><span class="text-green-600">Serial Number:</span> <strong x-text="config.serial_number || 'N/A'"></strong></div>
                                    <div><span class="text-green-600">Certificate:</span> <strong x-text="config.certificate ? '✓ Stored' : '✗ Missing'"></strong></div>
                                    <div><span class="text-green-600">API URL:</span> <strong x-text="config.base_url + '/Device/v1/' + config.device_id + '/GetStatus'"></strong></div>
                                </div>
                            </div>

                            <!-- Device Status Response -->
                            <template x-if="deviceStatus">
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h4 class="font-semibold text-blue-800 mb-2">Device Status (GetStatus Response)</h4>
                                    <pre class="text-xs bg-blue-100 p-3 rounded overflow-x-auto text-blue-900" x-text="JSON.stringify(deviceStatus, null, 2)"></pre>
                                </div>
                            </template>

                            <p class="text-sm text-gray-500">Your device is registered and ready. Go to the <button @click="activeTab = 'fiscal'" class="text-green-600 hover:underline font-medium">Fiscal Day</button> tab to open a fiscal day.</p>
                        </div>
                    </template>

                    <template x-if="config && !config.device_id">
                        <div class="space-y-6">
                            <!-- Registration Mode Toggle -->
                            <div class="flex space-x-4 border-b border-gray-200 pb-4">
                                <button @click="registrationMode = 'new'" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" :class="registrationMode === 'new' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    New Registration
                                </button>
                                <button @click="registrationMode = 'upload'" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors" :class="registrationMode === 'upload' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                                    Upload Existing Certificates
                                </button>
                            </div>

                            <!-- New Registration Form -->
                            <form x-show="registrationMode === 'new'" @submit.prevent="registerDevice()" class="space-y-4 max-w-xl">
                                <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm">
                                    Use this option to register a new device with ZIMRA using an activation key.
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Device ID</label>
                                    <input type="number" x-model="registerForm.device_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="32558" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
                                    <input type="text" x-model="registerForm.serial_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="your-serial-number" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Activation Key</label>
                                    <input type="text" x-model="registerForm.activation_key" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="00362772" required>
                                </div>
                                <div>
                                    <button type="submit" :disabled="loading" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                        <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Register Device</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Upload Certificates Form -->
                            <form x-show="registrationMode === 'upload'" @submit.prevent="uploadCertificates()" class="space-y-4 max-w-xl">
                                <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm">
                                    Use this option if the device was already registered and you have the certificate files.
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Device ID</label>
                                    <input type="number" x-model="uploadForm.device_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="32558" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
                                    <input type="text" x-model="uploadForm.serial_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="your-serial-number" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Device Certificate (device_certificate.pem)</label>
                                    <input type="file" @change="handleCertificateFile($event)" accept=".pem,.crt,.cer" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100" required>
                                    <p class="text-xs text-gray-500 mt-1">Upload the device certificate file (.pem format)</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Private Key (device_private.key)</label>
                                    <input type="file" @change="handlePrivateKeyFile($event)" accept=".key,.pem" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 file:mr-4 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:font-medium file:bg-green-50 file:text-green-700 hover:file:bg-green-100" required>
                                    <p class="text-xs text-gray-500 mt-1">Upload the device private key file (.key or .pem format)</p>
                                </div>
                                <div>
                                    <button type="submit" :disabled="loading || !uploadForm.certificate || !uploadForm.private_key" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                        <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Save Certificates</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </template>
                </div>

                <!-- Fiscal Day Tab -->
                <div x-show="activeTab === 'fiscal'" x-cloak>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Fiscal Day Management</h2>
                    
                    <template x-if="!config?.device_id">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please register a device first before managing fiscal days.
                        </div>
                    </template>

                    <template x-if="config?.device_id">
                        <div class="space-y-6">
                            <!-- Current Status -->
                            <div class="p-6 rounded-lg" :class="fiscalDay?.is_open ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold" :class="fiscalDay?.is_open ? 'text-green-800' : 'text-gray-800'" x-text="fiscalDay?.is_open ? 'Fiscal Day #' + fiscalDay.fiscal_day?.fiscal_day_no + ' is Open' : 'No Open Fiscal Day'"></h3>
                                        <p class="text-sm mt-1" :class="fiscalDay?.is_open ? 'text-green-600' : 'text-gray-600'">
                                            <template x-if="fiscalDay?.is_open">
                                                <span>Opened at: <span x-text="new Date(fiscalDay.fiscal_day?.opened_at).toLocaleString()"></span> | Receipts: <span x-text="fiscalDay.fiscal_day?.receipt_counter || 0"></span></span>
                                            </template>
                                            <template x-if="!fiscalDay?.is_open">
                                                <span>Open a new fiscal day to start processing receipts</span>
                                            </template>
                                        </p>
                                    </div>
                                    <div class="flex space-x-3">
                                        <template x-if="!fiscalDay?.is_open">
                                            <button @click="openFiscalDay()" :disabled="loading" class="px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center space-x-2">
                                                <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span>Open Fiscal Day</span>
                                            </button>
                                        </template>
                                        <template x-if="fiscalDay?.is_open">
                                            <button @click="closeFiscalDay()" :disabled="loading" class="px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center space-x-2">
                                                <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span>Close Fiscal Day</span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Fiscal Counters -->
                            <template x-if="fiscalDay?.is_open && fiscalDay?.fiscal_day?.fiscal_counters?.length > 0">
                                <div class="p-4 bg-white border border-gray-200 rounded-lg">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Fiscal Counters</h3>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full text-sm">
                                            <thead>
                                                <tr class="bg-gray-50">
                                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Type</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Currency</th>
                                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Tax %</th>
                                                    <th class="px-4 py-2 text-right font-medium text-gray-600">Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="counter in fiscalDay.fiscal_day.fiscal_counters" :key="counter.fiscalCounterTaxID">
                                                    <tr class="border-t border-gray-100">
                                                        <td class="px-4 py-2" x-text="counter.fiscalCounterType"></td>
                                                        <td class="px-4 py-2" x-text="counter.fiscalCounterCurrency"></td>
                                                        <td class="px-4 py-2" x-text="counter.fiscalCounterTaxPercent + '%'"></td>
                                                        <td class="px-4 py-2 text-right font-medium" x-text="counter.fiscalCounterValue.toFixed(2)"></td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- Submit Receipt Tab -->
                <div x-show="activeTab === 'receipts'" x-cloak x-init="loadFiscalDay()">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Submit Receipt</h2>
                        <button @click="loadFiscalDay()" class="text-sm text-green-600 hover:text-green-700 flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Refresh</span>
                        </button>
                    </div>
                    
                    <!-- Debug: Show fiscal day data -->
                    <div class="mb-4 p-3 bg-gray-100 rounded text-xs overflow-x-auto">
                        <strong>Debug fiscalDay:</strong> <pre x-text="JSON.stringify(fiscalDay, null, 2)" class="whitespace-pre-wrap"></pre>
                    </div>

                    <template x-if="!fiscalDay || !fiscalDay.is_open">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please open a fiscal day first before submitting receipts.
                            <button @click="activeTab = 'fiscal'" class="ml-2 text-yellow-900 underline font-medium">Go to Fiscal Day</button>
                        </div>
                    </template>

                    <template x-if="fiscalDay?.is_open">
                        <div class="space-y-6">
                            <form @submit.prevent="submitReceipt()" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Type</label>
                                        <select x-model="receiptForm.receiptType" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="FiscalInvoice">Fiscal Invoice</option>
                                            <option value="CreditNote">Credit Note</option>
                                            <option value="DebitNote">Debit Note</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                                        <select x-model="receiptForm.receiptCurrency" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="USD">USD</option>
                                            <option value="ZWG">ZWG</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Invoice No</label>
                                        <input type="text" x-model="receiptForm.invoiceNo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="INV-001" required>
                                    </div>
                                </div>

                                <!-- Receipt Lines -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Receipt Lines</label>
                                    <template x-for="(line, index) in receiptForm.receiptLines" :key="index">
                                        <div class="flex items-center space-x-2 mb-2 p-3 bg-gray-50 rounded-lg">
                                            <input type="text" x-model="line.receiptLineName" placeholder="Product Name" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                                            <input type="number" x-model.number="line.receiptLineQuantity" placeholder="Qty" class="w-20 px-3 py-2 border border-gray-300 rounded-lg text-sm" min="1" required>
                                            <input type="number" x-model.number="line.receiptLinePrice" placeholder="Price" step="0.01" class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                                            <input type="text" x-model="line.receiptLineHSCode" placeholder="HS Code" class="w-28 px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                            <button type="button" @click="removeReceiptLine(index)" class="p-2 text-red-600 hover:bg-red-100 rounded-lg" x-show="receiptForm.receiptLines.length > 1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                    <button type="button" @click="addReceiptLine()" class="text-sm text-green-600 hover:text-green-700 font-medium flex items-center space-x-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        <span>Add Line</span>
                                    </button>
                                </div>

                                <!-- Payment -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                                        <select x-model="receiptForm.paymentMethod" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="Cash">Cash</option>
                                            <option value="Card">Card</option>
                                            <option value="MobileMoney">Mobile Money</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">VAT Tax Code</label>
                                        <select x-model="receiptForm.taxCode" @change="updateTaxPercent()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                            <option value="A">A - Standard Rated (15%)</option>
                                            <option value="B">B - Zero Rated (0%)</option>
                                            <option value="C">C - Exempt (0%)</option>
                                            <option value="D">D - Withholding VAT (15%)</option>
                                            <option value="E">E - Deemed Supplies</option>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1" x-text="getTaxCodeDescription()"></p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                    <div class="text-lg">
                                        <span class="text-gray-600">Total:</span>
                                        <span class="font-bold text-gray-900" x-text="receiptForm.receiptCurrency + ' ' + calculateTotal().toFixed(2)"></span>
                                    </div>
                                    <button type="submit" :disabled="loading" class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                        <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Submit Receipt</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Last Receipt Response -->
                            <template x-if="lastReceiptResponse">
                                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <h3 class="text-sm font-semibold text-green-800 mb-2">Last Receipt Submitted</h3>
                                    <div class="text-sm text-green-700">
                                        <p>Receipt ID: <strong x-text="lastReceiptResponse.data?.receiptID"></strong></p>
                                        <p>Server Date: <span x-text="lastReceiptResponse.data?.serverDate"></span></p>
                                        <p>Operation ID: <span x-text="lastReceiptResponse.data?.operationID"></span></p>
                                    </div>
                                    <a x-show="lastReceiptResponse.receipt_id" :href="'/zimra/receipts/' + lastReceiptResponse.receipt_id + '/pdf'" target="_blank" class="inline-flex items-center mt-3 px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Download PDF
                                    </a>
                                </div>
                            </template>

                            <!-- Receipts History -->
                            <div class="mt-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-gray-900">Receipt History</h3>
                                    <button @click="loadReceipts()" class="text-sm text-green-600 hover:text-green-800">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        Refresh
                                    </button>
                                </div>
                                
                                <div x-show="receipts.length === 0" class="text-center py-8 text-gray-500">
                                    <p>No receipts submitted yet</p>
                                </div>
                                
                                <div x-show="receipts.length > 0" class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tax</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <template x-for="receipt in receipts" :key="receipt.id">
                                                <tr>
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900" x-text="receipt.invoice_no"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="new Date(receipt.receipt_date).toLocaleDateString()"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900" x-text="receipt.receipt_currency + ' ' + parseFloat(receipt.receipt_total).toFixed(2)"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="receipt.tax_code + ' (' + receipt.tax_percent + '%)'"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="receipt.payment_method"></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <a :href="'/zimra/receipts/' + receipt.id + '/pdf'" target="_blank" class="text-green-600 hover:text-green-800">
                                                            <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            PDF
                                                        </a>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Submit File Tab -->
                <div x-show="activeTab === 'submitfile'" x-cloak>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Submit File to ZIMRA</h2>
                    
                    <template x-if="!config?.device_id">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please register a device first before submitting files.
                        </div>
                    </template>

                    <template x-if="config?.device_id">
                        <div class="space-y-6">
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm">
                                <strong>Note:</strong> SubmitFile is used to submit closed fiscal day data to ZIMRA. 
                                The fiscal day must be closed before submission. Receipts and footer will be automatically signed.
                            </div>

                            <form @submit.prevent="submitFile()" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">JSON Payload</label>
                                    <textarea x-model="submitFilePayload" rows="20" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 font-mono text-sm" placeholder="Enter JSON payload..."></textarea>
                                </div>
                                
                                <div class="flex items-center space-x-4">
                                    <button type="submit" :disabled="loading" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 flex items-center space-x-2">
                                        <svg x-show="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Submit File</span>
                                    </button>
                                    <button type="button" @click="loadSamplePayload()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                        Load Sample
                                    </button>
                                </div>
                            </form>

                            <!-- Submit File Response -->
                            <template x-if="submitFileResponse">
                                <div class="p-4 rounded-lg" :class="submitFileResponse.error ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
                                    <h3 class="text-sm font-semibold mb-2" :class="submitFileResponse.error ? 'text-red-800' : 'text-green-800'">
                                        <span x-text="submitFileResponse.error ? 'Submission Failed' : 'File Submitted Successfully'"></span>
                                    </h3>
                                    <pre class="text-xs overflow-auto max-h-64 p-2 bg-white rounded" x-text="JSON.stringify(submitFileResponse, null, 2)"></pre>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </main>

    <script>
        function zimraApp() {
            return {
                activeTab: 'config',
                loading: false,
                message: '',
                messageType: 'success',
                config: null,
                fiscalDay: null,
                lastReceiptResponse: null,
                
                configForm: {
                    base_url: 'https://fdmsapitest.zimra.co.zw',
                    device_model: 'Server',
                    device_version: 'v1'
                },
                
                registerForm: {
                    device_id: '',
                    serial_number: '',
                    activation_key: ''
                },

                registrationMode: 'new',
                
                uploadForm: {
                    device_id: '',
                    serial_number: '',
                    certificate: '',
                    private_key: ''
                },
                
                receiptForm: {
                    receiptType: 'FiscalInvoice',
                    receiptCurrency: 'USD',
                    invoiceNo: '',
                    receiptLines: [
                        { receiptLineName: '', receiptLineQuantity: 1, receiptLinePrice: 0, receiptLineHSCode: '' }
                    ],
                    paymentMethod: 'Cash',
                    taxCode: 'A',
                    taxPercent: 15
                },

                submitFilePayload: '',
                submitFileResponse: null,
                deviceStatus: null,
                receipts: [],
                
                async init() {
                    await this.loadConfig();
                    await this.loadFiscalDay();
                    this.generateInvoiceNo();
                    await this.loadReceipts();
                },
                
                async loadReceipts() {
                    try {
                        const res = await fetch('/zimra/receipts');
                        if (res.ok) {
                            this.receipts = await res.json();
                        }
                    } catch (e) {
                        console.error('Failed to load receipts:', e);
                    }
                },
                
                generateInvoiceNo() {
                    let counter = parseInt(localStorage.getItem('zimra_invoice_counter') || '0') + 1;
                    localStorage.setItem('zimra_invoice_counter', counter.toString());
                    this.receiptForm.invoiceNo = 'INV-' + counter.toString().padStart(3, '0');
                },
                
                async loadConfig() {
                    try {
                        const res = await fetch('/zimra/config');
                        if (res.ok) {
                            this.config = await res.json();
                            if (this.config) {
                                this.configForm.base_url = this.config.base_url || this.configForm.base_url;
                                this.configForm.device_model = this.config.device_model || this.configForm.device_model;
                                this.configForm.device_version = this.config.device_version || this.configForm.device_version;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load config:', e);
                    }
                },
                
                async loadFiscalDay() {
                    try {
                        const res = await fetch('/zimra/fiscal-day');
                        const data = await res.json();
                        console.log('Fiscal day response:', data);
                        this.fiscalDay = data;
                        if (data.is_open) {
                            this.showMessage('Fiscal day #' + (data.fiscal_day?.fiscal_day_no || '?') + ' is open', 'success');
                        }
                    } catch (e) {
                        console.error('Failed to load fiscal day:', e);
                        this.showMessage('Failed to load fiscal day: ' + e.message, 'error');
                    }
                },

                async getDeviceStatus() {
                    this.loading = true;
                    this.deviceStatus = null;
                    try {
                        const res = await fetch('/zimra/status');
                        const data = await res.json();
                        this.deviceStatus = data;
                        if (res.ok && !data.error) {
                            this.showMessage('Device status retrieved successfully!', 'success');
                        } else {
                            this.showMessage(data.error || 'Failed to get device status', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
                },
                
                async saveConfig() {
                    this.loading = true;
                    try {
                        const method = this.config ? 'PUT' : 'POST';
                        const url = this.config ? `/zimra/config/${this.config.id}` : '/zimra/config';
                        
                        const res = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.configForm)
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok) {
                            this.showMessage('Configuration saved successfully!', 'success');
                            await this.loadConfig();
                        } else {
                            this.showMessage(data.message || 'Failed to save configuration', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred', 'error');
                    }
                    this.loading = false;
                },
                
                async registerDevice() {
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.registerForm)
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok && !data.error) {
                            this.showMessage('Device registered successfully!', 'success');
                            await this.loadConfig();
                            await this.loadFiscalDay();
                            this.activeTab = 'fiscal';
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to register device', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred', 'error');
                    }
                    this.loading = false;
                },

                handleCertificateFile(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.uploadForm.certificate = e.target.result;
                        };
                        reader.readAsText(file);
                    }
                },

                handlePrivateKeyFile(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.uploadForm.private_key = e.target.result;
                        };
                        reader.readAsText(file);
                    }
                },

                async uploadCertificates() {
                    if (!this.uploadForm.device_id || !this.uploadForm.serial_number) {
                        this.showMessage('Please enter Device ID and Serial Number', 'error');
                        return;
                    }
                    if (!this.uploadForm.certificate || !this.uploadForm.private_key) {
                        this.showMessage('Please upload both certificate and private key files', 'error');
                        return;
                    }

                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/upload-certificates', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.uploadForm)
                        });

                        const data = await res.json();

                        if (res.ok && !data.error) {
                            this.showMessage('Certificates uploaded successfully!', 'success');
                            await this.loadConfig();
                            await this.loadFiscalDay();
                            this.activeTab = 'fiscal';
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to upload certificates', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
                },
                
                async openFiscalDay() {
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/open-day', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        console.log('Open fiscal day response:', data);
                        
                        if (res.ok && !data.error) {
                            this.showMessage('Fiscal day opened successfully!', 'success');
                            await this.loadFiscalDay();
                        } else {
                            // Show detailed error
                            const errorMsg = data.body?.detail || data.error || data.message || JSON.stringify(data);
                            this.showMessage('Failed: ' + errorMsg, 'error');
                            console.error('Open day error:', data);
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                        console.error('Open day exception:', e);
                    }
                    this.loading = false;
                },
                
                async closeFiscalDay() {
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/close-day', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok && !data.error) {
                            this.showMessage('Fiscal day closed successfully!', 'success');
                            await this.loadFiscalDay();
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to close fiscal day', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred', 'error');
                    }
                    this.loading = false;
                },
                
                calculateTotal() {
                    return this.receiptForm.receiptLines.reduce((sum, line) => {
                        return sum + (line.receiptLineQuantity * line.receiptLinePrice);
                    }, 0);
                },
                
                updateTaxPercent() {
                    const taxRates = {
                        'A': 15,  // Standard Rated
                        'B': 0,   // Zero Rated
                        'C': 0,   // Exempt
                        'D': 15,  // Withholding VAT
                        'E': 0    // Deemed Supplies (varies, default 0)
                    };
                    this.receiptForm.taxPercent = taxRates[this.receiptForm.taxCode] || 15;
                },
                
                getTaxCodeDescription() {
                    const descriptions = {
                        'A': 'Standard VAT - General goods, retail sales, services',
                        'B': 'Zero Rated - Exports, basic food items, approved supplies',
                        'C': 'VAT Exempt - Financial, educational, medical services',
                        'D': 'Withholding VAT - Customer is VAT withholding agent',
                        'E': 'Deemed Supplies - Special tax scenarios'
                    };
                    return descriptions[this.receiptForm.taxCode] || '';
                },
                
                getTaxID() {
                    const taxIDs = { 'A': 1, 'B': 2, 'C': 3, 'D': 4, 'E': 5 };
                    return taxIDs[this.receiptForm.taxCode] || 1;
                },
                
                addReceiptLine() {
                    this.receiptForm.receiptLines.push({
                        receiptLineName: '',
                        receiptLineQuantity: 1,
                        receiptLinePrice: 0,
                        receiptLineHSCode: ''
                    });
                },
                
                removeReceiptLine(index) {
                    this.receiptForm.receiptLines.splice(index, 1);
                },
                
                async submitReceipt() {
                    this.loading = true;
                    try {
                        const total = this.calculateTotal();
                        const taxAmount = total * (this.receiptForm.taxPercent / 100);
                        const counter = (this.fiscalDay?.fiscal_day?.receipt_counter || 0) + 1;
                        
                        const payload = {
                            receiptType: this.receiptForm.receiptType,
                            receiptCurrency: this.receiptForm.receiptCurrency,
                            receiptCounter: counter,
                            receiptGlobalNo: counter,
                            invoiceNo: this.receiptForm.invoiceNo,
                            receiptDate: new Date().toISOString().slice(0, 19),
                            receiptLinesTaxInclusive: true,
                            receiptLines: this.receiptForm.receiptLines.map((line, i) => ({
                                receiptLineType: 'Sale',
                                receiptLineNo: i + 1,
                                receiptLineHSCode: line.receiptLineHSCode || '00000000',
                                receiptLineName: line.receiptLineName,
                                receiptLinePrice: line.receiptLinePrice,
                                receiptLineQuantity: line.receiptLineQuantity,
                                receiptLineTotal: line.receiptLineQuantity * line.receiptLinePrice,
                                taxCode: this.receiptForm.taxCode,
                                taxPercent: this.receiptForm.taxPercent,
                                taxID: this.getTaxID()
                            })),
                            receiptTaxes: [{
                                taxCode: this.receiptForm.taxCode,
                                taxPercent: this.receiptForm.taxPercent,
                                taxID: this.getTaxID(),
                                taxAmount: taxAmount,
                                salesAmountWithTax: total
                            }],
                            receiptPayments: [{
                                moneyTypeCode: this.receiptForm.paymentMethod,
                                paymentAmount: total
                            }],
                            receiptTotal: total,
                            receiptPrintForm: 'Receipt48'
                        };
                        
                        const res = await fetch('/zimra/submit-receipt', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(payload)
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok && !data.error) {
                            this.showMessage('Receipt submitted successfully!', 'success');
                            this.lastReceiptResponse = data;
                            await this.loadFiscalDay();
                            await this.loadReceipts();
                            // Reset form and generate next invoice number
                            this.generateInvoiceNo();
                            this.receiptForm.receiptLines = [{ receiptLineName: '', receiptLineQuantity: 1, receiptLinePrice: 0, receiptLineHSCode: '' }];
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to submit receipt', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred', 'error');
                    }
                    this.loading = false;
                },

                loadSamplePayload() {
                    const now = new Date();
                    const isoDate = now.toISOString().slice(0, 19);
                    this.submitFilePayload = JSON.stringify({
                        "header": {
                            "fiscalDayNo": this.fiscalDay?.fiscal_day_no || 1,
                            "fiscalDayOpened": this.fiscalDay?.opened_at || isoDate,
                            "fileSequence": 1
                        },
                        "content": {
                            "receipts": []
                        },
                        "footer": {
                            "fiscalDayCounters": this.fiscalDay?.fiscal_counters || [],
                            "receiptCounter": this.fiscalDay?.receipt_counter || 0,
                            "fiscalDayClosed": this.fiscalDay?.closed_at || isoDate
                        }
                    }, null, 2);
                },

                async submitFile() {
                    if (!this.submitFilePayload.trim()) {
                        this.showMessage('Please enter a JSON payload', 'error');
                        return;
                    }

                    let payload;
                    try {
                        payload = JSON.parse(this.submitFilePayload);
                    } catch (e) {
                        this.showMessage('Invalid JSON payload', 'error');
                        return;
                    }

                    this.loading = true;
                    this.submitFileResponse = null;

                    try {
                        const res = await fetch('/zimra/submit-file', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();
                        this.submitFileResponse = data;

                        if (res.ok && !data.error) {
                            this.showMessage('File submitted successfully!', 'success');
                        } else {
                            this.showMessage(data.body?.detail || data.message || 'Failed to submit file', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
                },
                
                showMessage(msg, type) {
                    this.message = msg;
                    this.messageType = type;
                    setTimeout(() => { this.message = ''; }, 5000);
                }
            };
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
