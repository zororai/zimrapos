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
                <div class="flex items-center space-x-4">
                    <!-- Active Company Badge -->
                    <template x-if="config">
                        <div class="flex items-center space-x-2 px-3 py-1.5 bg-green-100 border border-green-300 rounded-lg">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="text-sm font-semibold text-green-700" x-text="config.company_name"></span>
                            <span x-show="config.device_id" class="text-xs text-green-600" x-text="'(#' + config.device_id + ')'"></span>
                        </div>
                    </template>
                    
                    <!-- Company Selector -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm font-medium text-gray-600">Switch:</label>
                        <select x-model="selectedConfigId" @change="switchCompany()" class="px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">-- Select Company --</option>
                            <template x-for="c in allConfigs" :key="c.id">
                                <option :value="c.id" x-text="c.company_name + (c.device_id ? ' (#' + c.device_id + ')' : ' (Not Registered)')"></option>
                            </template>
                        </select>
                    </div>
                    <a href="/" class="text-sm text-gray-600 hover:text-gray-900 flex items-center space-x-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        <span>Back to Home</span>
                    </a>
                </div>
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
                    <button @click="activeTab = 'fiscal'; syncFiscalDayWithFDMS()" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'fiscal' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Fiscal Day
                    </button>
                    <button @click="activeTab = 'receipts'" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'receipts' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Submit Receipt
                    </button>
                    <button @click="activeTab = 'creditnotes'; loadSales()" class="px-6 py-4 text-sm font-medium border-b-2 transition-colors" :class="activeTab === 'creditnotes' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Credit Notes
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
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name *</label>
                                <input type="text" x-model="configForm.company_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="My Company Ltd" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company TIN</label>
                                <input type="text" x-model="configForm.company_tin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="1234567890">
                            </div>
                        </div>
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
                                <span>Add New Company</span>
                            </button>
                        </div>
                    </form>

                    <!-- All Registered Companies -->
                    <template x-if="allConfigs.length > 0">
                        <div class="mt-8">
                            <h3 class="text-sm font-semibold text-gray-700 mb-3">All Registered Companies</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm border border-gray-200 rounded-lg">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left font-medium text-gray-600">Company Name</th>
                                            <th class="px-4 py-2 text-left font-medium text-gray-600">TIN</th>
                                            <th class="px-4 py-2 text-left font-medium text-gray-600">Device ID</th>
                                            <th class="px-4 py-2 text-left font-medium text-gray-600">Status</th>
                                            <th class="px-4 py-2 text-center font-medium text-gray-600">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="c in allConfigs" :key="c.id">
                                            <tr class="border-t border-gray-100" :class="c.is_active ? 'bg-green-50' : ''">
                                                <td class="px-4 py-2 font-medium" x-text="c.company_name"></td>
                                                <td class="px-4 py-2 text-gray-600" x-text="c.company_tin || '-'"></td>
                                                <td class="px-4 py-2" x-text="c.device_id || 'Not registered'"></td>
                                                <td class="px-4 py-2">
                                                    <span x-show="c.is_active" class="px-2 py-0.5 bg-green-100 text-green-700 text-xs font-medium rounded">Active</span>
                                                    <span x-show="!c.is_active" class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded">Inactive</span>
                                                </td>
                                                <td class="px-4 py-2 text-center">
                                                    <button x-show="!c.is_active" @click="selectedConfigId = c.id; switchCompany()" class="px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">
                                                        Select
                                                    </button>
                                                    <span x-show="c.is_active" class="text-xs text-green-600 font-medium">Current</span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <!-- Current Active Configuration Details -->
                    <template x-if="config">
                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-green-700">Active Company Details</h3>
                                <button @click="deleteConfig()" :disabled="loading" class="px-3 py-1 bg-red-600 text-white text-sm font-medium rounded hover:bg-red-700 disabled:opacity-50 flex items-center space-x-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <span>Delete</span>
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div><span class="text-gray-500">Company:</span> <span class="font-medium text-green-700" x-text="config.company_name"></span></div>
                                <div><span class="text-gray-500">TIN:</span> <span class="font-medium" x-text="config.company_tin || 'N/A'"></span></div>
                                <div><span class="text-gray-500">Device ID:</span> <span class="font-medium" x-text="config.device_id || 'Not registered'"></span></div>
                                <div><span class="text-gray-500">Base URL:</span> <span class="font-medium" x-text="config.base_url"></span></div>
                                <div><span class="text-gray-500">Model:</span> <span class="font-medium" x-text="config.device_model"></span></div>
                                <div><span class="text-gray-500">Version:</span> <span class="font-medium" x-text="config.device_version"></span></div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Device Registration Tab -->
                <div x-show="activeTab === 'device'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Device Registration</h2>
                        <template x-if="config">
                            <div class="flex items-center space-x-2 px-3 py-1 bg-blue-100 border border-blue-300 rounded-lg">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-700" x-text="'Registering for: ' + config.company_name"></span>
                            </div>
                        </template>
                    </div>
                    
                    <template x-if="!config">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please add a company first in Configuration tab, then select it from the dropdown above.
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
                                    <div class="flex space-x-2">
                                        <button @click="getDeviceConfig()" :disabled="loading" class="px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 disabled:opacity-50 flex items-center space-x-1">
                                            <svg x-show="loading" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span>Get Config</span>
                                        </button>
                                        <button @click="getDeviceStatus()" :disabled="loading" class="px-3 py-1 bg-green-600 text-white text-sm font-medium rounded hover:bg-green-700 disabled:opacity-50 flex items-center space-x-1">
                                            <svg x-show="loading" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span>Get Status</span>
                                        </button>
                                        <button @click="clearDeviceRegistration()" :disabled="loading" class="px-3 py-1 bg-red-600 text-white text-sm font-medium rounded hover:bg-red-700 disabled:opacity-50 flex items-center space-x-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <span>Clear Registration</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3 text-sm text-green-700">
                                    <div><span class="text-green-600">Device ID:</span> <strong x-text="config.device_id"></strong></div>
                                    <div><span class="text-green-600">Serial Number:</span> <strong x-text="config.serial_number || 'N/A'"></strong></div>
                                    <div><span class="text-green-600">Certificate:</span> <strong x-text="config.certificate ? '✓ Stored' : '✗ Missing'"></strong></div>
                                    <div><span class="text-green-600">QR URL:</span> <strong x-text="config.qr_url ? '✓ Configured' : '✗ Click Get Config'"></strong></div>
                                </div>
                            </div>

                            <!-- Device Status Response -->
                            <template x-if="deviceStatus">
                                <div class="p-4 border rounded-lg" :class="deviceStatus.fiscalDayStatus === 'FiscalDayCloseFailed' ? 'bg-red-50 border-red-200' : 'bg-blue-50 border-blue-200'">
                                    <h4 class="font-semibold mb-2" :class="deviceStatus.fiscalDayStatus === 'FiscalDayCloseFailed' ? 'text-red-800' : 'text-blue-800'">Device Status (GetStatus Response)</h4>
                                    <pre class="text-xs p-3 rounded overflow-x-auto" :class="deviceStatus.fiscalDayStatus === 'FiscalDayCloseFailed' ? 'bg-red-100 text-red-900' : 'bg-blue-100 text-blue-900'" x-text="JSON.stringify(deviceStatus, null, 2)"></pre>
                                    
                                    <!-- Show error alert and Force Close button when fiscal day close failed -->
                                    <template x-if="deviceStatus.fiscalDayStatus === 'FiscalDayCloseFailed'">
                                        <div class="mt-4 space-y-3">
                                            <div class="p-3 bg-red-100 border border-red-300 rounded-lg">
                                                <p class="text-red-800 font-medium">Fiscal Day Close Failed</p>
                                                <p class="text-red-700 text-sm mt-1">Error: <span x-text="deviceStatus.fiscalDayClosingErrorCode"></span></p>
                                            </div>
                                            <div class="flex space-x-2">
                                                <button @click="closeFiscalDay()" :disabled="loading" class="px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center space-x-2">
                                                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Retry Close Day</span>
                                                </button>
                                                <button @click="forceCloseFiscalDay()" :disabled="loading" class="px-4 py-2 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center space-x-2" title="Force close locally without ZIMRA API">
                                                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Force Close (Local)</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
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
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Fiscal Day Management</h2>
                        <template x-if="config">
                            <div class="flex items-center space-x-2 px-3 py-1 bg-blue-100 border border-blue-300 rounded-lg">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="text-sm font-medium text-blue-700" x-text="'Using: ' + config.company_name"></span>
                            </div>
                        </template>
                    </div>
                    
                    <template x-if="!config?.device_id">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please register a device first before managing fiscal days. Select a company from the dropdown above.
                        </div>
                    </template>

                    <template x-if="config?.device_id">
                        <div class="space-y-6">
                            <!-- FDMS Status Display -->
                            <template x-if="deviceStatus">
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h4 class="font-semibold text-blue-800 mb-2">FDMS Status</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                        <div>
                                            <span class="text-blue-600">Status:</span>
                                            <span class="font-medium" :class="deviceStatus.fiscalDayStatus === 'FiscalDayOpened' ? 'text-green-700' : 'text-gray-700'" x-text="deviceStatus.fiscalDayStatus"></span>
                                        </div>
                                        <div>
                                            <span class="text-blue-600">Fiscal Day #:</span>
                                            <span class="font-medium text-gray-700" x-text="deviceStatus.lastFiscalDayNo"></span>
                                        </div>
                                        <div>
                                            <span class="text-blue-600">Last Receipt #:</span>
                                            <span class="font-medium text-gray-700" x-text="deviceStatus.lastReceiptGlobalNo"></span>
                                        </div>
                                        <div>
                                            <span class="text-blue-600">Operation ID:</span>
                                            <span class="font-medium text-gray-700 text-xs" x-text="deviceStatus.operationID"></span>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Current Status -->
                            <div class="p-6 rounded-lg" :class="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened' ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-lg font-semibold" :class="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened' ? 'text-green-800' : 'text-gray-800'" x-text="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened' ? 'Fiscal Day #' + deviceStatus.lastFiscalDayNo + ' is Open' : 'No Open Fiscal Day'"></h3>
                                        <p class="text-sm mt-1" :class="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened' ? 'text-green-600' : 'text-gray-600'">
                                            <template x-if="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened'">
                                                <span>Opened at: <span x-text="fiscalDay?.fiscal_day?.opened_at ? new Date(fiscalDay.fiscal_day.opened_at).toLocaleString() : 'N/A'"></span> | Receipts: <span x-text="deviceStatus?.lastReceiptGlobalNo || 0"></span></span>
                                            </template>
                                            <template x-if="deviceStatus?.fiscalDayStatus !== 'FiscalDayOpened'">
                                                <span>Open a new fiscal day to start processing receipts</span>
                                            </template>
                                        </p>
                                    </div>
                                    <div class="flex space-x-3">
                                        <!-- Show Open Day button when FDMS status is NOT FiscalDayOpened -->
                                        <template x-if="deviceStatus?.fiscalDayStatus !== 'FiscalDayOpened'">
                                            <button @click="openFiscalDay()" :disabled="loading" class="px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 disabled:opacity-50 flex items-center space-x-2">
                                                <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                <span>Open Fiscal Day</span>
                                            </button>
                                        </template>
                                        <!-- Show Close Day button when FDMS status IS FiscalDayOpened -->
                                        <template x-if="deviceStatus?.fiscalDayStatus === 'FiscalDayOpened'">
                                            <div class="flex space-x-2">
                                                <button @click="closeFiscalDay()" :disabled="loading" class="px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 flex items-center space-x-2">
                                                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Close Fiscal Day</span>
                                                </button>
                                                <button x-show="closeDayFailed" @click="forceCloseFiscalDay()" :disabled="loading" class="px-4 py-2 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center space-x-2" title="Force close locally without ZIMRA API">
                                                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                    </svg>
                                                    <span>Force Close (Local)</span>
                                                </button>
                                            </div>
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
                        <div class="flex items-center space-x-4">
                            <h2 class="text-lg font-semibold text-gray-900">Submit Receipt</h2>
                            <template x-if="config">
                                <div class="flex items-center space-x-2 px-3 py-1 bg-blue-100 border border-blue-300 rounded-lg">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <span class="text-sm font-medium text-blue-700" x-text="'Using: ' + config.company_name"></span>
                                </div>
                            </template>
                        </div>
                        <button @click="loadFiscalDay()" class="text-sm text-green-600 hover:text-green-700 flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Refresh</span>
                        </button>
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
                                            <option value="FiscalReceipt">Fiscal Receipt (Non-VAT)</option>
                                            <option value="FiscalInvoice">Fiscal Invoice (VAT)</option>
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

                                <!-- Buyer Data Section (Collapsible) -->
                                <div class="border border-gray-200 rounded-lg">
                                    <button type="button" @click="showBuyerData = !showBuyerData" class="w-full flex items-center justify-between p-4 bg-blue-50 hover:bg-blue-100 rounded-t-lg transition-colors">
                                        <div class="flex items-center space-x-2">
                                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            <span class="text-sm font-medium text-gray-900">Customer Details (Optional)</span>
                                            <span class="text-xs text-gray-500">- For B2B / Fiscal Invoices</span>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-500 transition-transform" :class="showBuyerData ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    
                                    <div x-show="showBuyerData" x-collapse class="p-4 space-y-4 bg-white">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                                                <input type="text" x-model="receiptForm.buyerData.buyerRegisterName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="ABC Company Ltd">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Trading Name</label>
                                                <input type="text" x-model="receiptForm.buyerData.buyerTradeName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="ABC Store">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">VAT Number</label>
                                                <input type="text" x-model="receiptForm.buyerData.vatNumber" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="12345678" maxlength="8">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">TIN</label>
                                                <input type="text" x-model="receiptForm.buyerData.buyerTIN" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="1234567890" maxlength="10">
                                            </div>
                                        </div>
                                        
                                        <div class="border-t border-gray-200 pt-4">
                                            <h4 class="text-sm font-medium text-gray-700 mb-3">Contact Information</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                                    <input type="tel" x-model="receiptForm.buyerData.buyerContacts.phoneNo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="+263712345678">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                                    <input type="email" x-model="receiptForm.buyerData.buyerContacts.email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="customer@example.com">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="border-t border-gray-200 pt-4">
                                            <h4 class="text-sm font-medium text-gray-700 mb-3">Address</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">House/Building No</label>
                                                    <input type="text" x-model="receiptForm.buyerData.buyerAddress.houseNo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="123">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                                    <input type="text" x-model="receiptForm.buyerData.buyerAddress.street" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Main Street">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                                                    <input type="text" x-model="receiptForm.buyerData.buyerAddress.district" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="CBD">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                                    <input type="text" x-model="receiptForm.buyerData.buyerAddress.city" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Harare">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-1">Province</label>
                                                    <input type="text" x-model="receiptForm.buyerData.buyerAddress.province" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" placeholder="Harare">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                            <div class="flex items-start space-x-2">
                                                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                                </svg>
                                                <div class="text-sm text-blue-800">
                                                    <p class="font-medium">Customer details are optional</p>
                                                    <p class="text-xs mt-1">Fill in customer information for B2B transactions or when issuing Fiscal Invoices. Leave empty for simple retail receipts.</p>
                                                </div>
                                            </div>
                                        </div>
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

                                <!-- VAT Status Banner -->
                                <div x-show="taxConfig.message" class="p-4 rounded-lg border" :class="taxConfig.isVatRegistered ? 'bg-green-50 border-green-200 text-green-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800'">
                                    <div class="flex items-start space-x-2">
                                        <svg class="w-5 h-5 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                        <div>
                                            <p class="font-medium" x-text="taxConfig.message"></p>
                                            <p class="text-sm mt-1" x-show="!taxConfig.isVatRegistered">Only 0% tax is available for this device.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment & Tax -->
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
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Tax Rate
                                            <span x-show="!taxConfig.isVatRegistered" class="text-xs text-yellow-600">(Auto-set to 0%)</span>
                                        </label>
                                        <select x-model.number="receiptForm.taxPercent" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" :disabled="!taxConfig.isVatRegistered">
                                            <template x-for="tax in taxConfig.applicableTaxes" :key="tax.taxID">
                                                <option :value="tax.taxPercent" x-text="`${tax.taxName} (${tax.taxPercent}%)`"></option>
                                            </template>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1" x-show="taxConfig.isVatRegistered">Select tax rate from FDMS configuration</p>
                                        <p class="text-xs text-yellow-600 mt-1" x-show="!taxConfig.isVatRegistered">Tax field is disabled - device not VAT registered</p>
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

                            <!-- Receipts History (Always visible) -->
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
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <template x-for="receipt in receipts" :key="receipt.id">
                                                <tr :class="{ 'bg-red-50': receipt.has_red_errors, 'bg-yellow-50': receipt.has_gray_errors && !receipt.has_red_errors }">
                                                    <td class="px-4 py-3 text-sm font-medium text-gray-900" x-text="receipt.invoice_no"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="new Date(receipt.receipt_date).toLocaleDateString()"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-900" x-text="receipt.receipt_currency + ' ' + parseFloat(receipt.receipt_total).toFixed(2)"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="receipt.tax_code + ' (' + receipt.tax_percent + '%)'"></td>
                                                    <td class="px-4 py-3 text-sm text-gray-500" x-text="receipt.payment_method"></td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <template x-if="receipt.has_red_errors">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                RED Error
                                                            </span>
                                                        </template>
                                                        <template x-if="receipt.has_gray_errors && !receipt.has_red_errors">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                GRAY Warning
                                                            </span>
                                                        </template>
                                                        <template x-if="receipt.is_valid && !receipt.has_red_errors && !receipt.has_gray_errors">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                Valid
                                                            </span>
                                                        </template>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <div class="flex items-center space-x-2">
                                                            <a :href="'/zimra/receipts/' + receipt.id + '/pdf'" target="_blank" class="text-green-600 hover:text-green-800">
                                                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                                </svg>
                                                            </a>
                                                            <template x-if="receipt.validation_errors && receipt.validation_errors.length > 0">
                                                                <button @click="showValidationErrors(receipt)" class="text-gray-500 hover:text-gray-700">
                                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                    </svg>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                    
                                    <!-- Validation Errors Modal -->
                                    <div x-show="selectedReceiptErrors" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="selectedReceiptErrors = null">
                                        <div class="flex items-center justify-center min-h-screen px-4">
                                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="selectedReceiptErrors = null"></div>
                                            <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full p-6">
                                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Validation Errors</h3>
                                                <div class="space-y-3 max-h-96 overflow-y-auto">
                                                    <template x-for="(error, index) in selectedReceiptErrors" :key="index">
                                                        <div :class="error.validationErrorColor === 'Red' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-yellow-50 border-yellow-200 text-yellow-800'" class="p-3 border rounded-lg">
                                                            <div class="font-medium" x-text="error.validationErrorCode || 'Unknown Error'"></div>
                                                            <div class="text-sm mt-1" x-text="error.errorMessage"></div>
                                                            <div x-show="error.field" class="text-xs mt-1 opacity-75">Field: <span x-text="error.field"></span></div>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button @click="selectedReceiptErrors = null" class="mt-4 w-full px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                                                    Close
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Credit Notes Tab -->
                <div x-show="activeTab === 'creditnotes'" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Create Credit Note</h2>
                        <button @click="loadSales()" class="text-sm text-green-600 hover:text-green-700 flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Refresh Sales</span>
                        </button>
                    </div>

                    <template x-if="!config?.device_id">
                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-800">
                            Please register a device first before creating credit notes.
                        </div>
                    </template>

                    <template x-if="config?.device_id">
                        <div class="space-y-6">
                            <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-800 text-sm">
                                <strong>Credit Notes:</strong> Create a credit note to reverse or partially refund a previous sale. 
                                The credit note will be automatically submitted to ZIMRA and linked to the original receipt.
                            </div>

                            <form @submit.prevent="submitCreditNote()" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Original Sale *</label>
                                        <select x-model="creditNoteForm.sale_id" @change="loadSaleDetails()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" required>
                                            <option value="">-- Select a sale --</option>
                                            <template x-for="sale in sales" :key="sale.id">
                                                <option :value="sale.id" x-text="`${sale.id} - ${sale.receipt_currency || 'USD'} ${parseFloat(sale.receipt_total || 0).toFixed(2)} (${new Date(sale.created_at).toLocaleDateString()})`"></option>
                                            </template>
                                        </select>
                                        <p class="text-xs text-gray-500 mt-1">Select the original sale to credit</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                                        <input type="text" x-model="creditNoteForm.currency" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50" readonly>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Credit Note *</label>
                                    <textarea x-model="creditNoteForm.reason" rows="3" minlength="10" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500" placeholder="e.g., Customer return - damaged goods (minimum 10 characters)" required></textarea>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <span x-show="creditNoteForm.reason.length < 10" class="text-red-600 font-medium">
                                            ⚠️ Minimum 10 characters required (<span x-text="creditNoteForm.reason.length"></span>/10)
                                        </span>
                                        <span x-show="creditNoteForm.reason.length >= 10" class="text-green-600">
                                            ✓ Valid reason (<span x-text="creditNoteForm.reason.length"></span> characters)
                                        </span>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Products to Credit</label>
                                    <template x-if="selectedSale">
                                        <div class="space-y-2">
                                            <template x-for="(line, index) in selectedSale.receipt_lines" :key="index">
                                                <div class="p-3 bg-gray-50 rounded-lg">
                                                    <div class="flex items-center space-x-3">
                                                        <input type="checkbox" :id="'line-' + index" x-model="creditNoteForm.selectedLines[index]" class="w-4 h-4 text-green-600">
                                                        <label :for="'line-' + index" class="flex-1 text-sm">
                                                            <span class="font-medium" x-text="line.receiptLineName"></span>
                                                            <span class="text-gray-600"> - Original Qty: </span><span x-text="line.receiptLineQuantity"></span>
                                                            <span class="text-gray-600"> × </span><span x-text="creditNoteForm.currency + ' ' + parseFloat(line.receiptLinePrice).toFixed(2)"></span>
                                                        </label>
                                                    </div>
                                                    <div x-show="creditNoteForm.selectedLines[index]" class="mt-2 ml-7 flex items-center space-x-3">
                                                        <label class="text-xs text-gray-600">Credit Qty:</label>
                                                        <input 
                                                            type="number" 
                                                            x-model.number="creditNoteForm.lineQuantities[index]"
                                                            :max="parseFloat(line.receiptLineQuantity)"
                                                            min="0.01"
                                                            step="0.01"
                                                            class="w-24 px-2 py-1 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                                            placeholder="Qty">
                                                        <span class="text-xs text-gray-500">
                                                            (Max: <span x-text="line.receiptLineQuantity"></span>)
                                                        </span>
                                                        <span class="text-sm font-medium text-gray-700">
                                                            = <span x-text="creditNoteForm.currency + ' ' + calculateLineCredit(index).toFixed(2)"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="!selectedSale">
                                        <p class="text-sm text-gray-500 italic">Select a sale to see products</p>
                                    </template>
                                </div>

                                <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                                    <div class="text-lg">
                                        <span class="text-gray-600">Credit Amount:</span>
                                        <span class="font-bold text-red-600" x-text="creditNoteForm.currency + ' -' + calculateCreditTotal().toFixed(2)"></span>
                                    </div>
                                    <button type="submit" :disabled="loading || !creditNoteForm.sale_id || calculateCreditTotal() === 0" class="px-6 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2">
                                        <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span>Submit Credit Note to ZIMRA</span>
                                    </button>
                                </div>
                            </form>

                            <!-- Credit Note Response -->
                            <template x-if="creditNoteResponse">
                                <div class="p-4 rounded-lg" :class="creditNoteResponse.error ? 'bg-red-50 border border-red-200' : 'bg-green-50 border border-green-200'">
                                    <h3 class="text-sm font-semibold mb-2" :class="creditNoteResponse.error ? 'text-red-800' : 'text-green-800'">
                                        <span x-text="creditNoteResponse.error ? 'Credit Note Failed' : 'Credit Note Submitted Successfully'"></span>
                                    </h3>
                                    <div class="text-sm" :class="creditNoteResponse.error ? 'text-red-700' : 'text-green-700'">
                                        <template x-if="!creditNoteResponse.error">
                                            <div>
                                                <p>Receipt ID: <strong x-text="creditNoteResponse.data?.receiptID"></strong></p>
                                                <p>Server Date: <span x-text="creditNoteResponse.data?.serverDate"></span></p>
                                                <p>Operation ID: <span x-text="creditNoteResponse.data?.operationID"></span></p>
                                            </div>
                                        </template>
                                        <template x-if="creditNoteResponse.error">
                                            <pre class="text-xs overflow-auto max-h-64 p-2 bg-white rounded mt-2" x-text="JSON.stringify(creditNoteResponse, null, 2)"></pre>
                                        </template>
                                    </div>
                                </div>
                            </template>
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
                allConfigs: [],
                selectedConfigId: '',
                fiscalDay: null,
                closeDayFailed: false,
                lastReceiptResponse: null,
                selectedReceiptErrors: null,
                
                configForm: {
                    company_name: '',
                    company_tin: '',
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
                    taxPercent: 15,
                    buyerData: {
                        buyerRegisterName: '',
                        buyerTradeName: '',
                        vatNumber: '',
                        buyerTIN: '',
                        buyerContacts: {
                            phoneNo: '',
                            email: ''
                        },
                        buyerAddress: {
                            province: '',
                            city: '',
                            street: '',
                            houseNo: '',
                            district: ''
                        }
                    }
                },
                
                showBuyerData: false,

                submitFilePayload: '',
                submitFileResponse: null,
                deviceStatus: null,
                receipts: [],
                
                // Credit Note form
                sales: [],
                selectedSale: null,
                creditNoteForm: {
                    sale_id: '',
                    currency: 'USD',
                    reason: '',
                    selectedLines: [],
                    lineQuantities: []
                },
                creditNoteResponse: null,
                
                // Tax configuration from FDMS
                taxConfig: {
                    isVatRegistered: false,
                    vatNumber: null,
                    applicableTaxes: [],
                    message: ''
                },
                
                async init() {
                    await this.loadAllConfigs();
                    await this.loadConfig();
                    await this.loadFiscalDay();
                    await this.loadNextInvoiceNo();
                    await this.loadReceipts();
                    await this.loadTaxConfig();
                },

                async loadAllConfigs() {
                    try {
                        const res = await fetch('/zimra/configs');
                        if (res.ok) {
                            this.allConfigs = await res.json();
                            // Set selected config to active one
                            const activeConfig = this.allConfigs.find(c => c.is_active);
                            if (activeConfig) {
                                this.selectedConfigId = activeConfig.id;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to load configs:', e);
                    }
                },

                async switchCompany() {
                    if (!this.selectedConfigId) {
                        this.config = null;
                        this.fiscalDay = null;
                        this.receipts = [];
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        // Activate the selected config
                        const res = await fetch(`/zimra/config/${this.selectedConfigId}/activate`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (res.ok) {
                            await this.loadConfig();
                            await this.loadFiscalDay();
                            await this.loadReceipts();
                            await this.loadNextInvoiceNo();
                            this.showMessage('Switched to ' + (this.config?.company_name || 'selected company'), 'success');
                        }
                    } catch (e) {
                        this.showMessage('Failed to switch company: ' + e.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },
                
                async loadReceipts() {
                    try {
                        const res = await fetch('/zimra/receipts');
                        if (res.ok) {
                            const data = await res.json();
                            this.receipts = data.receipts || data || [];
                        }
                    } catch (e) {
                        console.error('Failed to load receipts:', e);
                    }
                },

                showValidationErrors(receipt) {
                    this.selectedReceiptErrors = receipt.validation_errors || [];
                },
                
                async loadNextInvoiceNo() {
                    try {
                        const res = await fetch('/zimra/next-invoice-no');
                        if (res.ok) {
                            const data = await res.json();
                            this.receiptForm.invoiceNo = data.invoice_no;
                        }
                    } catch (e) {
                        console.error('Failed to load next invoice number:', e);
                        this.receiptForm.invoiceNo = 'INV-001';
                    }
                },
                
                async loadTaxConfig() {
                    try {
                        const res = await fetch('/zimra/tax-config');
                        if (res.ok) {
                            const data = await res.json();
                            this.taxConfig = data;
                            
                            // Auto-set tax to first available tax (usually 0% for non-VAT)
                            if (data.applicableTaxes && data.applicableTaxes.length > 0) {
                                const firstTax = data.applicableTaxes[0];
                                this.receiptForm.taxPercent = firstTax.taxPercent;
                                // Don't set taxCode - backend will handle it conditionally
                            }
                            
                            console.log('Tax config loaded:', data);
                        }
                    } catch (e) {
                        console.error('Failed to load tax config:', e);
                    }
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

                async getDeviceConfig() {
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/device-config');
                        const data = await res.json();
                        if (res.ok && !data.error) {
                            this.showMessage('Config retrieved! QR URL: ' + (data.qrUrl || 'Not available'), 'success');
                            // Reload config to get updated qr_url
                            await this.loadConfig();
                        } else {
                            this.showMessage(data.error || 'Failed to get device config', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
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

                async syncFiscalDayWithFDMS() {
                    if (!this.config?.device_id) {
                        return; // No device registered yet
                    }
                    
                    this.loading = true;
                    try {
                        // Call sync endpoint which gets FDMS status and updates database
                        const res = await fetch('/zimra/sync-fiscal-day', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        console.log('Sync fiscal day response:', data);
                        
                        if (res.ok && !data.error) {
                            // Update local state with synced data
                            this.deviceStatus = data.fdms_status;
                            this.fiscalDay = data.fiscal_day;
                            
                            if (data.fdms_status?.fiscalDayStatus === 'FiscalDayOpened') {
                                this.showMessage('Fiscal Day #' + data.fdms_status.lastFiscalDayNo + ' is Open (synced with FDMS)', 'success');
                            } else {
                                this.showMessage('Fiscal Day Status: ' + (data.fdms_status?.fiscalDayStatus || 'Unknown'), 'info');
                            }
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to sync with FDMS', 'error');
                        }
                    } catch (e) {
                        console.error('Sync fiscal day error:', e);
                        this.showMessage('Failed to sync with FDMS: ' + e.message, 'error');
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
                            this.showMessage('Company added successfully!', 'success');
                            await this.loadAllConfigs();
                            await this.loadConfig();
                            // Clear form for next entry
                            this.configForm.company_name = '';
                            this.configForm.company_tin = '';
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
                            await this.loadAllConfigs();
                            await this.loadConfig();
                            await this.loadFiscalDay();
                            this.activeTab = 'fiscal';
                        } else {
                            // Build detailed error message
                            let errorMsg = data.message || 'Failed to register device';
                            if (data.errorCode) {
                                errorMsg = `[${data.errorCode}] ${errorMsg}`;
                            }
                            this.showMessage(errorMsg, 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
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
                            await this.loadAllConfigs();
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
                            this.closeDayFailed = false;
                            await this.loadFiscalDay();
                        } else {
                            this.closeDayFailed = true;
                            const errorMsg = data.body?.fiscalDayClosingErrorCode || data.body?.detail || data.error || data.message || 'Failed to close fiscal day';
                            this.showMessage('Close failed: ' + errorMsg + '. You can use Force Close to close locally.', 'error');
                        }
                    } catch (e) {
                        this.closeDayFailed = true;
                        this.showMessage('An error occurred. You can use Force Close to close locally.', 'error');
                    }
                    this.loading = false;
                },
                
                async forceCloseFiscalDay() {
                    if (!confirm('Force close will mark the fiscal day as closed locally WITHOUT notifying ZIMRA. This should only be used when ZIMRA API is unavailable. Continue?')) {
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/force-close-day', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok && !data.error) {
                            this.showMessage('Fiscal day force closed locally. Note: ZIMRA was not notified.', 'warning');
                            this.closeDayFailed = false;
                            this.deviceStatus = null;
                            await this.loadFiscalDay();
                            await this.getDeviceStatus();
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to force close fiscal day', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
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
                        // Step 1: Call getConfig from FDMS first to ensure tax config is fresh
                        const configRes = await fetch('/zimra/device-config', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        if (!configRes.ok) {
                            const configErr = await configRes.json();
                            this.showMessage(configErr.error || 'Failed to fetch FDMS config', 'error');
                            this.loading = false;
                            return;
                        }
                        
                        const fdmsConfig = await configRes.json();
                        console.log('FDMS Config fetched:', fdmsConfig);
                        
                        // Step 2: Proceed with receipt submission
                        const total = this.calculateTotal();
                        const taxAmount = total * (this.receiptForm.taxPercent / 100);
                        const counter = (this.fiscalDay?.fiscal_day?.receipt_counter || 0) + 1;
                        
                        const payload = {
                            // receiptType auto-determined by backend based on VAT registration
                            receiptCurrency: this.receiptForm.receiptCurrency,
                            receiptCounter: counter,
                            receiptGlobalNo: counter,
                            invoiceNo: this.receiptForm.invoiceNo,
                            receiptDate: new Date().toISOString().slice(0, 19),
                            receiptLinesTaxInclusive: true,
                            receiptLines: this.receiptForm.receiptLines.map((line, i) => {
                                console.log('Line HS Code:', line.receiptLineHSCode); // Debug
                                return {
                                    receiptLineType: 'Sale',
                                    receiptLineNo: i + 1,
                                    receiptLineHSCode: line.receiptLineHSCode || '00000000',
                                    receiptLineName: line.receiptLineName,
                                    receiptLinePrice: line.receiptLinePrice,
                                    receiptLineQuantity: line.receiptLineQuantity,
                                    receiptLineTotal: line.receiptLineQuantity * line.receiptLinePrice,
                                    taxPercent: this.receiptForm.taxPercent,
                                    taxID: this.getTaxID()
                                };
                            }),
                            receiptTaxes: [{
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
                        
                        // Add buyer data if any field is filled
                        const buyerData = this.receiptForm.buyerData;
                        const hasBuyerData = buyerData.buyerRegisterName || buyerData.buyerTradeName || 
                                           buyerData.vatNumber || buyerData.buyerTIN ||
                                           buyerData.buyerContacts.phoneNo || buyerData.buyerContacts.email ||
                                           buyerData.buyerAddress.houseNo || buyerData.buyerAddress.street ||
                                           buyerData.buyerAddress.city || buyerData.buyerAddress.province;
                        
                        if (hasBuyerData) {
                            payload.buyerData = {
                                ...(buyerData.buyerRegisterName && { buyerRegisterName: buyerData.buyerRegisterName }),
                                ...(buyerData.buyerTradeName && { buyerTradeName: buyerData.buyerTradeName }),
                                ...(buyerData.vatNumber && { vatNumber: buyerData.vatNumber }),
                                ...(buyerData.buyerTIN && { buyerTIN: buyerData.buyerTIN }),
                                ...((buyerData.buyerContacts.phoneNo || buyerData.buyerContacts.email) && {
                                    buyerContacts: {
                                        ...(buyerData.buyerContacts.phoneNo && { phoneNo: buyerData.buyerContacts.phoneNo }),
                                        ...(buyerData.buyerContacts.email && { email: buyerData.buyerContacts.email })
                                    }
                                }),
                                ...((buyerData.buyerAddress.houseNo || buyerData.buyerAddress.street || 
                                    buyerData.buyerAddress.city || buyerData.buyerAddress.province || 
                                    buyerData.buyerAddress.district) && {
                                    buyerAddress: {
                                        ...(buyerData.buyerAddress.province && { province: buyerData.buyerAddress.province }),
                                        ...(buyerData.buyerAddress.city && { city: buyerData.buyerAddress.city }),
                                        ...(buyerData.buyerAddress.street && { street: buyerData.buyerAddress.street }),
                                        ...(buyerData.buyerAddress.houseNo && { houseNo: buyerData.buyerAddress.houseNo }),
                                        ...(buyerData.buyerAddress.district && { district: buyerData.buyerAddress.district })
                                    }
                                })
                            };
                        }
                        
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
                            // Reload page immediately
                            window.location.reload();
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
                },

                async deleteConfig() {
                    if (!this.config) {
                        this.showMessage('No configuration to delete', 'error');
                        return;
                    }
                    
                    if (!confirm('Are you sure you want to delete the ZIMRA configuration? This will remove all settings, device registration, and certificates. This action cannot be undone.')) {
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        const res = await fetch(`/zimra/config/${this.config.id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok) {
                            this.showMessage('Configuration deleted successfully!', 'success');
                            this.config = null;
                            this.selectedConfigId = '';
                            this.configForm = { company_name: '', company_tin: '', base_url: 'https://fdmsapitest.zimra.co.zw', device_model: 'Server', device_version: 'v1' };
                            this.fiscalDay = null;
                            this.deviceStatus = null;
                            await this.loadAllConfigs();
                            // If there are still configs, select the first one
                            if (this.allConfigs.length > 0) {
                                this.selectedConfigId = this.allConfigs[0].id;
                                await this.switchCompany();
                            }
                        } else {
                            this.showMessage(data.message || 'Failed to delete configuration', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
                },

                async clearDeviceRegistration() {
                    if (!this.config?.device_id) {
                        this.showMessage('No device registration to clear', 'error');
                        return;
                    }
                    
                    if (!confirm('Are you sure you want to clear the device registration? This will remove the device ID, certificates, and all device-related data. You will need to register a new device. This action cannot be undone.')) {
                        return;
                    }
                    
                    this.loading = true;
                    try {
                        const res = await fetch('/zimra/device-registration', {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        
                        const data = await res.json();
                        
                        if (res.ok) {
                            this.showMessage('Device registration cleared successfully! You can now register a new device.', 'success');
                            await this.loadConfig();
                            this.fiscalDay = null;
                            this.deviceStatus = null;
                        } else {
                            this.showMessage(data.message || 'Failed to clear device registration', 'error');
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                    }
                    this.loading = false;
                },

                getTaxID() {
                    // Find tax by taxPercent from FDMS config
                    const tax = this.taxConfig.applicableTaxes.find(t => t.taxPercent === this.receiptForm.taxPercent);
                    return tax ? tax.taxID : 513; // Default to 513 (0% tax)
                },

                calculateTotal() {
                    return this.receiptForm.receiptLines.reduce((sum, line) => {
                        return sum + (line.receiptLineQuantity * line.receiptLinePrice);
                    }, 0);
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
                    if (this.receiptForm.receiptLines.length > 1) {
                        this.receiptForm.receiptLines.splice(index, 1);
                    }
                },

                async loadSales() {
                    try {
                        const res = await fetch('/zimra/receipts');
                        if (res.ok) {
                            const data = await res.json();
                            this.sales = data.receipts || [];
                        }
                    } catch (e) {
                        console.error('Failed to load sales:', e);
                    }
                },

                loadSaleDetails() {
                    this.selectedSale = this.sales.find(s => s.id == this.creditNoteForm.sale_id);
                    if (this.selectedSale) {
                        this.creditNoteForm.currency = this.selectedSale.receipt_currency || 'USD';
                        this.creditNoteForm.selectedLines = [];
                        // Initialize lineQuantities with original quantities
                        this.creditNoteForm.lineQuantities = this.selectedSale.receipt_lines.map(line => 
                            parseFloat(line.receiptLineQuantity || 0)
                        );
                        console.log('Selected Sale:', this.selectedSale);
                        console.log('Receipt Lines:', this.selectedSale.receipt_lines);
                    }
                },

                calculateLineCredit(index) {
                    if (!this.selectedSale || !this.selectedSale.receipt_lines[index]) return 0;
                    
                    const line = this.selectedSale.receipt_lines[index];
                    const creditQty = this.creditNoteForm.lineQuantities[index] || 0;
                    const price = parseFloat(line.receiptLinePrice || 0);
                    
                    return Math.abs(creditQty * price);
                },

                calculateCreditTotal() {
                    if (!this.selectedSale || !this.selectedSale.receipt_lines) return 0;
                    
                    return this.selectedSale.receipt_lines.reduce((sum, line, index) => {
                        if (this.creditNoteForm.selectedLines[index]) {
                            return sum + this.calculateLineCredit(index);
                        }
                        return sum;
                    }, 0);
                },

                async submitCreditNote() {
                    if (!this.creditNoteForm.sale_id) {
                        this.showMessage('Please select a sale', 'error');
                        return;
                    }

                    if (!this.creditNoteForm.reason) {
                        this.showMessage('Please enter a reason', 'error');
                        return;
                    }

                    const selectedLinesCount = this.creditNoteForm.selectedLines.filter(Boolean).length;
                    if (selectedLinesCount === 0) {
                        this.showMessage('Please select at least one product to credit', 'error');
                        return;
                    }
                    
                    // Validate receiptNotes minimum length (RCPT032)
                    const reason = this.creditNoteForm.reason.trim();
                    if (reason.length < 10) {
                        this.showMessage('Credit note reason must be at least 10 characters (meaningful business reason required)', 'error');
                        return;
                    }

                    this.loading = true;
                    this.creditNoteResponse = null;

                    try {
                        // Build selected lines with custom quantities
                        const selectedLines = [];
                        this.creditNoteForm.selectedLines.forEach((selected, index) => {
                            if (selected) {
                                selectedLines.push({
                                    line_index: index,
                                    quantity: this.creditNoteForm.lineQuantities[index] || 0
                                });
                            }
                        });

                        // Use the production-safe BCMath endpoint
                        // Backend will calculate all totals and ensure payment matches receipt total
                        const payload = {
                            original_receipt_id: this.selectedSale.id,
                            selected_lines: selectedLines,
                            reason: this.creditNoteForm.reason,
                            payment_method: this.selectedSale.payment_method || 'Cash'
                        };

                        console.log('Submitting Credit Note (BCMath):', payload);

                        const res = await fetch('/zimra/submit-credit-note', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(payload)
                        });

                        const data = await res.json();

                        if (res.ok && !data.error) {
                            this.showMessage('Credit note submitted successfully!', 'success');
                            this.creditNoteResponse = data;
                            // Reset form
                            this.creditNoteForm = {
                                sale_id: '',
                                currency: 'USD',
                                reason: '',
                                selectedLines: [],
                                lineQuantities: []
                            };
                            this.selectedSale = null;
                            await this.loadSales();
                        } else {
                            this.showMessage(data.error || data.message || 'Failed to submit credit note', 'error');
                            this.creditNoteResponse = data;
                        }
                    } catch (e) {
                        this.showMessage('An error occurred: ' + e.message, 'error');
                        this.creditNoteResponse = { error: true, message: e.message };
                    }
                    this.loading = false;
                }
            };
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
