<template>
    <AppLayout title="Settings">
        <div class="flex flex-col md:flex-row gap-0 md:items-start -mx-4 sm:-mx-6 lg:-mx-8">

            <!-- Mobile: horizontal scrollable tab strip -->
            <div class="md:hidden border-b border-gray-200 bg-white sticky top-0 z-10">
                <nav class="flex gap-1 overflow-x-auto px-4 py-2 scrollbar-none">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        @click="activeTab = item.key"
                        class="shrink-0 px-3 py-1.5 text-sm rounded-lg font-medium transition-colors whitespace-nowrap"
                        :class="activeTab === item.key
                            ? 'bg-amber-100 text-amber-800'
                            : 'text-gray-700 hover:bg-gray-100'"
                    >
                        {{ item.label }}
                    </button>
                </nav>
            </div>

            <!-- Desktop: left sidebar nav -->
            <div class="hidden md:block w-52 shrink-0 py-2 px-2">
                <nav class="space-y-0.5">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        @click="activeTab = item.key"
                        class="w-full text-left px-4 py-2.5 text-sm rounded-xl font-medium transition-colors"
                        :class="activeTab === item.key
                            ? 'bg-amber-100 text-amber-800'
                            : 'text-gray-700 hover:bg-gray-100'"
                    >
                        {{ item.label }}
                    </button>
                </nav>
            </div>

            <!-- Main content -->
            <div class="flex-1 min-w-0 py-2 px-4 sm:px-6 md:pl-0 md:pr-6 lg:pr-8">
                <SettingsCommunities      v-if="activeTab === 'communities'"     :memberships="memberships" />
                <SettingsProfile          v-else-if="activeTab === 'profile'"    :profile-user="profileUser" :community-members="memberships" />
                <SettingsKyc              v-else-if="activeTab === 'kyc'"        :kyc="kyc" />
                <SettingsAccount          v-else-if="activeTab === 'account'"    :profile-user="profileUser" :timezone="timezone" />
                <SettingsAffiliates       v-else-if="activeTab === 'affiliates'" :affiliate-link="affiliateLink" />
                <SettingsNotifications    v-else-if="activeTab === 'notifications'" :notif-prefs="notifPrefs" :community-members="memberships" />
                <SettingsChat             v-else-if="activeTab === 'chat'"       :chat-prefs="chatPrefs" :community-members="memberships" />
                <SettingsPaymentMethods   v-else-if="activeTab === 'payment_methods'" />
                <SettingsPaymentHistory   v-else-if="activeTab === 'payment_history'" />
                <SettingsTheme            v-else-if="activeTab === 'theme'"      :theme="theme" />
                <SettingsPayouts          v-else-if="activeTab === 'payouts'"    :payout-method="payoutMethod" :payout-details="payoutDetails" :bank-name="bankName" :kyc="kyc" @go-to-kyc="activeTab = 'kyc'" />
                <SettingsCrypto           v-else-if="activeTab === 'crypto'"     :crypto-wallet="cryptoWallet" :crz-balance="crzBalance" />

                <!-- Fallback for unknown tabs -->
                <div v-else class="bg-white border border-gray-200 rounded-2xl p-16 text-center">
                    <p class="text-sm font-medium text-gray-700 capitalize mb-1">{{ activeTab }}</p>
                    <p class="text-xs text-gray-400">Coming soon</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

import SettingsCommunities    from './SettingsTabs/SettingsCommunities.vue';
import SettingsProfile        from './SettingsTabs/SettingsProfile.vue';
import SettingsKyc            from './SettingsTabs/SettingsKyc.vue';
import SettingsAccount        from './SettingsTabs/SettingsAccount.vue';
import SettingsAffiliates     from './SettingsTabs/SettingsAffiliates.vue';
import SettingsNotifications  from './SettingsTabs/SettingsNotifications.vue';
import SettingsChat           from './SettingsTabs/SettingsChat.vue';
import SettingsPaymentMethods from './SettingsTabs/SettingsPaymentMethods.vue';
import SettingsPaymentHistory from './SettingsTabs/SettingsPaymentHistory.vue';
import SettingsTheme          from './SettingsTabs/SettingsTheme.vue';
import SettingsPayouts        from './SettingsTabs/SettingsPayouts.vue';
import SettingsCrypto         from './SettingsTabs/SettingsCrypto.vue';

const props = defineProps({
    tab:           String,
    profileUser:   Object,
    memberships:   Array,
    affiliateLink: String,
    timezone:      String,
    theme:         String,
    notifPrefs:    Object,
    chatPrefs:     Object,
    payoutMethod:  String,
    payoutDetails: String,
    bankName:      String,
    cryptoWallet:  String,
    crzBalance:    Number,
    kyc:           Object,
});

const activeTab = ref(props.tab ?? 'communities');

const navItems = [
    { key: 'communities',      label: 'Communities' },
    { key: 'profile',          label: 'Profile' },
    { key: 'kyc',              label: 'Verification' },
    { key: 'affiliates',       label: 'Affiliates' },
    { key: 'payouts',          label: 'Payouts' },
    { key: 'account',          label: 'Account' },
    { key: 'notifications',    label: 'Notifications' },
    { key: 'chat',             label: 'Chat' },
    { key: 'payment_methods',  label: 'Payment methods' },
    { key: 'payment_history',  label: 'Payment history' },
    { key: 'theme',            label: 'Theme' },
    { key: 'crypto',           label: 'Crypto' },
];
</script>
