<script setup>
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import OfflineStatusBanner from '@/Components/OfflineStatusBanner.vue';

const showSidebar = ref(true);
const showMobileMenu = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);

const navigation = [
    { name: 'Dashboard', route: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Students', route: 'students.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
    { name: 'Personnel', route: 'personnel.index', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
    { name: 'Appointments', route: 'appointments.index', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { name: 'Medicine & Stocks Logbook', route: 'medicines.index', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z' },
    { name: 'Treatments', route: 'treatments.index', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    { name: 'Reports', route: 'reports.index', icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { name: 'Master Data', route: 'master-data.index', icon: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' },
];

const isActive = (routeName) => {
    try {
        if (routeName === 'master-data.index') {
            return route().current('master-data.index') || route().current('departments.*') || route().current('dss.*');
        }
        return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
    } catch {
        return false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside
            :class="[showSidebar ? 'w-64' : 'w-20', 'hidden lg:flex']"
            class="fixed inset-y-0 left-0 z-30 flex-col bg-[#132639] text-white transition-all duration-300 ease-in-out"
        >
            <!-- Logo -->
            <div class="flex items-center px-4 border-b border-white/10" :class="showSidebar ? 'py-4' : 'py-3'">
                <div class="flex items-center gap-3 overflow-hidden">
                    <img src="/images/logo.jpg" alt="MMACI Logo" class="flex-shrink-0 rounded-full object-cover" :class="showSidebar ? 'w-12 h-12' : 'w-10 h-10'" />
                    <div v-if="showSidebar" class="transition-opacity duration-200 min-w-0">
                        <h1 class="text-xs font-bold leading-tight text-mustard-400 uppercase tracking-wide">Merchant Marine Academy of Caraga, Inc.</h1>
                        <p class="text-[10px] text-gray-400 mt-0.5">Health Monitoring System</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="route(item.route)"
                    :class="[
                        isActive(item.route)
                            ? 'bg-mustard-500/20 text-mustard-400 border-l-3 border-mustard-400'
                            : 'text-gray-300 hover:bg-white/5 hover:text-white border-l-3 border-transparent',
                    ]"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200 group"
                >
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span v-if="showSidebar" class="truncate">{{ item.name }}</span>
                </Link>
            </nav>

            <!-- Toggle -->
            <div class="p-3 border-t border-white/10">
                <button @click="showSidebar = !showSidebar"
                    class="w-full flex items-center justify-center p-2 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"
                        :class="{ 'rotate-180': !showSidebar }">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                    </svg>
                </button>
            </div>
        </aside>

        <!-- Mobile sidebar overlay -->
        <div v-if="showMobileMenu" class="fixed inset-0 bg-black/60 z-40 lg:hidden" @click="showMobileMenu = false"></div>
        <aside v-if="showMobileMenu"
            class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col lg:hidden text-white transition-transform duration-300 bg-[#132639]">
            <div class="flex items-center justify-between py-4 px-4 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <img src="/images/logo.jpg" alt="MMACI Logo" class="w-12 h-12 rounded-full object-cover flex-shrink-0" />
                    <div>
                        <h1 class="text-xs font-bold text-mustard-400 uppercase tracking-wide leading-tight">Merchant Marine Academy of Caraga, Inc.</h1>
                        <p class="text-[10px] text-gray-400 mt-0.5">Clinic Health Monitoring System</p>
                    </div>
                </div>
                <button @click="showMobileMenu = false" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 py-4 px-3 space-y-1 overflow-y-auto">
                <Link v-for="item in navigation" :key="item.name" :href="route(item.route)"
                    @click="showMobileMenu = false"
                    :class="[isActive(item.route) ? 'bg-mustard-500/20 text-mustard-400' : 'text-gray-300 hover:bg-white/5 hover:text-white']"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-200">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span>{{ item.name }}</span>
                </Link>
            </nav>
        </aside>

        <!-- Main content -->
        <div :class="[showSidebar ? 'lg:ml-64' : 'lg:ml-20']" class="flex-1 flex flex-col min-h-screen transition-all duration-300">
            <!-- Top bar -->
            <header class="sticky top-0 z-20 bg-white/80 backdrop-blur-md border-b border-gray-200/60 h-16 flex items-center justify-between px-4 lg:px-6">
                <div class="flex items-center gap-3">
                    <button @click="showMobileMenu = true" class="lg:hidden text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">
                            <slot name="header">Dashboard</slot>
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <!-- User dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold bg-[#095D86]">
                                    {{ user.name.charAt(0).toUpperCase() }}
                                </div>
                                <span class="hidden md:inline">{{ user.name }}</span>
                                <span class="hidden md:inline px-2 py-0.5 text-xs rounded-full"
                                      :class="user.role === 'admin' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                                    {{ user.role }}
                                </span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-6">
                <!-- Offline status banner -->
                <OfflineStatusBanner />
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="mb-4 px-4 py-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.error && !$page.props.flash?.import_report" class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    {{ $page.props.flash.error }}
                </div>
                <slot />
            </main>
        </div>
    </div>
</template>
