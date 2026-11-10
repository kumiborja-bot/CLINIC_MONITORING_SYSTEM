<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    students: Object,
    departments: Array,
    filters: Object,
});
const page = usePage();
const importReport = computed(() => page.props.flash?.import_report);

const search = ref(props.filters?.search || '');
const departmentId = ref(props.filters?.department_id || '');
const bmiCategory = ref(props.filters?.bmi_category || '');
const type = ref(props.filters?.type || '');

const applyFilters = () => {
    router.get(route('students.index'), {
        search: search.value || undefined,
        department_id: departmentId.value || undefined,
        bmi_category: bmiCategory.value || undefined,
        type: type.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const setType = (newType) => {
    type.value = newType;
    // Reset department filter when switching classifications to avoid empty results
    if (newType && newType !== 'college') {
        departmentId.value = '';
    }
    applyFilters();
};

let searchTimeout;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
});

const bmiColor = (category) => {
    const colors = {
        'Underweight': 'bg-yellow-100 text-yellow-800',
        'Normal': 'bg-green-100 text-green-800',
        'Overweight': 'bg-orange-100 text-orange-800',
        'Obese': 'bg-red-100 text-red-800',
    };
    return colors[category] || 'bg-gray-100 text-gray-600';
};

const deleteStudent = (id) => {
    if (confirm('Are you sure you want to delete this student?')) {
        router.delete(route('students.destroy', id));
    }
};

const fileInput = ref(null);
const importForm = useForm({
    file: null
});

const triggerFileInput = () => {
    fileInput.value.click();
};

const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file) {
        importForm.file = file;
        importForm.post(route('students.import-csv'), {
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
            },
            onError: (errors) => {
                alert(errors.file || 'An error occurred during import.');
            }
        });
    }
};

</script>

<template>
    <Head title="Students" />
    <AuthenticatedLayout>
        <template #header>Students</template>

        <div v-if="importReport" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-semibold">CSV import summary</p>
            <p class="mt-1 text-xs">Found {{ importReport.total_count || 0 }} record(s): imported {{ importReport.imported_count || 0 }} new, skipped {{ importReport.duplicate_count || 0 }} duplicate(s), failed {{ importReport.failed_count || 0 }}.</p>
            <ul v-if="importReport.errors?.length" class="mt-2 list-disc pl-5 text-xs"><li v-for="(error, index) in importReport.errors.slice(0, 10)" :key="index">{{ error }}</li></ul>
        </div>

        <!-- Top Bar -->
        <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex flex-wrap sm:flex-row items-center gap-3">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="search" type="text" placeholder="Search students..."
                        class="pl-10 pr-4 py-2 text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500 w-64" />
                </div>
                <select v-model="departmentId" @change="applyFilters"
                    class="text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                    <option value="">All Departments</option>
                    <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.code }}</option>
                </select>
                <select v-model="bmiCategory" @change="applyFilters"
                    class="text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                    <option value="">All BMI</option>
                    <option value="Underweight">Underweight</option>
                    <option value="Normal">Normal</option>
                    <option value="Overweight">Overweight</option>
                    <option value="Obese">Obese</option>
                </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Hidden File Input -->
                <input type="file" ref="fileInput" @change="handleFileUpload" accept=".csv" class="hidden" />

                <!-- Import CSV button (Dynamic File Upload) -->
                <button @click="triggerFileInput" :disabled="importForm.processing"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-navy-800 bg-navy-100 hover:bg-navy-200 transition-all duration-200 disabled:opacity-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Import CSV
                </button>

                <Link :href="route('students.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-[#F0A500] hover:bg-[#F4B842] shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Student
                </Link>
            </div>
        </div>

        <!-- Classification Tabs -->
        <div class="mb-6 flex gap-1 bg-white rounded-lg p-1 shadow-sm border border-gray-100 w-fit">
            <button @click="setType('')"
                :class="type === '' ? 'bg-[#132639] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-semibold rounded-md transition-all">
                All Students
            </button>
            <button @click="setType('college')"
                :class="type === 'college' ? 'bg-[#132639] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-semibold rounded-md transition-all">
                College
            </button>
            <button @click="setType('senior_high')"
                :class="type === 'senior_high' ? 'bg-[#132639] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-semibold rounded-md transition-all">
                Senior High
            </button>
            <button @click="setType('junior_high')"
                :class="type === 'junior_high' ? 'bg-[#132639] text-white shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                class="px-4 py-2 text-sm font-semibold rounded-md transition-all">
                Junior High
            </button>
        </div>

        <!-- Table -->
        <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
            <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-navy-800 text-white">
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">ID</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">Department</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">Course</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">Sex</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">BMI</th>
                                <th class="px-4 py-3 text-left font-medium text-xs uppercase tracking-wider">Allergies</th>
                                <th class="px-4 py-3 text-center font-medium text-xs uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ student.student_id_number }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ student.name }}</td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs font-medium bg-navy-100 text-navy-800">{{ student.department?.code }}</span></td>
                                <td class="px-4 py-3 text-gray-600 text-xs">{{ student.course?.name || '-' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ student.sex || '-' }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="student.medical_record?.bmi_category" :class="bmiColor(student.medical_record.bmi_category)"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium">
                                        {{ student.medical_record.bmi }} - {{ student.medical_record.bmi_category }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">No record</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="student.medical_record?.allergies || student.medical_record?.food_allergy || student.medical_record?.drug_allergy"
                                        class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">⚠ Has Allergies</span>
                                    <span v-else class="text-xs text-gray-400">None</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <Link :href="route('students.show', student.id)" class="px-2 py-1 text-xs font-semibold rounded hover:bg-[#0E4362] hover:text-white text-[#095D86] border border-[#095D86] transition-colors" title="View">
                                            View
                                        </Link>
                                        <Link :href="route('students.edit', student.id)" class="px-2 py-1 text-xs font-semibold rounded hover:bg-[#F0A500] hover:text-white text-[#F0A500] border border-[#F0A500] transition-colors" title="Edit">
                                            Edit
                                        </Link>
                                        <button @click="deleteStudent(student.id)" class="px-2 py-1 text-xs font-semibold rounded hover:bg-red-600 hover:text-white text-red-500 border border-red-500 transition-colors" title="Delete">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!students.data?.length">
                                <td colspan="8" class="px-4 py-12 text-center text-gray-400">No students found</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="students.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-xs text-gray-500">Showing {{ students.from }} to {{ students.to }} of {{ students.total }}</p>
                    <div class="flex gap-1">
                        <template v-for="link in students.links" :key="link.label">
                            <Link v-if="link.url" :href="link.url"
                                :class="link.active ? 'bg-navy-800 text-white' : 'bg-gray-50 text-gray-600 hover:bg-gray-100'"
                                class="px-3 py-1 rounded-lg text-xs font-medium transition-colors"
                                v-html="link.label" />
                            <span v-else class="px-3 py-1 text-xs text-gray-300" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
