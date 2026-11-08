<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    stats: Object,
    commonIllnesses: Array,
    topMedicines: Array,
    recentTreatments: Array,
    lowStockMedicines: Array,
    departments: Array,
    filters: Object,
    overweightPersonnel: Array,
    bmiBreakdown: Object,
    expiringMedicines: Array,
});

const selectedDept = ref(props.filters?.department_id || '');
const illnessChart = ref(null);
const medicineChart = ref(null);
const bmiChart = ref(null);
const showOverweight = ref(false);

const filterByDepartment = () => {
    router.get(route('dashboard'), { department_id: selectedDept.value || undefined }, { preserveState: true });
};

onMounted(() => {
    // Illness chart
    if (props.commonIllnesses?.length) {
        new Chart(illnessChart.value, {
            type: 'doughnut',
            data: {
                labels: props.commonIllnesses.map(i => i.diagnosis),
                datasets: [{
                    data: props.commonIllnesses.map(i => i.total),
                    backgroundColor: ['#1b2a4a', '#d4a017', '#2d4a7a', '#f0c040', '#3b5a9f', '#b8860b', '#5973ae', '#9a6f09', '#778cbd', '#674a0f'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } } },
            },
        });
    }

    // Medicine chart
    if (props.topMedicines?.length) {
        new Chart(medicineChart.value, {
            type: 'bar',
            data: {
                labels: props.topMedicines.map(m => m.medicine?.name || 'Unknown'),
                datasets: [{
                    label: 'Total Dispensed',
                    data: props.topMedicines.map(m => m.total),
                    backgroundColor: '#d4a017',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                    x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                },
            },
        });
    }
    if (bmiChart.value) new Chart(bmiChart.value, { type: 'doughnut', data: { labels: Object.keys(props.bmiBreakdown || {}), datasets: [{ data: Object.values(props.bmiBreakdown || {}), backgroundColor: ['#f97316','#ef4444','#22c55e','#eab308','#94a3b8'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } } });
});

const statCards = [
    { label: 'Total Students', key: 'totalStudents', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', color: 'bg-[#132639]' },
    { label: 'Total Personnel', key: 'totalPersonnel', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', color: 'bg-[#0E4362]' },
    { label: 'Medicines in Stock', key: 'totalMedicines', icon: 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z', color: 'bg-[#095D86]' },
    { label: 'Treatments Today', key: 'treatmentsToday', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', color: 'bg-[#F0A500]' },
];
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <template #header>Dashboard</template>

        <!-- Department Filter -->
        <div class="mb-6 flex items-center gap-3">
            <select v-model="selectedDept" @change="filterByDepartment"
                class="rounded-lg border-gray-200 text-sm focus:ring-mustard-500 focus:border-mustard-500">
                <option value="">All Departments</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.code }} - {{ dept.name }}</option>
            </select>
        </div>

        <!-- Stat Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div v-for="card in statCards" :key="card.key"
                class="rounded-xl p-5 text-white shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-0.5"
                :class="card.color">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ card.label }}</p>
                        <p class="text-3xl font-bold mt-1">{{ stats[card.key] || 0 }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <button type="button" @click="showOverweight = !showOverweight" class="text-left bg-orange-50 border border-orange-200 rounded-xl p-5 hover:bg-orange-100 transition-colors"><p class="text-sm font-semibold text-orange-800">Overweight / Obese Personnel</p><p class="text-3xl font-bold text-orange-700 mt-1">{{ overweightPersonnel?.length || 0 }}</p><p class="text-xs text-orange-600 mt-1">Click for breakdown</p></button>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 lg:col-span-2" v-if="showOverweight"><div v-if="overweightPersonnel?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2"><div v-for="person in overweightPersonnel" :key="person.id" class="rounded-lg bg-orange-50 px-3 py-2"><p class="text-sm font-semibold text-gray-800">{{ person.name }}</p><p class="text-xs text-gray-500">{{ person.department || 'No department' }} · BMI {{ person.bmi }} ({{ person.category }})</p></div></div><p v-else class="text-sm text-gray-400">No overweight personnel recorded.</p></div>
            <div v-else class="bg-white rounded-xl shadow-sm border border-gray-100 p-4"><h3 class="text-sm font-semibold text-gray-800 mb-2">Personnel BMI Breakdown</h3><div class="h-36"><canvas ref="bmiChart"></canvas></div></div>
        </div>
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4"><h3 class="text-sm font-semibold text-amber-800">Medicine Expiry Alert — within 2 months</h3><div v-if="expiringMedicines?.length" class="mt-2 flex flex-wrap gap-2"><span v-for="m in expiringMedicines" :key="m.id" class="rounded-full bg-white px-3 py-1 text-xs font-medium text-amber-700">{{ m.name }} · {{ m.expiry_date }}</span></div><p v-else class="mt-2 text-sm text-amber-700">No medicines are expiring within the next 2 months.</p></div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Common Illnesses Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-mustard-500"></span>
                    Most Common Illnesses
                </h3>
                <div class="h-64" v-if="commonIllnesses?.length">
                    <canvas ref="illnessChart"></canvas>
                </div>
                <div v-else class="h-64 flex items-center justify-center text-gray-400 text-sm">
                    No treatment data yet
                </div>
            </div>

            <!-- Top Dispensed Medicines -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-navy-700"></span>
                    Most Dispensed Medicines
                </h3>
                <div class="h-64" v-if="topMedicines?.length">
                    <canvas ref="medicineChart"></canvas>
                </div>
                <div v-else class="h-64 flex items-center justify-center text-gray-400 text-sm">
                    No dispensing data yet
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Treatments -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Recent Treatments</h3>
                    <Link :href="route('treatments.index')" class="text-xs text-mustard-600 hover:text-mustard-700 font-medium">View All →</Link>
                </div>
                <div v-if="recentTreatments?.length" class="divide-y divide-gray-50">
                    <div v-for="t in recentTreatments" :key="t.id" class="px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ t.patient_name }}</p>
                                <p class="text-xs text-gray-500">{{ t.diagnosis }}</p>
                            </div>
                            <div class="text-right">
                                <span :class="t.patient_type_label === 'Student' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700'"
                                    class="text-xs px-2 py-0.5 rounded-full font-medium">{{ t.patient_type_label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-10 text-center text-gray-400 text-sm">No treatments recorded yet</div>
            </div>

            <!-- Low Stock Medicines -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">⚠️ Low Stock Medicines</h3>
                </div>
                <div v-if="lowStockMedicines?.length" class="divide-y divide-gray-50">
                    <div v-for="m in lowStockMedicines" :key="m.id" class="px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-800">{{ m.name }}</p>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-medium">
                                {{ m.stock_quantity }} {{ m.unit }} left
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-10 text-center text-gray-400 text-sm">All medicines are well-stocked 👍</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
