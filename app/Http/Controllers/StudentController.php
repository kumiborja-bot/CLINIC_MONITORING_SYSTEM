<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Department;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::with(['department', 'course', 'medicalRecord']);

        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'college') {
                $query->whereHas('department', function ($q) {
                    $q->where('code', '!=', 'BSED');
                });
            } elseif ($type === 'senior_high') {
                $query->whereHas('course', function ($q) {
                    $q->where('name', 'like', '%Senior High%');
                });
            } elseif ($type === 'junior_high') {
                $query->whereHas('course', function ($q) {
                    $q->where('name', 'like', '%Junior High%');
                });
            }
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('bmi_category')) {
            $query->whereHas('medicalRecord', function ($q) use ($request) {
                $q->where('bmi_category', $request->bmi_category);
            });
        }

        $students = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'departments' => Department::where('type', 'student')->with('courses')->get(),
            'filters' => $request->only(['search', 'department_id', 'bmi_category', 'type']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Students/Create', [
            'departments' => Department::where('type', 'student')->with('courses')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id_number' => 'required|unique:students',
            'name' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'sex' => 'nullable|in:Male,Female',
            'civil_status' => 'nullable|string',
            'religion' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'course_id' => 'nullable|exists:courses,id',
            'contact_no' => 'nullable|string',
            'year_level' => 'nullable|string',
            'emergency_contact_person' => 'nullable|string',
            'emergency_contact_relationship' => 'nullable|string',
            'emergency_contact_address' => 'nullable|string',
            'emergency_contact_no' => 'nullable|string',
        ]);

        $student = Student::create($validated);

        // Create medical record if medical data provided
        if ($request->has('height_cm') || $request->has('weight_kg')) {
            $medicalData = $request->validate([
                'examination_date' => 'nullable|date',
                'height_cm' => 'nullable|numeric',
                'weight_kg' => 'nullable|numeric',
                'pulse_rate' => 'nullable|string',
                'respiratory_rate' => 'nullable|string',
                'blood_pressure' => 'nullable|string',
                'oxygen_saturation' => 'nullable|numeric',
                'smoker' => 'nullable|boolean',
                'alcoholic' => 'nullable|boolean',
                'allergies' => 'nullable|string',
                'food_allergy' => 'nullable|boolean',
                'drug_allergy' => 'nullable|boolean',
                'past_medical_history' => 'nullable|string',
                'family_hpn' => 'nullable|boolean',
                'family_cancer' => 'nullable|boolean',
                'family_asthma' => 'nullable|boolean',
                'family_dm' => 'nullable|boolean',
                'physician_name' => 'nullable|string',
                'physician_license_no' => 'nullable|string',
                'physician_ptr' => 'nullable|string',
                'license_expiry_date' => 'nullable|date',
            ]);

            // Auto-calculate BMI
            if (!empty($medicalData['height_cm']) && !empty($medicalData['weight_kg'])) {
                $bmiResult = MedicalRecord::calculateBmi($medicalData['height_cm'], $medicalData['weight_kg']);
                $medicalData['bmi'] = $bmiResult['bmi'];
                $medicalData['bmi_category'] = $bmiResult['category'];
            }

            $student->medicalRecord()->create($medicalData);
        }

        return redirect()->route('students.index')->with('success', 'Student created successfully.');
    }

    public function show(Student $student)
    {
        $student->load(['department', 'course', 'medicalRecord', 'treatments.treatedBy', 'medicineLogs.medicine', 'maritimeDocuments']);

        // Explicitly map treated_by_user to avoid serialization clash
        foreach ($student->treatments as $t) {
            $t->treated_by_user = $t->treatedBy;
        }

        return Inertia::render('Students/Show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student)
    {
        $student->load(['department', 'course', 'medicalRecord']);

        return Inertia::render('Students/Edit', [
            'student' => $student,
            'departments' => Department::where('type', 'student')->with('courses')->get(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id_number' => 'required|unique:students,student_id_number,' . $student->id,
            'name' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'sex' => 'nullable|in:Male,Female',
            'civil_status' => 'nullable|string',
            'religion' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'course_id' => 'nullable|exists:courses,id',
            'contact_no' => 'nullable|string',
            'year_level' => 'nullable|string',
            'emergency_contact_person' => 'nullable|string',
            'emergency_contact_relationship' => 'nullable|string',
            'emergency_contact_address' => 'nullable|string',
            'emergency_contact_no' => 'nullable|string',
        ]);

        $student->update($validated);

        // Update medical record
        $medicalData = $request->validate([
            'examination_date' => 'nullable|date',
            'height_cm' => 'nullable|numeric',
            'weight_kg' => 'nullable|numeric',
            'pulse_rate' => 'nullable|string',
            'respiratory_rate' => 'nullable|string',
            'blood_pressure' => 'nullable|string',
            'oxygen_saturation' => 'nullable|numeric',
            'smoker' => 'nullable|boolean',
            'alcoholic' => 'nullable|boolean',
            'allergies' => 'nullable|string',
            'food_allergy' => 'nullable|boolean',
            'drug_allergy' => 'nullable|boolean',
            'past_medical_history' => 'nullable|string',
            'family_hpn' => 'nullable|boolean',
            'family_cancer' => 'nullable|boolean',
            'family_asthma' => 'nullable|boolean',
            'family_dm' => 'nullable|boolean',
            'physician_name' => 'nullable|string',
            'physician_license_no' => 'nullable|string',
            'physician_ptr' => 'nullable|string',
            'license_expiry_date' => 'nullable|date',
        ]);

        // Auto-calculate BMI
        if (!empty($medicalData['height_cm']) && !empty($medicalData['weight_kg'])) {
            $bmiResult = MedicalRecord::calculateBmi($medicalData['height_cm'], $medicalData['weight_kg']);
            $medicalData['bmi'] = $bmiResult['bmi'];
            $medicalData['bmi_category'] = $bmiResult['category'];
        }

        $student->medicalRecord()->updateOrCreate(
            ['recordable_type' => Student::class , 'recordable_id' => $student->id],
            $medicalData
        );

        return redirect()->route('students.show', $student)->with('success', 'Student updated successfully.');
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted successfully.');
    }

    public function getCourses(Department $department)
    {
        return response()->json($department->courses);
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $tempPath = $file->getRealPath();

        $importService = app(\App\Services\StudentImportService::class);
        $result = $importService->importFromPath($tempPath);

        if ($result['success']) {
            return redirect()->back()->with('success', "Import completed! Imported {$result['imported_count']} new students; skipped {$result['duplicate_count']} duplicates.")->with('import_report', $result);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    public function importLocal()
    {
        $filePath = 'C:\\Users\\Inonazarene\\Desktop\\MEDICAL\\cleaned_students.csv';
        $importService = app(\App\Services\StudentImportService::class);
        $result = $importService->importFromPath($filePath);

        if ($result['success']) {
            return redirect()->back()->with('success', "Local import completed! Imported {$result['imported_count']} new students; skipped {$result['duplicate_count']} duplicates.")->with('import_report', $result);
        }

        return redirect()->back()->with('error', $result['message']);
    }
}
