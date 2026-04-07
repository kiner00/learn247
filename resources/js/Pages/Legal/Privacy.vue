<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 py-6 flex items-center justify-between">
                <Link href="/" class="inline-block">
                    <img src="/brand/logo-transparent.png" alt="Curzzo" class="h-10 w-auto" />
                </Link>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/terms" class="text-gray-600 hover:text-indigo-600">Terms of Service</Link>
                    <Link href="/login" class="text-indigo-600 font-medium hover:underline">Log in</Link>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 py-12">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 md:p-12">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Privacy Policy</h1>
                <p class="text-sm text-gray-500 mb-10">Last updated: April 7, 2026</p>

                <div class="prose prose-gray max-w-none prose-headings:text-gray-900 prose-p:text-gray-600 prose-li:text-gray-600 prose-a:text-indigo-600">
                    <h2>1. Introduction</h2>
                    <p>
                        Curzzo ("we", "our", "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your personal information when you use our community-based learning platform.
                    </p>
                    <p>
                        By using Curzzo, you consent to the data practices described in this policy. If you do not agree, please do not use the Platform.
                    </p>

                    <h2>2. Information We Collect</h2>

                    <h3>2.1 Information You Provide</h3>
                    <ul>
                        <li><strong>Account information:</strong> First name, last name, email address, phone number (optional), and password</li>
                        <li><strong>Profile information:</strong> Username, bio, location, avatar image, social media links, and timezone preference</li>
                        <li><strong>Payment information:</strong> Payout method (bank transfer, e-wallet, or crypto wallet address), bank name, and account details for receiving payments. Payment card details are processed directly by Xendit and are not stored on our servers</li>
                        <li><strong>Identity verification (KYC):</strong> Government-issued ID photo and selfie, submitted voluntarily by creators requesting payouts</li>
                        <li><strong>Content:</strong> Posts, comments, messages, chat messages, support tickets, course materials, and other content you create or upload</li>
                        <li><strong>Media:</strong> Images and videos uploaded to your profile, communities, courses, or chat</li>
                    </ul>

                    <h3>2.2 Information Collected Automatically</h3>
                    <ul>
                        <li><strong>Session data:</strong> IP address, user agent (browser type and version), and session activity</li>
                        <li><strong>Cookies:</strong> Session cookies, CSRF tokens, and affiliate referral cookies (see Section 6)</li>
                        <li><strong>Usage data:</strong> Pages visited, features used, course progress, and engagement metrics (likes, comments, points)</li>
                    </ul>

                    <h3>2.3 Information from Third Parties</h3>
                    <ul>
                        <li><strong>Payment provider:</strong> Transaction status and payment confirmations from Xendit</li>
                    </ul>

                    <h2>3. How We Use Your Information</h2>
                    <p>We use your personal information to:</p>
                    <ul>
                        <li><strong>Provide the Platform:</strong> Create and manage your account, process memberships and payments, deliver courses and content</li>
                        <li><strong>Communication:</strong> Send transactional emails (receipts, password resets, notifications), and community email campaigns from creators you've subscribed to</li>
                        <li><strong>AI features:</strong> Power community chatbots, generate landing pages and images, and verify identity documents (see Section 5)</li>
                        <li><strong>Safety and security:</strong> Detect fraud, verify identities, enforce our Terms of Service, and protect users</li>
                        <li><strong>Improvement:</strong> Analyze usage patterns to improve the Platform's features and performance</li>
                        <li><strong>Affiliate tracking:</strong> Track referrals and calculate commissions for affiliate partners</li>
                    </ul>

                    <h2>4. How We Share Your Information</h2>
                    <p>We do not sell your personal information. We may share your data with:</p>

                    <h3>4.1 Service Providers</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Provider</th>
                                <th>Purpose</th>
                                <th>Data Shared</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Xendit</td>
                                <td>Payment processing and payouts</td>
                                <td>Transaction amounts, payout details</td>
                            </tr>
                            <tr>
                                <td>Amazon Web Services (S3)</td>
                                <td>File storage</td>
                                <td>Uploaded images, videos, and documents</td>
                            </tr>
                            <tr>
                                <td>Google (Gemini AI)</td>
                                <td>AI chatbot, content generation, KYC verification</td>
                                <td>User messages, community info, KYC document images</td>
                            </tr>
                            <tr>
                                <td>Resend</td>
                                <td>Email delivery</td>
                                <td>Email addresses and email content</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3>4.2 Community Creators</h3>
                    <p>
                        When you join a community, the community creator can see your profile information (name, username, email, avatar), membership status, course progress, and engagement activity within their community.
                    </p>

                    <h3>4.3 Other Users</h3>
                    <p>
                        Your public profile (name, username, avatar, bio) is visible to other users. Posts, comments, and chat messages are visible to members of the relevant community. Direct messages are visible only to you and the recipient.
                    </p>

                    <h3>4.4 Third-Party Tracking (Set by Creators)</h3>
                    <p>
                        Community creators may optionally configure third-party tracking pixels on their community pages, including Facebook Pixel, TikTok Pixel, and Google Analytics. These tools are managed by the respective community creator, not by Curzzo. Please review each community's tracking disclosures if applicable.
                    </p>

                    <h3>4.5 Legal Requirements</h3>
                    <p>
                        We may disclose your information if required by law, court order, or governmental request, or if necessary to protect the rights, property, or safety of Curzzo, our users, or the public.
                    </p>

                    <h2>5. AI and Automated Processing</h2>
                    <p>
                        Curzzo uses artificial intelligence (powered by Google Gemini) for the following purposes:
                    </p>
                    <ul>
                        <li><strong>Community chatbots:</strong> Your messages to community chatbots are sent to Google Gemini to generate responses. Conversation history is stored to maintain context</li>
                        <li><strong>Landing page and image generation:</strong> Community metadata (name, description, brand settings) is used to generate landing pages and promotional images</li>
                        <li><strong>Identity verification:</strong> If you submit KYC documents, your government ID and selfie are analyzed by AI for identity verification. The AI result is stored alongside your verification status</li>
                    </ul>
                    <p>
                        AI-generated content is not guaranteed to be accurate. We do not use your personal data to train AI models.
                    </p>

                    <h2>6. Cookies</h2>
                    <p>We use the following cookies:</p>
                    <table>
                        <thead>
                            <tr>
                                <th>Cookie</th>
                                <th>Purpose</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Session cookie</td>
                                <td>Maintains your login session</td>
                                <td>120 minutes (or until logout)</td>
                            </tr>
                            <tr>
                                <td>CSRF token</td>
                                <td>Protects against cross-site request forgery</td>
                                <td>Session</td>
                            </tr>
                            <tr>
                                <td>Remember me</td>
                                <td>Keeps you logged in between sessions</td>
                                <td>Persistent</td>
                            </tr>
                            <tr>
                                <td>Affiliate referral (ref_code)</td>
                                <td>Tracks referral source for affiliate commissions</td>
                                <td>Session</td>
                            </tr>
                        </tbody>
                    </table>
                    <p>
                        We do not use cookies for advertising purposes. Third-party tracking pixels configured by community creators may set their own cookies.
                    </p>

                    <h2>7. Data Storage and Security</h2>
                    <p>
                        Your data is stored on secure servers with the following protections:
                    </p>
                    <ul>
                        <li>Passwords are hashed using bcrypt and are never stored in plain text</li>
                        <li>Sensitive API keys are encrypted at rest in the database</li>
                        <li>Session data is stored in Redis with configurable expiration</li>
                        <li>File uploads are stored on Amazon S3 with access controls</li>
                        <li>HTTPS encryption for all data in transit</li>
                        <li>CSRF protection on all forms and API endpoints</li>
                    </ul>

                    <h2>8. Data Retention</h2>
                    <p>
                        We retain your personal data for as long as your account is active or as needed to provide you with our services. When you delete your account:
                    </p>
                    <ul>
                        <li>Your profile information is permanently deleted</li>
                        <li>Your community memberships are removed</li>
                        <li>Content you created (posts, comments) may be retained in anonymized form</li>
                        <li>Payment records are retained as required by applicable financial regulations</li>
                    </ul>

                    <h2>9. Your Rights</h2>
                    <p>Depending on your jurisdiction, you may have the right to:</p>
                    <ul>
                        <li><strong>Access:</strong> Request a copy of the personal data we hold about you</li>
                        <li><strong>Correction:</strong> Update or correct inaccurate personal data through your account settings</li>
                        <li><strong>Deletion:</strong> Request deletion of your account and personal data</li>
                        <li><strong>Objection:</strong> Object to certain processing of your personal data</li>
                        <li><strong>Portability:</strong> Request your data in a portable format</li>
                        <li><strong>Unsubscribe:</strong> Opt out of email campaigns using the unsubscribe link in any email</li>
                    </ul>
                    <p>
                        To exercise these rights, contact us through the support ticketing system or email <a href="mailto:support@curzzo.com">support@curzzo.com</a>.
                    </p>

                    <h2>10. Children's Privacy</h2>
                    <p>
                        Curzzo is not intended for children under 18 years of age. We do not knowingly collect personal information from children. If we learn that we have collected data from a child under 18, we will delete that information promptly.
                    </p>

                    <h2>11. International Data Transfers</h2>
                    <p>
                        Your data may be transferred to and processed in countries other than your country of residence, including countries where our service providers operate (e.g., the United States for AWS, Google). We take steps to ensure your data is protected in accordance with this Privacy Policy regardless of where it is processed.
                    </p>

                    <h2>12. Third-Party Links</h2>
                    <p>
                        The Platform may contain links to third-party websites or services. We are not responsible for the privacy practices of these third parties. We encourage you to review their privacy policies before providing them with your information.
                    </p>

                    <h2>13. Changes to This Policy</h2>
                    <p>
                        We may update this Privacy Policy from time to time. Changes will be posted on this page with an updated "Last updated" date. Your continued use of the Platform after changes are posted constitutes acceptance of the revised policy.
                    </p>

                    <h2>14. Contact Us</h2>
                    <p>
                        If you have questions or concerns about this Privacy Policy or our data practices, please contact us through the support ticketing system on the Platform or email us at <a href="mailto:support@curzzo.com">support@curzzo.com</a>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="max-w-4xl mx-auto px-4 pb-12 text-center text-sm text-gray-500">
            &copy; {{ new Date().getFullYear() }} Curzzo. All rights reserved.
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
