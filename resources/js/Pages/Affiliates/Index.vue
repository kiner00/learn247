<template>
    <AppLayout title="My Affiliates">
        <div class="max-w-5xl">
            <!-- Header + tabs -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">
                    My Affiliates
                </h1>
                <div class="flex border-b border-gray-200">
                    <button
                        v-for="tab in TABS"
                        :key="tab.value"
                        @click="activeTab = tab.value"
                        class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors"
                        :class="
                            activeTab === tab.value
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
                        "
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- ── Tab: Links ─────────────────────────────────────────────────── -->
            <div v-if="activeTab === 'links'">
                <p class="text-sm text-gray-500 mb-5">
                    Share your referral links. When someone subscribes through
                    your link, you earn the community's commission.
                </p>

                <div
                    v-if="affiliates.length === 0"
                    class="text-center py-16 bg-white rounded-2xl border border-gray-200"
                >
                    <div class="text-4xl mb-3">🔗</div>
                    <p class="font-medium text-gray-700">
                        No affiliate links yet
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        Join a paid community and click "Become an Affiliate" to
                        get started.
                    </p>
                </div>

                <div
                    v-if="totalEligible > 0"
                    class="flex items-center justify-between mb-3"
                >
                    <p class="text-sm text-gray-500">
                        Total eligible:
                        <span class="font-semibold text-gray-900"
                            >₱{{ fmt(totalEligible) }}</span
                        >
                    </p>
                    <button
                        @click="requestAll"
                        class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-xl transition-colors"
                    >
                        Request All Payouts
                    </button>
                </div>

                <div
                    v-if="affiliates.length > 0"
                    class="bg-white rounded-2xl border border-gray-200 overflow-hidden"
                >
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th
                                    class="text-left px-5 py-3 font-semibold text-gray-600"
                                >
                                    Community
                                </th>
                                <th
                                    class="text-left px-5 py-3 font-semibold text-gray-600"
                                >
                                    Referral Link
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                >
                                    Earned
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                >
                                    Paid Out
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                >
                                    Pending
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                >
                                    Payout
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                >
                                    Pixels
                                </th>
                                <th
                                    class="text-right px-5 py-3 font-semibold text-gray-600"
                                ></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr
                                v-for="a in affiliates"
                                :key="a.id"
                                :class="[
                                    'hover:bg-gray-50',
                                    !a.is_active ? 'opacity-60' : '',
                                ]"
                            >
                                <td class="px-5 py-4 font-medium text-gray-900">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/communities/${a.community.slug}`"
                                            class="hover:text-indigo-600"
                                        >
                                            {{ a.community.name }}
                                        </Link>
                                        <span
                                            v-if="!a.is_active"
                                            class="text-xs font-bold uppercase bg-red-100 text-red-600 px-2 py-0.5 rounded-full"
                                        >
                                            Suspended
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div
                                        v-if="a.is_active"
                                        class="flex items-center gap-2"
                                    >
                                        <span
                                            class="font-mono text-xs text-gray-500 truncate max-w-48"
                                            >{{ a.referral_url }}</span
                                        >
                                        <button
                                            @click="copy(a.referral_url)"
                                            class="shrink-0 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                        >
                                            {{
                                                copied === a.referral_url
                                                    ? "✓ Copied"
                                                    : "Copy"
                                            }}
                                        </button>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs text-red-500 italic"
                                        >Renew subscription to reactivate</span
                                    >
                                </td>
                                <td
                                    class="px-5 py-4 text-right text-gray-900 font-medium"
                                >
                                    ₱{{ fmt(a.total_earned) }}
                                </td>
                                <td class="px-5 py-4 text-right text-gray-500">
                                    ₱{{ fmt(a.total_paid) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span
                                        :class="
                                            a.pending_amount > 0
                                                ? 'text-green-700 font-semibold'
                                                : 'text-gray-400'
                                        "
                                    >
                                        ₱{{ fmt(a.pending_amount) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        @click="showPayoutModal = true"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        {{
                                            payoutMethod
                                                ? "✓ " +
                                                  payoutMethod.toUpperCase()
                                                : "Set payout"
                                        }}
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        @click="openPixelModal(a)"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        {{
                                            a.facebook_pixel_id || a.tiktok_pixel_id || a.google_analytics_id
                                                ? "✓ Pixels"
                                                : "Set pixels"
                                        }}
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <span
                                        v-if="
                                            a.payout_request_status ===
                                            'approved'
                                        "
                                        class="inline-flex flex-col items-end gap-0.5"
                                    >
                                        <span
                                            class="text-xs text-green-600 font-medium"
                                            >Approved</span
                                        >
                                        <span
                                            class="text-[10px] text-gray-400 leading-tight"
                                            >Being processed — payout will be
                                            sent to your
                                            {{
                                                a.payout_method?.toUpperCase() ??
                                                "account"
                                            }}
                                            shortly</span
                                        >
                                    </span>
                                    <span
                                        v-else-if="
                                            a.payout_request_status ===
                                            'pending'
                                        "
                                        class="inline-flex flex-col items-end gap-0.5"
                                    >
                                        <span
                                            class="text-xs text-amber-600 font-medium"
                                            >Pending review</span
                                        >
                                        <span
                                            class="text-[10px] text-gray-400 leading-tight"
                                            >Admin is reviewing your
                                            request</span
                                        >
                                    </span>
                                    <button
                                        v-else-if="a.eligible_amount > 0"
                                        @click="openRequestModal(a)"
                                        class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                    >
                                        Request Payout
                                    </button>
                                    <span v-else class="text-xs text-gray-300"
                                        >—</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Tab: Analytics ─────────────────────────────────────────────── -->
            <div v-if="activeTab === 'analytics'">
                <!-- Filters -->
                <div class="flex items-center gap-2 mb-5">
                    <select
                        :value="communityId ?? ''"
                        @change="
                            applyFilter(
                                'community',
                                $event.target.value || null,
                            )
                        "
                        class="px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="">All communities</option>
                        <option
                            v-for="c in analytics.communities"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>

                    <div
                        class="flex rounded-lg border border-gray-200 overflow-hidden"
                    >
                        <button
                            v-for="p in PERIODS"
                            :key="p.value"
                            @click="applyFilter('period', p.value)"
                            class="px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                period === p.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 hover:bg-gray-50'
                            "
                        >
                            {{ p.label }}
                        </button>
                    </div>
                </div>

                <!-- Summary cards -->
                <div
                    class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5"
                >
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Total Earned</p>
                        <p class="text-lg font-bold text-gray-900">
                            ₱{{ fmt(analytics.summary.total_earned) }}
                        </p>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Paid Out</p>
                        <p class="text-lg font-bold text-green-600">
                            ₱{{ fmt(analytics.summary.total_paid) }}
                        </p>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Pending</p>
                        <p class="text-lg font-bold text-amber-500">
                            ₱{{ fmt(analytics.summary.total_pending) }}
                        </p>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Conversions</p>
                        <p class="text-lg font-bold text-gray-900">
                            {{ analytics.summary.total_conversions }}
                        </p>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Avg / Referral</p>
                        <p class="text-lg font-bold text-indigo-600">
                            ₱{{ fmt(analytics.summary.avg_per_referral) }}
                        </p>
                    </div>
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-4 text-center"
                    >
                        <p class="text-xs text-gray-400 mb-1">Best Month</p>
                        <p class="text-sm font-bold text-gray-900">
                            {{ analytics.summary.best_month ?? "—" }}
                        </p>
                        <p
                            v-if="analytics.summary.best_month_total"
                            class="text-xs text-gray-500"
                        >
                            ₱{{ fmt(analytics.summary.best_month_total) }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
                    <!-- Bar chart -->
                    <div
                        class="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-5"
                    >
                        <h2 class="text-sm font-semibold text-gray-900 mb-4">
                            Earnings —
                            {{ PERIODS.find((p) => p.value === period)?.label }}
                        </h2>
                        <BarChart :data="analytics.chartData" />
                    </div>

                    <!-- By community -->
                    <div
                        class="bg-white border border-gray-200 rounded-2xl p-5"
                    >
                        <h2 class="text-sm font-semibold text-gray-900 mb-4">
                            By Community (All Time)
                        </h2>
                        <div v-if="analytics.byComm.length" class="space-y-3">
                            <div
                                v-for="row in analytics.byComm"
                                :key="row.community"
                            >
                                <div class="flex justify-between text-xs mb-1">
                                    <span
                                        class="text-gray-700 font-medium truncate max-w-[60%]"
                                        >{{ row.community }}</span
                                    >
                                    <span class="text-gray-500"
                                        >₱{{ fmt(row.total) }}</span
                                    >
                                </div>
                                <div
                                    class="h-1.5 bg-gray-100 rounded-full overflow-hidden"
                                >
                                    <div
                                        class="h-full bg-indigo-400 rounded-full"
                                        :style="{
                                            width: `${(row.total / byCommMax) * 100}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-400">No data yet.</p>
                    </div>
                </div>

                <!-- Conversion history -->
                <div class="bg-white border border-gray-200 rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-semibold text-gray-900">
                            Conversion History
                            <span class="text-gray-400 font-normal"
                                >(last 100)</span
                            >
                        </h2>
                        <div class="flex gap-1.5">
                            <button
                                v-for="s in STATUS_FILTERS"
                                :key="s.value"
                                @click="statusFilter = s.value"
                                class="px-2.5 py-1 text-xs rounded-lg border transition-colors"
                                :class="
                                    statusFilter === s.value
                                        ? 'bg-indigo-50 border-indigo-200 text-indigo-700 font-medium'
                                        : 'border-gray-200 text-gray-500 hover:bg-gray-50'
                                "
                            >
                                {{ s.label }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="filteredConversions.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left border-b border-gray-100">
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400"
                                    >
                                        Date
                                    </th>
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400"
                                    >
                                        Community
                                    </th>
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400 text-right"
                                    >
                                        Sale
                                    </th>
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400 text-right"
                                    >
                                        Commission
                                    </th>
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="pb-2 text-xs font-semibold text-gray-400"
                                    >
                                        Paid At
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr
                                    v-for="c in filteredConversions"
                                    :key="c.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td
                                        class="py-2.5 text-gray-600 whitespace-nowrap"
                                    >
                                        {{ c.date }}
                                    </td>
                                    <td
                                        class="py-2.5 text-gray-800 font-medium max-w-40 truncate"
                                    >
                                        {{ c.community }}
                                    </td>
                                    <td
                                        class="py-2.5 text-gray-600 text-right whitespace-nowrap"
                                    >
                                        ₱{{ fmt(c.sale_amount) }}
                                    </td>
                                    <td
                                        class="py-2.5 text-indigo-700 font-semibold text-right whitespace-nowrap"
                                    >
                                        ₱{{ fmt(c.commission_amount) }}
                                    </td>
                                    <td class="py-2.5">
                                        <span
                                            class="px-2 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-700':
                                                    c.status === 'paid',
                                                'bg-amber-100 text-amber-700':
                                                    c.status === 'pending',
                                                'bg-red-100 text-red-600':
                                                    c.status === 'failed',
                                            }"
                                        >
                                            {{ c.status }}
                                        </span>
                                    </td>
                                    <td
                                        class="py-2.5 text-gray-400 whitespace-nowrap"
                                    >
                                        {{ c.paid_at ?? "—" }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="text-sm text-gray-400 py-6 text-center">
                        No conversions yet.
                    </p>
                </div>
            </div>
        </div>

        <!-- Request Payout modal -->
        <Teleport to="body">
            <div
                v-if="showRequestModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showRequestModal = false"
            >
                <div
                    class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6"
                >
                    <h3 class="text-base font-bold text-gray-900 mb-1">
                        Request Payout
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">
                        {{ requestingAffiliate?.community.name }} · Eligible:
                        ₱{{ fmt(requestingAffiliate?.eligible_amount) }}
                    </p>

                    <div
                        v-if="!payoutMethod"
                        class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-4 text-sm text-amber-700"
                    >
                        Please set your payout method first.
                        <button
                            @click="
                                showRequestModal = false;
                                showPayoutModal = true;
                            "
                            class="underline ml-1 font-semibold"
                        >
                            Set it now
                        </button>
                    </div>

                    <form
                        v-else
                        @submit.prevent="submitRequest"
                        class="space-y-3"
                    >
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-600 mb-1"
                                >Amount (₱)</label
                            >
                            <input
                                v-model.number="requestForm.amount"
                                type="number"
                                step="0.01"
                                :min="1"
                                :max="requestingAffiliate?.eligible_amount"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p class="text-xs text-gray-400 mt-1">
                                Max: ₱{{
                                    fmt(requestingAffiliate?.eligible_amount)
                                }}
                            </p>
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button
                                type="button"
                                @click="showRequestModal = false"
                                class="flex-1 py-2 border border-gray-200 text-gray-600 text-sm rounded-xl hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="requestForm.processing"
                                class="flex-1 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Pixel settings modal -->
        <Teleport to="body">
            <div
                v-if="showPixelModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showPixelModal = false"
            >
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
                    <h3 class="text-base font-bold text-gray-900 mb-1">
                        Ad Tracking Pixels
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">
                        Your pixels fire alongside the creator's pixels when someone visits via your referral link.
                    </p>
                    <form @submit.prevent="savePixels" class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Facebook Pixel ID</label>
                            <input
                                v-model="pixelForm.facebook_pixel_id"
                                type="text"
                                placeholder="e.g. 1234567890123456"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">TikTok Pixel ID</label>
                            <input
                                v-model="pixelForm.tiktok_pixel_id"
                                type="text"
                                placeholder="e.g. CXXXXXXXXXXXXX"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Google Analytics ID</label>
                            <input
                                v-model="pixelForm.google_analytics_id"
                                type="text"
                                placeholder="e.g. G-XXXXXXXXXX"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button
                                type="button"
                                @click="showPixelModal = false"
                                class="flex-1 py-2 border border-gray-200 text-gray-600 text-sm rounded-xl hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="flex-1 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Payout method modal -->
        <Teleport to="body">
            <div
                v-if="showPayoutModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showPayoutModal = false"
            >
                <div
                    class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6"
                >
                    <h3 class="text-base font-bold text-gray-900 mb-1">
                        Payout Details
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">
                        This applies to all your affiliate earnings.
                    </p>
                    <form @submit.prevent="savePayout" class="space-y-3">
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-600 mb-1"
                                >Method</label
                            >
                            <select
                                v-model="payoutForm.payout_method"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-gray-600 mb-1"
                            >
                                {{
                                    payoutForm.payout_method === "bank"
                                        ? "Account Number / Name"
                                        : "Account / Number"
                                }}
                            </label>
                            <input
                                v-model="payoutForm.payout_details"
                                type="text"
                                placeholder="e.g. 09xxxxxxxxx"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="flex gap-3 pt-1">
                            <button
                                type="button"
                                @click="showPayoutModal = false"
                                class="flex-1 py-2 border border-gray-200 text-gray-600 text-sm rounded-xl hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="flex-1 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl hover:bg-indigo-700"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import { Link, router } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AppLayout.vue";
import BarChart from "./BarChart.vue";

const props = defineProps({
    affiliates: Array,
    payoutMethod: String,
    payoutDetails: String,
    period: { type: String, default: "month" },
    communityId: { type: String, default: null },
    analytics: Object,
    tab: { type: String, default: "links" },
});

const TABS = [
    { value: "links", label: "Links" },
    { value: "analytics", label: "Analytics" },
];

const PERIODS = [
    { value: "week", label: "Week" },
    { value: "month", label: "Month" },
    { value: "year", label: "Year" },
    { value: "all", label: "All" },
];

const STATUS_FILTERS = [
    { value: "all", label: "All" },
    { value: "pending", label: "Pending" },
    { value: "paid", label: "Paid" },
];

const activeTab = ref(props.tab ?? "links");
const statusFilter = ref("all");
const copied = ref(null);
const showPayoutModal = ref(false);
const showRequestModal = ref(false);
const showPixelModal = ref(false);
const requestingAffiliate = ref(null);
const pixelAffiliate = ref(null);
const requestForm = reactive({ amount: 0, processing: false });
const pixelForm = reactive({ facebook_pixel_id: '', tiktok_pixel_id: '', google_analytics_id: '' });

const payoutForm = reactive({
    payout_method: props.payoutMethod ?? "gcash",
    payout_details: props.payoutDetails ?? "",
});

const totalEligible = computed(() =>
    props.affiliates.reduce(
        (sum, a) => sum + (a.payout_request_status ? 0 : a.eligible_amount),
        0,
    ),
);

const filteredConversions = computed(() =>
    statusFilter.value === "all"
        ? props.analytics.conversions
        : props.analytics.conversions.filter(
              (c) => c.status === statusFilter.value,
          ),
);

const byCommMax = computed(() =>
    props.analytics.byComm.length
        ? Math.max(...props.analytics.byComm.map((r) => r.total))
        : 1,
);

function fmt(n) {
    return Number(n ?? 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

async function copy(url) {
    await navigator.clipboard.writeText(url);
    copied.value = url;
    setTimeout(() => {
        copied.value = null;
    }, 2000);
}

function requestAll() {
    router.post("/affiliates/payout-request/all", {}, { preserveScroll: true });
}

function openRequestModal(affiliate) {
    requestingAffiliate.value = affiliate;
    requestForm.amount = affiliate.eligible_amount;
    showRequestModal.value = true;
}

function submitRequest() {
    requestForm.processing = true;
    router.post(
        `/affiliates/${requestingAffiliate.value.id}/payout-request`,
        { amount: requestForm.amount },
        {
            onSuccess: () => {
                showRequestModal.value = false;
            },
            onFinish: () => {
                requestForm.processing = false;
            },
            preserveScroll: true,
        },
    );
}

function savePayout() {
    router.patch("/account/settings/payout", payoutForm, {
        onSuccess: () => {
            showPayoutModal.value = false;
        },
        preserveScroll: true,
    });
}

function openPixelModal(affiliate) {
    pixelAffiliate.value = affiliate;
    pixelForm.facebook_pixel_id   = affiliate.facebook_pixel_id   ?? '';
    pixelForm.tiktok_pixel_id     = affiliate.tiktok_pixel_id     ?? '';
    pixelForm.google_analytics_id = affiliate.google_analytics_id ?? '';
    showPixelModal.value = true;
}

function savePixels() {
    router.patch(`/affiliates/${pixelAffiliate.value.id}/pixels`, pixelForm, {
        onSuccess: () => {
            showPixelModal.value = false;
        },
        preserveScroll: true,
    });
}

function applyFilter(key, value) {
    const params = {
        period: props.period,
        community: props.communityId,
        tab: activeTab.value,
    };
    params[key] = value;
    if (!params.community) delete params.community;
    router.get("/my-affiliates", params, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>
