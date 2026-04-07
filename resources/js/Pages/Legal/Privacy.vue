<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-6 flex items-center justify-between">
                <Link href="/" class="inline-block">
                    <img :src="'/brand/logo-transparent.png'" alt="Curzzo" class="h-10 w-auto" />
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/terms" class="text-gray-600 hover:text-indigo-600 transition-colors">Terms of Service</Link>
                </div>
            </div>
        </div>

        <!-- Hero -->
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white">
            <div class="max-w-4xl mx-auto px-4 py-16 md:py-20">
                <h1 class="text-4xl md:text-5xl font-bold mb-3">Privacy Policy</h1>
                <p class="text-indigo-200 text-lg">Last updated: April 7, 2026</p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="space-y-10">

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">1</span>
                        Introduction
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        Curzzo ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your personal information when you use our community-based learning platform.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        By using Curzzo, you consent to the data practices described in this policy. If you do not agree, please do not use the Platform.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">2</span>
                        Information We Collect
                    </h2>

                    <h3 class="text-base font-semibold text-gray-800 mb-3 mt-2">2.1 Information You Provide</h3>
                    <div class="space-y-3 mb-6">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Account information</p>
                            <p class="text-sm text-gray-600">First name, last name, email address, phone number (optional), and password</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Profile information</p>
                            <p class="text-sm text-gray-600">Username, bio, location, avatar image, social media links, and timezone preference</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Payment information</p>
                            <p class="text-sm text-gray-600">Payout method (bank transfer, e-wallet, or crypto wallet address), bank name, and account details for receiving payments. Payment card details are processed directly by Xendit and are not stored on our servers</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Identity verification (KYC)</p>
                            <p class="text-sm text-gray-600">Government-issued ID photo and selfie, submitted voluntarily by creators requesting payouts</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Content</p>
                            <p class="text-sm text-gray-600">Posts, comments, messages, chat messages, support tickets, course materials, and other content you create or upload</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Media</p>
                            <p class="text-sm text-gray-600">Images and videos uploaded to your profile, communities, courses, or chat</p>
                        </div>
                    </div>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">2.2 Information Collected Automatically</h3>
                    <div class="space-y-3 mb-6">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Session data</p>
                            <p class="text-sm text-gray-600">IP address, user agent (browser type and version), and session activity</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Cookies</p>
                            <p class="text-sm text-gray-600">Session cookies, CSRF tokens, and affiliate referral cookies (see Section 6)</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Usage data</p>
                            <p class="text-sm text-gray-600">Pages visited, features used, course progress, and engagement metrics (likes, comments, points)</p>
                        </div>
                    </div>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">2.3 Information from Third Parties</h3>
                    <div class="bg-gray-50 rounded-xl p-4">
                        <p class="text-sm font-semibold text-gray-700 mb-1">Payment provider</p>
                        <p class="text-sm text-gray-600">Transaction status and payment confirmations from Xendit</p>
                    </div>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">3</span>
                        How We Use Your Information
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">We use your personal information to:</p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">Provide the Platform:</strong> Create and manage your account, process memberships and payments, deliver courses and content</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">Communication:</strong> Send transactional emails (receipts, password resets, notifications), and community email campaigns from creators you've subscribed to</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">AI features:</strong> Power community chatbots, generate landing pages and images, and verify identity documents (see Section 5)</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">Safety and security:</strong> Detect fraud, verify identities, enforce our Terms of Service, and protect users</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">Improvement:</strong> Analyze usage patterns to improve the Platform's features and performance</span>
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <span><strong class="text-gray-700">Affiliate tracking:</strong> Track referrals and calculate commissions for affiliate partners</span>
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">4</span>
                        How We Share Your Information
                    </h2>
                    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6">
                        <p class="text-green-800 text-sm font-medium">We do not sell your personal information.</p>
                    </div>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">4.1 Service Providers</h3>
                    <div class="overflow-x-auto mb-6">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Provider</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Purpose</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Data Shared</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600">
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">Xendit</td>
                                    <td class="py-3 px-4">Payment processing and payouts</td>
                                    <td class="py-3 px-4">Transaction amounts, payout details</td>
                                </tr>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">Amazon Web Services (S3)</td>
                                    <td class="py-3 px-4">File storage</td>
                                    <td class="py-3 px-4">Uploaded images, videos, and documents</td>
                                </tr>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">Google (Gemini AI)</td>
                                    <td class="py-3 px-4">AI chatbot, content generation, KYC verification</td>
                                    <td class="py-3 px-4">User messages, community info, KYC document images</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-700">Resend</td>
                                    <td class="py-3 px-4">Email delivery</td>
                                    <td class="py-3 px-4">Email addresses and email content</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">4.2 Community Creators</h3>
                    <p class="text-gray-600 leading-relaxed text-sm mb-6">
                        When you join a community, the community creator can see your profile information (name, username, email, avatar), membership status, course progress, and engagement activity within their community.
                    </p>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">4.3 Other Users</h3>
                    <p class="text-gray-600 leading-relaxed text-sm mb-6">
                        Your public profile (name, username, avatar, bio) is visible to other users. Posts, comments, and chat messages are visible to members of the relevant community. Direct messages are visible only to you and the recipient.
                    </p>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">4.4 Third-Party Tracking (Set by Creators)</h3>
                    <p class="text-gray-600 leading-relaxed text-sm mb-6">
                        Community creators may optionally configure third-party tracking pixels on their community pages, including Facebook Pixel, TikTok Pixel, and Google Analytics. These tools are managed by the respective community creator, not by Curzzo. Please review each community's tracking disclosures if applicable.
                    </p>

                    <h3 class="text-base font-semibold text-gray-800 mb-3">4.5 Legal Requirements</h3>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        We may disclose your information if required by law, court order, or governmental request, or if necessary to protect the rights, property, or safety of Curzzo, our users, or the public.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">5</span>
                        AI and Automated Processing
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Curzzo uses artificial intelligence (powered by Google Gemini) for the following purposes:
                    </p>
                    <div class="space-y-3 mb-4">
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                            <p class="text-sm font-semibold text-indigo-700 mb-1">Community chatbots</p>
                            <p class="text-sm text-indigo-600">Your messages to community chatbots are sent to Google Gemini to generate responses. Conversation history is stored to maintain context</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                            <p class="text-sm font-semibold text-indigo-700 mb-1">Landing page and image generation</p>
                            <p class="text-sm text-indigo-600">Community metadata (name, description, brand settings) is used to generate landing pages and promotional images</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100">
                            <p class="text-sm font-semibold text-indigo-700 mb-1">Identity verification</p>
                            <p class="text-sm text-indigo-600">If you submit KYC documents, your government ID and selfie are analyzed by AI for identity verification. The AI result is stored alongside your verification status</p>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        AI-generated content is not guaranteed to be accurate. We do not use your personal data to train AI models.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">6</span>
                        Cookies
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">We use the following cookies:</p>
                    <div class="overflow-x-auto mb-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Cookie</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Purpose</th>
                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">Duration</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600">
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">Session cookie</td>
                                    <td class="py-3 px-4">Maintains your login session</td>
                                    <td class="py-3 px-4">120 minutes (or until logout)</td>
                                </tr>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">CSRF token</td>
                                    <td class="py-3 px-4">Protects against cross-site request forgery</td>
                                    <td class="py-3 px-4">Session</td>
                                </tr>
                                <tr class="border-b border-gray-100">
                                    <td class="py-3 px-4 font-medium text-gray-700">Remember me</td>
                                    <td class="py-3 px-4">Keeps you logged in between sessions</td>
                                    <td class="py-3 px-4">Persistent</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-700">Affiliate referral</td>
                                    <td class="py-3 px-4">Tracks referral source for affiliate commissions</td>
                                    <td class="py-3 px-4">Session</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        We do not use cookies for advertising purposes. Third-party tracking pixels configured by community creators may set their own cookies.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">7</span>
                        Data Storage and Security
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Your data is stored on secure servers with the following protections:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Passwords are hashed using bcrypt and are never stored in plain text
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Sensitive API keys are encrypted at rest in the database
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Session data is stored in Redis with configurable expiration
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            File uploads are stored on Amazon S3 with access controls
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            HTTPS encryption for all data in transit
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            CSRF protection on all forms and API endpoints
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">8</span>
                        Data Retention
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        We retain your personal data for as long as your account is active or as needed to provide you with our services. When you delete your account:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Your profile information is permanently deleted
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Your community memberships are removed
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Content you created (posts, comments) may be retained in anonymized form
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Payment records are retained as required by applicable financial regulations
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">9</span>
                        Your Rights
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">Depending on your jurisdiction, you may have the right to:</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Access</p>
                            <p class="text-xs text-gray-600">Request a copy of the personal data we hold about you</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Correction</p>
                            <p class="text-xs text-gray-600">Update or correct inaccurate personal data through your account settings</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Deletion</p>
                            <p class="text-xs text-gray-600">Request deletion of your account and personal data</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Objection</p>
                            <p class="text-xs text-gray-600">Object to certain processing of your personal data</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Portability</p>
                            <p class="text-xs text-gray-600">Request your data in a portable format</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700 mb-1">Unsubscribe</p>
                            <p class="text-xs text-gray-600">Opt out of email campaigns using the unsubscribe link in any email</p>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed text-sm">
                        To exercise these rights, contact us through the support ticketing system or email
                        <a href="mailto:support@curzzo.com" class="text-indigo-600 hover:underline font-medium">support@curzzo.com</a>.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">10</span>
                        Children's Privacy
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Curzzo is not intended for children under 18 years of age. We do not knowingly collect personal information from children. If we learn that we have collected data from a child under 18, we will delete that information promptly.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">11</span>
                        International Data Transfers
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Your data may be transferred to and processed in countries other than your country of residence, including countries where our service providers operate (e.g., the United States for AWS, Google). We take steps to ensure your data is protected in accordance with this Privacy Policy regardless of where it is processed.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">12</span>
                        Third-Party Links
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        The Platform may contain links to third-party websites or services. We are not responsible for the privacy practices of these third parties. We encourage you to review their privacy policies before providing them with your information.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">13</span>
                        Changes to This Policy
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated "Last updated" date. Your continued use of the Platform after changes are posted constitutes acceptance of the revised policy.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">14</span>
                        Contact Us
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        If you have questions or concerns about this Privacy Policy or our data practices, please contact us through the support ticketing system on the Platform or email us at
                        <a href="mailto:support@curzzo.com" class="text-indigo-600 hover:underline font-medium">support@curzzo.com</a>.
                    </p>
                </section>

            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 bg-white mt-12">
            <div class="max-w-4xl mx-auto px-4 py-8 flex flex-col md:flex-row items-center justify-between gap-4">
                <p class="text-sm text-gray-500">&copy; {{ new Date().getFullYear() }} Curzzo. All rights reserved.</p>
                <div class="flex items-center gap-6 text-sm">
                    <Link href="/terms" class="text-gray-500 hover:text-indigo-600 transition-colors">Terms of Service</Link>
                    <Link href="/privacy" class="text-indigo-600 font-medium">Privacy Policy</Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
