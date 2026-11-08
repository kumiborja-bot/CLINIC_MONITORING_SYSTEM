<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { useOfflineQueue } from '@/utils/offlineQueue';
import axios from 'axios';

const props = defineProps({
    departments: Array,
    student: { type: Object, default: null },
});

const isEdit = computed(() => !!props.student);

const form = useForm({
    student_id_number: props.student?.student_id_number || '',
    name: props.student?.name || '',
    birthdate: props.student?.birthdate ? props.student.birthdate.split('T')[0] : '',
    address: props.student?.address || '',
    age: props.student?.age || '',
    sex: props.student?.sex || '',
    civil_status: props.student?.civil_status || '',
    religion: props.student?.religion || '',
    department_id: props.student?.department_id || '',
    course_id: props.student?.course_id || '',
    contact_no: props.student?.contact_no || '',
    year_level: props.student?.year_level || '',
    emergency_contact_person: props.student?.emergency_contact_person || '',
    emergency_contact_relationship: props.student?.emergency_contact_relationship || '',
    emergency_contact_address: props.student?.emergency_contact_address || '',
    emergency_contact_no: props.student?.emergency_contact_no || '',
    // Medical Record
    examination_date: props.student?.medical_record?.examination_date ? props.student.medical_record.examination_date.split('T')[0] : '',
    height_cm: props.student?.medical_record?.height_cm || '',
    weight_kg: props.student?.medical_record?.weight_kg || '',
    pulse_rate: props.student?.medical_record?.pulse_rate || '',
    respiratory_rate: props.student?.medical_record?.respiratory_rate || '',
    blood_pressure: props.student?.medical_record?.blood_pressure || '',
    oxygen_saturation: props.student?.medical_record?.oxygen_saturation || '',
    smoker: props.student?.medical_record?.smoker || false,
    alcoholic: props.student?.medical_record?.alcoholic || false,
    allergies: props.student?.medical_record?.allergies || '',
    food_allergy: props.student?.medical_record?.food_allergy || false,
    drug_allergy: props.student?.medical_record?.drug_allergy || false,
    past_medical_history: props.student?.medical_record?.past_medical_history || '',
    family_hpn: props.student?.medical_record?.family_hpn || false,
    family_cancer: props.student?.medical_record?.family_cancer || false,
    family_asthma: props.student?.medical_record?.family_asthma || false,
    family_dm: props.student?.medical_record?.family_dm || false,
    physician_name: props.student?.medical_record?.physician_name || '',
    physician_license_no: props.student?.medical_record?.physician_license_no || '',
    physician_ptr: props.student?.medical_record?.physician_ptr || '',
    license_expiry_date: props.student?.medical_record?.license_expiry_date ? props.student.medical_record.license_expiry_date.split('T')[0] : '',
});

// Auto BMI calculation
const bmi = computed(() => {
    if (form.height_cm > 0 && form.weight_kg > 0) {
        const heightM = form.height_cm / 100;
        return (form.weight_kg / (heightM * heightM)).toFixed(2);
    }
    return null;
});

const bmiCategory = computed(() => {
    if (!bmi.value) return '';
    const v = parseFloat(bmi.value);
    if (v < 18.5) return 'Underweight';
    if (v < 25) return 'Normal';
    if (v < 30) return 'Overweight';
    return 'Obese';
});

const bmiColor = computed(() => {
    const c = { 'Underweight': 'text-yellow-600 bg-yellow-50', 'Normal': 'text-green-600 bg-green-50', 'Overweight': 'text-orange-600 bg-orange-50', 'Obese': 'text-red-600 bg-red-50' };
    return c[bmiCategory.value] || 'text-gray-500 bg-gray-50';
});

const bloodPressureStatus = computed(() => {
    const match = String(form.blood_pressure || '').match(/(\d{2,3})\s*\/\s*(\d{2,3})/);
    if (!match) return '';
    const systolic = Number(match[1]); const diastolic = Number(match[2]);
    if (systolic >= 140 || diastolic >= 90) return 'High blood pressure';
    if (systolic < 90 || diastolic < 60) return 'Low blood pressure';
    return 'Normal blood pressure';
});
const bloodPressureColor = computed(() => ({ 'High blood pressure': 'text-red-700 bg-red-50', 'Low blood pressure': 'text-blue-700 bg-blue-50', 'Normal blood pressure': 'text-green-700 bg-green-50' }[bloodPressureStatus.value] || 'text-gray-500 bg-gray-50'));

// Courses for selected department
const availableCourses = computed(() => {
    const dept = props.departments.find(d => d.id == form.department_id);
    return dept?.courses || [];
});

watch(() => form.department_id, () => {
    form.course_id = '';
});

// Auto age from birthdate
watch(() => form.birthdate, (val) => {
    if (val) {
        const today = new Date();
        const birth = new Date(val);
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        form.age = age;
    }
});

const { isOnline, addToQueue } = useOfflineQueue();
const offlineQueued = ref(false);

const submit = async () => {
    if (isEdit.value) {
        form.put(route('students.update', props.student.id));
    } else if (isOnline.value) {
        form.post(route('students.store'));
    } else {
        await addToQueue('student', route('students.store'), form.data());
        offlineQueued.value = true;
        form.reset();
        setTimeout(() => { offlineQueued.value = false; }, 4000);
    }
};

const searchingCsv = ref(false);
const searchCsv = async () => {
    if (!form.student_id_number) {
        alert('Please enter a Student ID Number to search.');
        return;
    }
    searchingCsv.value = true;
    try {
        const { data } = await axios.get('/api/csv-patients', { params: { q: form.student_id_number } });
        if (data && data.length > 0) {
            const match = data[0];
            form.name = match.name || '';
            // Optionally map other fields if they exist in CSV
            alert('Student found in CSV and data automatically filled.');
        } else {
            alert('Student not found in CSV.');
        }
    } catch (e) {
        console.error('Failed to search CSV', e);
        alert('Error searching CSV.');
    } finally {
        searchingCsv.value = false;
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Student' : 'Add Student'" />
    <AuthenticatedLayout>
        <template #header>{{ isEdit ? 'Edit Student' : 'Add New Student' }}</template>

        <!-- Offline queued success message -->
        <transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-all duration-200 ease-in" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
            <div v-if="offlineQueued" class="mb-4 px-4 py-3 rounded-xl bg-amber-50 border border-amber-200/60 flex items-center gap-3 shadow-sm">
                <div class="w-9 h-9 rounded-lg bg-amber-500/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-amber-700">Student queued offline</p>
                    <p class="text-xs text-amber-500/80">It will sync automatically when you reconnect.</p>
                </div>
            </div>
        </transition>

        <form @submit.prevent="submit" class="max-w-5xl space-y-6">
            <!-- Student Data Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-navy-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Student Data</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Student ID Number *</label>
                        <div class="flex gap-2">
                            <input v-model="form.student_id_number" type="text" required class="flex-1 text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                            <button type="button" @click="searchCsv" :disabled="searchingCsv" class="px-3 py-2 bg-navy-800 text-white rounded-lg text-xs font-semibold hover:bg-navy-700 disabled:opacity-50 transition-colors shrink-0">
                                {{ searchingCsv ? 'Searching...' : 'Search CSV' }}
                            </button>
                        </div>
                        <p v-if="form.errors.student_id_number" class="text-xs text-red-500 mt-1">{{ form.errors.student_id_number }}</p>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name *</label>
                        <input v-model="form.name" type="text" required class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Birthdate</label>
                        <input v-model="form.birthdate" type="date" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Age</label>
                        <input v-model="form.age" type="number" readonly class="w-full text-sm rounded-lg border-gray-100 bg-gray-50" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Sex</label>
                        <select v-model="form.sex" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                            <option value="">Select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="lg:col-span-2">
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Address</label>
                        <input v-model="form.address" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Civil Status</label>
                        <select v-model="form.civil_status" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                            <option value="">Select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Religion</label>
                        <input v-model="form.religion" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Department *</label>
                        <select v-model="form.department_id" required class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                            <option value="">Select Department</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.code }} - {{ dept.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Course</label>
                        <select v-model="form.course_id" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" :disabled="!availableCourses.length">
                            <option value="">Select Course</option>
                            <option v-for="course in availableCourses" :key="course.id" :value="course.id">{{ course.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Year Level</label>
                        <select v-model="form.year_level" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500">
                            <option value="">Select</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="Grade 7">Grade 7</option>
                            <option value="Grade 8">Grade 8</option>
                            <option value="Grade 9">Grade 9</option>
                            <option value="Grade 10">Grade 10</option>
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Contact No.</label>
                        <input v-model="form.contact_no" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-navy-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">In Case of Emergency</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Contact Person</label>
                        <input v-model="form.emergency_contact_person" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Relationship</label>
                        <input v-model="form.emergency_contact_relationship" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Address</label>
                        <input v-model="form.emergency_contact_address" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Contact No.</label>
                        <input v-model="form.emergency_contact_no" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                    </div>
                </div>
            </div>

            <!-- Medical Record / Physical Examination -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-navy-800">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wider">Medical Record — Physical Examination</h3>
                </div>
                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Date Examined</label>
                            <input v-model="form.examination_date" type="date" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Height (cm)</label>
                            <input v-model="form.height_cm" type="number" step="0.1" min="0" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Weight (kg)</label>
                            <input v-model="form.weight_kg" type="number" step="0.1" min="0" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">BMI (Auto-calculated)</label>
                            <div class="flex items-center gap-2">
                                <input :value="bmi || ''" type="text" readonly class="w-full text-sm rounded-lg border-gray-100 bg-gray-50 font-bold" />
                                <span v-if="bmiCategory" :class="bmiColor" class="px-2 py-1 rounded-lg text-xs font-bold whitespace-nowrap">{{ bmiCategory }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Pulse Rate</label>
                            <input v-model="form.pulse_rate" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Respiratory Rate</label>
                            <input v-model="form.respiratory_rate" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Blood Pressure</label>
                            <input v-model="form.blood_pressure" type="text" placeholder="120/80" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                            <span v-if="bloodPressureStatus" class="inline-block mt-1 px-2 py-1 rounded-full text-xs font-semibold" :class="bloodPressureColor">{{ bloodPressureStatus }}</span>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Oxygen Saturation (%)</label>
                            <input v-model="form.oxygen_saturation" type="number" step="0.1" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                    </div>

                    <!-- Lifestyle & Allergies -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <p class="text-xs font-bold text-gray-700 uppercase">Lifestyle</p>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.smoker" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Smoker
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.alcoholic" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Alcoholic
                            </label>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs font-bold text-gray-700 uppercase">Allergies</p>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Allergies (specify)</label>
                                <input v-model="form.allergies" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.food_allergy" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Food Allergy
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.drug_allergy" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Drug Allergy
                            </label>
                        </div>
                    </div>

                    <!-- Medical History -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Past Medical History</label>
                        <textarea v-model="form.past_medical_history" rows="2" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500"></textarea>
                    </div>

                    <!-- Family History -->
                    <div>
                        <p class="text-xs font-bold text-gray-700 uppercase mb-3">Family History</p>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.family_hpn" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> HPN (Hypertension)
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.family_cancer" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Cancer
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.family_asthma" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> Asthma
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.family_dm" type="checkbox" class="rounded border-gray-300 text-mustard-500 focus:ring-mustard-500" /> DM (Diabetes)
                            </label>
                        </div>
                    </div>

                    <!-- Physician -->
                    <div class="border-t pt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">School Physician</label>
                            <input v-model="form.physician_name" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">License No.</label>
                            <input v-model="form.physician_license_no" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">PTR</label>
                            <input v-model="form.physician_ptr" type="text" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">License Expiry Date</label>
                            <input v-model="form.license_expiry_date" type="date" class="w-full text-sm rounded-lg border-gray-200 focus:ring-mustard-500 focus:border-mustard-500" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3">
                <a :href="route('students.index')" class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">Cancel</a>
                <button type="submit" :disabled="form.processing"
                    class="px-6 py-2.5 text-sm font-bold text-white bg-[#132639] hover:bg-[#0E4362] rounded-lg shadow-sm transition-all duration-200 hover:shadow-md disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update Student' : (isOnline ? 'Save Student' : '📥 Queue Offline')) }}
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
