<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-6 flex items-center justify-between">
                <Link href="/" class="inline-block">
                    <img :src="'/brand/logo-transparent.png'" alt="Curzzo" class="h-10 w-auto" />
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/privacy" class="text-gray-600 hover:text-indigo-600 transition-colors">Privacy Policy</Link>
                </div>
            </div>
        </div>

        <!-- Hero -->
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white">
            <div class="max-w-4xl mx-auto px-4 py-16 md:py-20">
                <h1 class="text-4xl md:text-5xl font-bold mb-3">Terms of Service</h1>
                <p class="text-indigo-200 text-lg">Last updated: April 7, 2026</p>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="space-y-10">

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">1</span>
                        Acceptance of Terms
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        By accessing or using Curzzo ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree to these terms, do not use the Platform. These terms apply to all users, including community creators, members, and visitors.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">2</span>
                        Description of Service
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Curzzo is a community-based learning platform that allows creators to build and manage online communities, courses, live events, and digital content. The Platform provides features including but not limited to:
                    </p>
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Community creation and membership management
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Course and curriculum hosting with video lessons and quizzes
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Live events and webinars
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Community posts, comments, and discussions
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Direct messaging and community chat
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            AI-powered tools (chatbot, content generation, identity verification)
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Certificates of completion
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Affiliate and referral programs
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Payment processing for memberships, courses, and certifications
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Email campaigns and automated sequences
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Support ticketing system
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">3</span>
                        Account Registration
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        To use certain features of the Platform, you must create an account. You agree to provide accurate, current, and complete information during registration, including your name, email address, and optionally your phone number. You are responsible for maintaining the confidentiality of your password and for all activities under your account.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        You must be at least 18 years old to create an account. By registering, you represent that you meet this age requirement.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">4</span>
                        User Conduct
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">You agree not to:</p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Use the Platform for any unlawful purpose or in violation of any applicable laws
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Post or transmit content that is defamatory, obscene, threatening, or infringes on intellectual property rights
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Impersonate any person or entity, or falsely represent your affiliation
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Attempt to gain unauthorized access to other accounts, systems, or networks
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Interfere with or disrupt the Platform or its servers
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Upload malicious code, viruses, or harmful files
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Use automated tools to scrape, crawl, or extract data from the Platform
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Harass, abuse, or harm other users
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-red-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Circumvent any access restrictions or security measures
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">5</span>
                        User-Generated Content
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        You retain ownership of content you post on the Platform, including posts, comments, messages, images, and videos. By posting content, you grant Curzzo a non-exclusive, worldwide, royalty-free license to use, display, reproduce, and distribute your content solely for the purpose of operating and improving the Platform.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        You are solely responsible for the content you post. Curzzo does not endorse or verify user-generated content and reserves the right to remove content that violates these terms or is otherwise objectionable.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">6</span>
                        Community Creators
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Community creators are responsible for the content and management of their communities. Creators may set membership pricing, create courses, host events, and configure community settings. Creators agree to:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Provide accurate descriptions of their communities, courses, and offerings
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Deliver the content and services promised to their members
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Comply with all applicable laws, including consumer protection and data privacy regulations
                        </li>
                        <li class="flex items-start gap-2 text-gray-600 text-sm">
                            <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Not use the Platform to promote illegal activities or fraudulent schemes
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">7</span>
                        Payments and Billing
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-4">
                        Payments on the Platform are processed through Xendit, our third-party payment provider. By making a purchase, you agree to Xendit's terms of service. Curzzo charges platform fees on transactions as follows:
                    </p>
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <p class="text-xs text-gray-500 mb-1">Free plan</p>
                            <p class="text-2xl font-bold text-gray-900">9.8%</p>
                            <p class="text-xs text-gray-400">platform fee</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <p class="text-xs text-gray-500 mb-1">Basic plan</p>
                            <p class="text-2xl font-bold text-gray-900">4.9%</p>
                            <p class="text-xs text-gray-400">platform fee</p>
                        </div>
                        <div class="bg-indigo-50 rounded-xl p-4 text-center border border-indigo-100">
                            <p class="text-xs text-indigo-600 mb-1">Pro plan</p>
                            <p class="text-2xl font-bold text-indigo-600">2.9%</p>
                            <p class="text-xs text-indigo-400">platform fee</p>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        Additional processing fees may apply. All fees are deducted from payments before disbursement to creators. Payouts to creators are subject to a PHP 15 flat fee per withdrawal.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Subscription memberships may be billed monthly or as a one-time payment, depending on the community's configuration. You are responsible for canceling subscriptions before renewal to avoid charges.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">8</span>
                        Refund Policy
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Refund policies are set by individual community creators. Curzzo is not responsible for refund decisions made by creators. If you have a payment dispute, please contact the community creator first. If unresolved, you may contact Curzzo support for assistance.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">9</span>
                        Identity Verification (KYC)
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Creators requesting payouts may be required to complete identity verification (Know Your Customer). This process involves submitting a government-issued ID and a selfie for AI-assisted verification. By submitting KYC documents, you consent to their processing for identity verification purposes.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">10</span>
                        AI-Powered Features
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        The Platform uses artificial intelligence for various features, including but not limited to community chatbots, landing page generation, image generation, and identity verification. AI-generated content is provided "as is" and may not always be accurate or appropriate. Curzzo does not guarantee the accuracy of AI outputs.
                    </p>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
                        <p class="text-amber-800 text-sm leading-relaxed">
                            By using AI features, you acknowledge that your inputs (messages, community information, uploaded images) may be processed by third-party AI providers (Google Gemini) to generate responses and content.
                        </p>
                    </div>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">11</span>
                        Affiliate Program
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Users may participate in the affiliate program to earn commissions by referring new members. Affiliate commissions are tracked and paid out according to the terms set by each community creator. Curzzo reserves the right to suspend or terminate affiliate accounts engaged in fraudulent activity.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">12</span>
                        Intellectual Property
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        The Platform, including its design, code, features, and branding, is the property of Curzzo and is protected by intellectual property laws. You may not copy, modify, distribute, or reverse-engineer any part of the Platform without prior written consent.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">13</span>
                        Termination
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        Curzzo reserves the right to suspend or terminate your account at any time, with or without notice, for conduct that violates these terms or is harmful to other users or the Platform. You may delete your account at any time through your account settings.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Community creators may request community deletion, which will be processed according to our data retention policies.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">14</span>
                        Limitation of Liability
                    </h2>
                    <p class="text-gray-600 leading-relaxed mb-3">
                        Curzzo is provided "as is" without warranties of any kind, express or implied. To the fullest extent permitted by law, Curzzo shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including but not limited to loss of profits, data, or goodwill, arising from your use of the Platform.
                    </p>
                    <p class="text-gray-600 leading-relaxed">
                        Curzzo does not guarantee uninterrupted or error-free service. We are not responsible for content created by community creators or interactions between users.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">15</span>
                        Indemnification
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        You agree to indemnify and hold harmless Curzzo, its officers, directors, employees, and agents from any claims, damages, losses, or expenses arising from your use of the Platform, your violation of these terms, or your infringement of any third-party rights.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">16</span>
                        Changes to Terms
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        Curzzo reserves the right to modify these Terms of Service at any time. Changes will be posted on this page with an updated "Last updated" date. Your continued use of the Platform after changes are posted constitutes acceptance of the revised terms.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">17</span>
                        Governing Law
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        These Terms of Service shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes arising from these terms shall be resolved in the courts of the Philippines.
                    </p>
                </section>

                <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 md:p-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4 flex items-center gap-3">
                        <span class="flex-shrink-0 w-8 h-8 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-sm font-bold">18</span>
                        Contact Us
                    </h2>
                    <p class="text-gray-600 leading-relaxed">
                        If you have questions about these Terms of Service, please contact us through the support ticketing system on the Platform or email us at
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
                    <Link href="/terms" class="text-indigo-600 font-medium">Terms of Service</Link>
                    <Link href="/privacy" class="text-gray-500 hover:text-indigo-600 transition-colors">Privacy Policy</Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
