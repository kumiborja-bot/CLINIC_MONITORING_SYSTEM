<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchablePatientSelect from '@/Components/SearchablePatientSelect.vue';
import SearchableMedicineSelect from '@/Components/SearchableMedicineSelect.vue';

const props = defineProps({
    appointments: Object,
    filters: Object,
    stats: Object,
    students: { type: Array, default: () => [] },
    personnel: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    providers: { type: Array, default: () => [] },
    medicines: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || 'all');
const selectedPatientType = ref(props.filters.patient_type || 'all');

const applyFilters = () => {
    router.get(
        route('appointments.index'),
        {
            search: search.value,
            status: selectedStatus.value,
            patient_type: selectedPatientType.value,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = 'all';
    selectedPatientType.value = 'all';
    applyFilters();
};

// Patient Selection in Modal
const modalPatientType = ref('student');

const activePatientList = computed(() => {
    return modalPatientType.value === 'student' ? props.students : props.personnel;
});

// Modals State
const showAppointmentModal = ref(false);
const isEditing = ref(false);
const editingAppointmentId = ref(null);

const showConsultationModal = ref(false);
const selectedAppointmentForConsultation = ref(null);

const showPrescriptionModal = ref(false);
const selectedAppointmentForPrescription = ref(null);

// Forms
const appointmentForm = useForm({
    patient_type: 'student',
    patient_id: '',
    email: '',
    appointment_date: '',
    clinic_location: 'Main Campus Clinic',
    assigned_provider_id: '',
    assigned_provider_name: '',
    reason_for_visit: '',
    status: 'scheduled',
    cancellation_reason: '',
    notes: '',
    send_email: true,
});

const consultationForm = useForm({
    diagnosis: '',
    treatment_given: '',
    medication_given: '',
    medicine_id: '',
    medicine_quantity: 1,
    notes: '',
    follow_up_date: '',
    send_followup_reminder: true,
});

const prescriptionForm = useForm({
    appointment_id: null,
    patient_type: '',
    patient_id: null,
    prescriber_name: '',
    medication_details: [
        { medicine_name: '', dosage: '', frequency: '', duration: '', instructions: '' }
    ],
    notes: '',
    send_email: true,
});

// Auto-fill patient details on search select
watch(() => appointmentForm.patient_id, (newId) => {
    if (!newId) return;
    const list = activePatientList.value || [];
    const found = list.find(p => p.id == newId);
    if (found) {
        appointmentForm.patient_type = modalPatientType.value;
        if (found.email) {
            appointmentForm.email = found.email;
        }
    }
});

const openCreateModal = () => {
    isEditing.value = false;
    editingAppointmentId.value = null;
    appointmentForm.reset();
    modalPatientType.value = 'student';
    appointmentForm.patient_type = 'student';
    appointmentForm.clinic_location = 'Main Campus Clinic';
    appointmentForm.status = 'scheduled';
    appointmentForm.send_email = true;
    showAppointmentModal.value = true;
};

const openEditModal = (apt) => {
    isEditing.value = true;
    editingAppointmentId.value = apt.id;
    const isStudent = apt.patient_type?.includes('Student');
    modalPatientType.value = isStudent ? 'student' : 'personnel';
    appointmentForm.patient_type = isStudent ? 'student' : 'personnel';
    appointmentForm.patient_id = apt.patient_id;
    appointmentForm.email = apt.email || apt.patient?.email || '';
    appointmentForm.appointment_date = apt.appointment_date ? apt.appointment_date.substring(0, 16) : '';
    appointmentForm.clinic_location = apt.clinic_location || 'Main Campus Clinic';
    appointmentForm.assigned_provider_id = apt.assigned_provider_id || '';
    appointmentForm.assigned_provider_name = apt.assigned_provider_name || '';
    appointmentForm.reason_for_visit = apt.reason_for_visit || '';
    appointmentForm.status = apt.status || 'scheduled';
    appointmentForm.cancellation_reason = apt.cancellation_reason || '';
    appointmentForm.notes = apt.notes || '';
    appointmentForm.send_email = true;
    showAppointmentModal.value = true;
};

const submitAppointment = () => {
    appointmentForm.patient_type = modalPatientType.value;
    if (isEditing.value) {
        appointmentForm.put(route('appointments.update', editingAppointmentId.value), {
            onSuccess: () => {
                showAppointmentModal.value = false;
                appointmentForm.reset();
            },
        });
    } else {
        appointmentForm.post(route('appointments.store'), {
            onSuccess: () => {
                showAppointmentModal.value = false;
                appointmentForm.reset();
            },
        });
    }
};

// Record Consultation Modal
const openConsultationModal = (apt) => {
    selectedAppointmentForConsultation.value = apt;
    consultationForm.reset();
    consultationForm.diagnosis = apt.reason_for_visit ? `Consultation: ${apt.reason_for_visit}` : '';
    consultationForm.send_followup_reminder = true;
    showConsultationModal.value = true;
};

const submitConsultation = () => {
    if (!selectedAppointmentForConsultation.value) return;
    consultationForm.post(route('appointments.record-consultation', selectedAppointmentForConsultation.value.id), {
        onSuccess: () => {
            showConsultationModal.value = false;
            consultationForm.reset();
        },
    });
};

// Prescription Modal
const openPrescriptionModal = (apt) => {
    selectedAppointmentForPrescription.value = apt;
    prescriptionForm.reset();
    prescriptionForm.appointment_id = apt.id;
    prescriptionForm.patient_type = apt.patient_type;
    prescriptionForm.patient_id = apt.patient_id;
    prescriptionForm.prescriber_name = apt.assigned_provider_name || '';
    prescriptionForm.medication_details = [
        { medicine_name: '', dosage: '', frequency: '', duration: '', instructions: '' }
    ];
    prescriptionForm.send_email = true;
    showPrescriptionModal.value = true;
};

const addMedicationRow = () => {
    prescriptionForm.medication_details.push({
        medicine_name: '', dosage: '', frequency: '', duration: '', instructions: ''
    });
};

const removeMedicationRow = (index) => {
    if (prescriptionForm.medication_details.length > 1) {
        prescriptionForm.medication_details.splice(index, 1);
    }
};

const submitPrescription = () => {
    prescriptionForm.post(route('prescriptions.store'), {
        onSuccess: () => {
            showPrescriptionModal.value = false;
            prescriptionForm.reset();
        },
    });
};

// Quick Actions
const sendReminder = (apt, isFollowUp = false) => {
    router.post(route('appointments.send-reminder', apt.id), { is_follow_up: isFollowUp });
};

const deleteAppointment = (apt) => {
    if (confirm('Are you sure you want to delete this appointment?')) {
        router.delete(route('appointments.destroy', apt.id));
    }
};

// Formatting helpers
const formatDate = (datetimeStr) => {
    if (!datetimeStr) return 'N/A';
    const date = new Date(datetimeStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'scheduled': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'attended':
        case 'completed': return 'bg-green-100 text-green-800 border-green-200';
        case 'cancelled': return 'bg-red-100 text-red-800 border-red-200';
        case 'rescheduled': return 'bg-purple-100 text-purple-800 border-purple-200';
        default: return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};
</script>

<template>
    <Head title="Visit Monitoring" />

    <AuthenticatedLayout>
        <template #header>
            Visit Monitoring
        </template>

        <div class="space-y-6 min-w-0 overflow-x-hidden">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200/80 shadow-sm">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Clinic Visit Monitoring</h2>
                    <p class="text-sm text-gray-500 mt-1">Schedule, monitor, and complete clinic visits, consultations, and follow-up care.</p>
                </div>
                <div>
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#095D86] text-white text-sm font-semibold rounded-lg hover:bg-[#074766] transition-colors shadow-sm"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        New Visit
                    </button>
                </div>
            </div>

            <!-- Stats Counter Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <div class="bg-white p-4 rounded-xl border border-gray-200/80 shadow-sm">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Appointments</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ stats.total }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-yellow-200/80 bg-yellow-50/20 shadow-sm">
                    <p class="text-xs font-semibold text-yellow-700 uppercase tracking-wider">Pending Requests</p>
                    <p class="text-2xl font-bold text-yellow-800 mt-1">{{ stats.pending }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-blue-200/80 bg-blue-50/20 shadow-sm">
                    <p class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Scheduled</p>
                    <p class="text-2xl font-bold text-blue-800 mt-1">{{ stats.scheduled }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-green-200/80 bg-green-50/20 shadow-sm">
                    <p class="text-xs font-semibold text-green-700 uppercase tracking-wider">Attended / Completed</p>
                    <p class="text-2xl font-bold text-green-800 mt-1">{{ stats.attended }}</p>
                </div>
                <div class="bg-white p-4 rounded-xl border border-red-200/80 bg-red-50/20 shadow-sm">
                    <p class="text-xs font-semibold text-red-700 uppercase tracking-wider">Cancelled</p>
                    <p class="text-2xl font-bold text-red-800 mt-1">{{ stats.cancelled }}</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white p-4 rounded-xl border border-gray-200/80 shadow-sm flex flex-col lg:flex-row items-stretch lg:items-center gap-4 min-w-0">
                <div class="flex-1 w-full relative">
                    <input
                        v-model="search"
                        @keyup.enter="applyFilters"
                        type="text"
                        placeholder="Search patient, location, provider, or reason..."
                        class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <div class="w-full md:w-48">
                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="w-full py-2 text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                    >
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="rescheduled">Rescheduled</option>
                        <option value="attended">Attended</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="w-full md:w-48">
                    <select
                        v-model="selectedPatientType"
                        @change="applyFilters"
                        class="w-full py-2 text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                    >
                        <option value="all">All Patient Types</option>
                        <option value="student">Students</option>
                        <option value="personnel">Personnel</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-colors"
                    >
                        Filter
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-3 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Appointments Table -->
            <div class="bg-white rounded-xl border border-gray-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-3.5">Patient Details</th>
                                <th class="px-6 py-3.5">Date & Time</th>
                                <th class="px-6 py-3.5">Location & Provider</th>
                                <th class="px-6 py-3.5">Reason for Visit</th>
                                <th class="px-6 py-3.5">Status</th>
                                <th class="px-6 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="apt in appointments.data" :key="apt.id" class="hover:bg-gray-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-gray-900">{{ apt.patient?.name || 'N/A' }}</div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span
                                            class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded"
                                            :class="apt.patient_type?.includes('Student') ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                        >
                                            {{ apt.patient_type?.includes('Student') ? 'Student' : 'Personnel' }}
                                        </span>
                                        <span class="text-xs text-gray-500 font-mono">
                                            {{ apt.patient?.student_id_number || apt.patient?.employee_id || '' }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ apt.email || apt.patient?.email || 'No email registered' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="font-semibold text-gray-800">{{ formatDate(apt.appointment_date) }}</div>
                                    <div v-if="apt.reminder_sent" class="text-[11px] text-green-600 font-medium mt-0.5 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Reminder Sent
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-800">{{ apt.clinic_location }}</div>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        Provider: {{ apt.assigned_provider_name || 'Unassigned' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-700 max-w-xs truncate">{{ apt.reason_for_visit || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2.5 py-1 text-xs font-bold rounded-full border capitalize"
                                        :class="getStatusBadgeClass(apt.status)"
                                    >
                                        {{ apt.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Record Consultation Button -->
                                        <button
                                            v-if="['scheduled', 'pending', 'attended'].includes(apt.status)"
                                            @click="openConsultationModal(apt)"
                                            title="Record Consultation & Health Record"
                                            class="px-2.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded border border-emerald-200 transition-colors"
                                        >
                                            Consultation
                                        </button>

                                        <!-- Issue Prescription Button -->
                                        <button
                                            v-if="['scheduled', 'attended', 'completed'].includes(apt.status)"
                                            @click="openPrescriptionModal(apt)"
                                            title="Issue Digital Prescription via Email"
                                            class="px-2.5 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-semibold rounded border border-indigo-200 transition-colors"
                                        >
                                            Prescription
                                        </button>

                                        <!-- Send Email Reminder -->
                                        <button
                                            @click="sendReminder(apt, false)"
                                            title="Send Email Reminder"
                                            class="p-1.5 text-gray-500 hover:text-[#095D86] hover:bg-gray-100 rounded transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                        </button>

                                        <!-- Edit Modal Button -->
                                        <button
                                            @click="openEditModal(apt)"
                                            title="Edit Appointment"
                                            class="p-1.5 text-gray-500 hover:text-blue-600 hover:bg-gray-100 rounded transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>

                                        <!-- Delete Button -->
                                        <button
                                            @click="deleteAppointment(apt)"
                                            title="Delete Appointment"
                                            class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-gray-100 rounded transition-colors"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="appointments.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    No clinic appointments found matching the search criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="appointments.links && appointments.links.length > 3" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                        Showing {{ appointments.from }} to {{ appointments.to }} of {{ appointments.total }} appointments
                    </div>
                    <div class="flex gap-1">
                        <component
                            v-for="(link, i) in appointments.links"
                            :key="i"
                            :is="link.url ? 'Link' : 'span'"
                            :href="link.url"
                            v-html="link.label"
                            class="px-3 py-1 text-xs rounded border"
                            :class="[
                                link.active ? 'bg-[#095D86] text-white border-[#095D86]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                                !link.url ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Schedule / Edit Appointment Modal -->
        <div v-if="showAppointmentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-xl max-w-xl w-full p-6 shadow-xl border border-gray-200 my-8 overflow-visible">
                <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
                    <h3 class="text-lg font-bold text-gray-900">
                        {{ isEditing ? 'Edit Clinic Appointment' : 'Schedule New Appointment' }}
                    </h3>
                    <button @click="showAppointmentModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitAppointment" class="space-y-4">
                    <!-- Searchable Patient Selection -->
                    <div v-if="!isEditing" class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Patient Type *</label>
                                <select
                                    v-model="modalPatientType"
                                    @change="appointmentForm.patient_id = ''"
                                    class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                                >
                                    <option value="student">Student</option>
                                    <option value="personnel">Personnel</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Search Patient *</label>
                                <SearchablePatientSelect
                                    v-model="appointmentForm.patient_id"
                                    :patients="activePatientList"
                                    :departments="departments"
                                    :patient-type="modalPatientType"
                                    placeholder="Search student/personnel by name or ID..."
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Patient Notification Email</label>
                        <input
                            v-model="appointmentForm.email"
                            type="email"
                            placeholder="patient@example.com"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        />
                    </div>

                    <!-- Date & Time -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Appointment Date & Time</label>
                            <input
                                v-model="appointmentForm.appointment_date"
                                type="datetime-local"
                                required
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Clinic Location</label>
                            <input
                                v-model="appointmentForm.clinic_location"
                                type="text"
                                required
                                placeholder="Main Campus Clinic"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            />
                        </div>
                    </div>

                    <!-- Provider & Status -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Assigned Healthcare Provider</label>
                            <select
                                v-model="appointmentForm.assigned_provider_id"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            >
                                <option value="">-- Select Provider --</option>
                                <option v-for="user in providers" :key="user.id" :value="user.id">
                                    {{ user.name }} ({{ user.role }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Appointment Status</label>
                            <select
                                v-model="appointmentForm.status"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            >
                                <option value="scheduled">Scheduled</option>
                                <option value="pending">Pending Request</option>
                                <option value="rescheduled">Rescheduled</option>
                                <option value="attended">Attended</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <!-- Cancellation Reason if cancelled -->
                    <div v-if="appointmentForm.status === 'cancelled'">
                        <label class="block text-xs font-bold text-red-700 uppercase mb-1">Reason for Cancellation</label>
                        <input
                            v-model="appointmentForm.cancellation_reason"
                            type="text"
                            placeholder="State reason for cancellation..."
                            class="w-full text-sm rounded-lg border-red-300 focus:border-red-500 focus:ring-red-500"
                        />
                    </div>

                    <!-- Reason for Visit -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Reason for Visit / Symptoms</label>
                        <textarea
                            v-model="appointmentForm.reason_for_visit"
                            rows="2"
                            placeholder="Describe purpose of visit..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        ></textarea>
                    </div>

                    <!-- Email Checkbox -->
                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                        <input
                            v-model="appointmentForm.send_email"
                            type="checkbox"
                            id="send_email_check"
                            class="rounded border-gray-300 text-[#095D86] focus:ring-[#095D86]"
                        />
                        <label for="send_email_check" class="text-xs font-semibold text-gray-700 cursor-pointer">
                            Send Email Notification to Patient (Confirmation / Reschedule / Cancellation)
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button
                            type="button"
                            @click="showAppointmentModal = false"
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="appointmentForm.processing"
                            class="px-4 py-2 bg-[#095D86] hover:bg-[#074766] text-white text-sm font-semibold rounded-lg shadow-sm"
                        >
                            {{ isEditing ? 'Save Changes' : 'Schedule Appointment' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Record Consultation Modal -->
        <div v-if="showConsultationModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-xl max-w-xl w-full p-6 shadow-xl border border-gray-200 my-8">
                <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Record Consultation</h3>
                        <p class="text-xs text-gray-500">Patient: {{ selectedAppointmentForConsultation?.patient?.name }}</p>
                    </div>
                    <button @click="showConsultationModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitConsultation" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Diagnosis / Assessment</label>
                        <input
                            v-model="consultationForm.diagnosis"
                            type="text"
                            required
                            placeholder="Enter clinical diagnosis..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Treatment Given</label>
                        <textarea
                            v-model="consultationForm.treatment_given"
                            rows="2"
                            placeholder="Details of treatment administered..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        ></textarea>
                    </div>

                    <!-- Medicine from Inventory -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-blue-50/50 rounded-lg border border-blue-100 overflow-visible">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Medicine from Inventory</label>
                            <SearchableMedicineSelect
                                v-model="consultationForm.medicine_id"
                                :medicines="medicines"
                                placeholder="Search stocked medicine by name or supplier..."
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Quantity</label>
                            <input
                                v-model="consultationForm.medicine_quantity"
                                type="number"
                                min="1"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Medication Notes / Additional Rx</label>
                        <input
                            v-model="consultationForm.medication_given"
                            type="text"
                            placeholder="e.g. Additional dosage instructions or non-inventory meds..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Follow-up Date (Optional)</label>
                            <input
                                v-model="consultationForm.follow_up_date"
                                type="date"
                                class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                            />
                        </div>
                        <div class="flex items-center pt-5">
                            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                                <input
                                    v-model="consultationForm.send_followup_reminder"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-[#095D86] focus:ring-[#095D86]"
                                />
                                Send Follow-up Reminder Email
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Consultation Notes</label>
                        <textarea
                            v-model="consultationForm.notes"
                            rows="2"
                            placeholder="Additional medical observations..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button
                            type="button"
                            @click="showConsultationModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="consultationForm.processing"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg shadow-sm"
                        >
                            Record Consultation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. Issue Digital Prescription Modal -->
        <div v-if="showPrescriptionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm overflow-y-auto">
            <div class="bg-white rounded-xl max-w-2xl w-full p-6 shadow-xl border border-gray-200 my-8">
                <div class="flex items-center justify-between border-b border-gray-200 pb-4 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">Issue Digital Medical Prescription</h3>
                        <p class="text-xs text-gray-500">Patient: {{ selectedAppointmentForPrescription?.patient?.name }}</p>
                    </div>
                    <button @click="showPrescriptionModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitPrescription" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Prescribing Healthcare Provider</label>
                        <input
                            v-model="prescriptionForm.prescriber_name"
                            type="text"
                            placeholder="Dr. John Doe / Nurse Jane Smith"
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        />
                    </div>

                    <!-- Dynamic Medication Rows -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-xs font-bold text-gray-700 uppercase">Prescribed Items</label>
                            <button
                                type="button"
                                @click="addMedicationRow"
                                class="text-xs font-semibold text-[#095D86] hover:underline flex items-center gap-1"
                            >
                                + Add Medication
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div
                                v-for="(med, idx) in prescriptionForm.medication_details"
                                :key="idx"
                                class="p-3 bg-gray-50 rounded-lg border border-gray-200 relative"
                            >
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-2">
                                    <input
                                        v-model="med.medicine_name"
                                        type="text"
                                        required
                                        placeholder="Medicine Name (e.g. Amoxicillin)"
                                        class="text-xs rounded border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                                    />
                                    <input
                                        v-model="med.dosage"
                                        type="text"
                                        placeholder="Dosage (e.g. 500mg)"
                                        class="text-xs rounded border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                                    />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <input
                                        v-model="med.frequency"
                                        type="text"
                                        placeholder="Frequency (e.g. Every 8 hours)"
                                        class="text-xs rounded border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                                    />
                                    <input
                                        v-model="med.duration"
                                        type="text"
                                        placeholder="Duration (e.g. 7 days)"
                                        class="text-xs rounded border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                                    />
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model="med.instructions"
                                            type="text"
                                            placeholder="Instructions (e.g. Take after meals)"
                                            class="text-xs rounded border-gray-300 focus:border-[#095D86] focus:ring-[#095D86] flex-1"
                                        />
                                        <button
                                            v-if="prescriptionForm.medication_details.length > 1"
                                            type="button"
                                            @click="removeMedicationRow(idx)"
                                            class="text-red-500 hover:text-red-700 p-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 uppercase mb-1">Special Precautions / Remarks</label>
                        <textarea
                            v-model="prescriptionForm.notes"
                            rows="2"
                            placeholder="Additional doctor's advice..."
                            class="w-full text-sm rounded-lg border-gray-300 focus:border-[#095D86] focus:ring-[#095D86]"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                        <input
                            v-model="prescriptionForm.send_email"
                            type="checkbox"
                            id="send_rx_email"
                            class="rounded border-gray-300 text-[#095D86] focus:ring-[#095D86]"
                        />
                        <label for="send_rx_email" class="text-xs font-semibold text-gray-700 cursor-pointer">
                            Send Digital Prescription directly to Patient Email
                        </label>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                        <button
                            type="button"
                            @click="showPrescriptionModal = false"
                            class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="prescriptionForm.processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm"
                        >
                            Issue & Send Prescription
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
