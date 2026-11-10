<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Department;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonnelController extends Controller
{
    public function index(Request $request)
    {
        $query = Personnel::with(['department', 'medicalRecord']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $personnel = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Personnel/Index', [
            'personnel' => $personnel,
            'departments' => Department::where('type', 'personnel')->get(),
            'filters' => $request->only(['search', 'department_id']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Personnel/Create', [
            'departments' => Department::where('type', 'personnel')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|unique:personnel',
            'name' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'sex' => 'nullable|in:Male,Female',
            'civil_status' => 'nullable|string',
            'religion' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'position' => 'nullable|string',
            'contact_no' => 'nullable|string',
            'emergency_contact_person' => 'nullable|string',
            'emergency_contact_relationship' => 'nullable|string',
            'emergency_contact_address' => 'nullable|string',
            'emergency_contact_no' => 'nullable|string',
        ]);

        $person = Personnel::create($validated);

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

            if (!empty($medicalData['height_cm']) && !empty($medicalData['weight_kg'])) {
                $bmiResult = MedicalRecord::calculateBmi($medicalData['height_cm'], $medicalData['weight_kg']);
                $medicalData['bmi'] = $bmiResult['bmi'];
                $medicalData['bmi_category'] = $bmiResult['category'];
            }

            $person->medicalRecord()->create($medicalData);
        }

        return redirect()->route('personnel.index')->with('success', 'Personnel created successfully.');
    }

    public function show(Personnel $personnel)
    {
        $personnel->load(['department', 'medicalRecord', 'treatments.treatedBy', 'medicineLogs.medicine', 'personnelDocuments', 'weightLogs']);

        // Explicitly map treated_by_user to avoid serialization clash
        foreach ($personnel->treatments as $t) {
            $t->treated_by_user = $t->treatedBy;
        }

        return Inertia::render('Personnel/Show', [
            'personnel' => $personnel,
        ]);
    }

    public function storeWeightLog(Request $request, Personnel $personnel)
    {
        $validated = $request->validate([
            'recorded_date' => 'required|date',
            'height_cm' => 'required|numeric|gt:0',
            'weight_kg' => 'required|numeric|gt:0',
            'remarks' => 'nullable|string|max:255',
        ]);

        $bmiResult = \App\Models\PersonnelWeightLog::calculateBmi($validated['height_cm'], $validated['weight_kg']);
        $validated['bmi'] = $bmiResult['bmi'];
        $validated['bmi_category'] = $bmiResult['category'];

        $personnel->weightLogs()->create($validated);

        // Synchronize latest medical record height/weight/bmi
        $personnel->medicalRecord()->updateOrCreate(
            ['recordable_type' => Personnel::class, 'recordable_id' => $personnel->id],
            [
                'height_cm' => $validated['height_cm'],
                'weight_kg' => $validated['weight_kg'],
                'bmi' => $validated['bmi'],
                'bmi_category' => $validated['bmi_category'],
                'examination_date' => $validated['recorded_date'],
            ]
        );

        return back()->with('success', 'Monthly weight log added successfully.');
    }

    public function destroyWeightLog(\App\Models\PersonnelWeightLog $log)
    {
        $log->delete();
        return back()->with('success', 'Weight log deleted.');
    }

    public function edit(Personnel $personnel)
    {
        $personnel->load(['department', 'medicalRecord']);

        return Inertia::render('Personnel/Edit', [
            'personnel' => $personnel,
            'departments' => Department::where('type', 'personnel')->get(),
        ]);
    }

    public function update(Request $request, Personnel $personnel)
    {
        $validated = $request->validate([
            'employee_id' => 'required|unique:personnel,employee_id,' . $personnel->id,
            'name' => 'required|string|max:255',
            'birthdate' => 'nullable|date',
            'address' => 'nullable|string',
            'age' => 'nullable|integer',
            'sex' => 'nullable|in:Male,Female',
            'civil_status' => 'nullable|string',
            'religion' => 'nullable|string',
            'department_id' => 'required|exists:departments,id',
            'position' => 'nullable|string',
            'contact_no' => 'nullable|string',
            'emergency_contact_person' => 'nullable|string',
            'emergency_contact_relationship' => 'nullable|string',
            'emergency_contact_address' => 'nullable|string',
            'emergency_contact_no' => 'nullable|string',
        ]);

        $personnel->update($validated);

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

        if (!empty($medicalData['height_cm']) && !empty($medicalData['weight_kg'])) {
            $bmiResult = MedicalRecord::calculateBmi($medicalData['height_cm'], $medicalData['weight_kg']);
            $medicalData['bmi'] = $bmiResult['bmi'];
            $medicalData['bmi_category'] = $bmiResult['category'];
        }

        $personnel->medicalRecord()->updateOrCreate(
            ['recordable_type' => Personnel::class , 'recordable_id' => $personnel->id],
            $medicalData
        );

        return redirect()->route('personnel.show', $personnel)->with('success', 'Personnel updated successfully.');
    }

    public function destroy(Personnel $personnel)
    {
        $personnel->delete();
        return redirect()->route('personnel.index')->with('success', 'Personnel deleted successfully.');
    }

    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $tempPath = $file->getRealPath();

        $importService = app(\App\Services\PersonnelImportService::class);
        $result = $importService->importFromPath($tempPath);

        if ($result['success']) {
            $message = "Import completed! Imported {$result['imported_count']} new personnel; skipped {$result['duplicate_count']} duplicates.";
            if (($result['skipped_count'] ?? 0) > 0 || !empty($result['errors'])) {
                $message .= " Skipped " . ($result['skipped_count'] ?? 0) . " row(s).";
            }

            return redirect()->back()
                ->with('success', $message)
                ->with('import_report', $result);
        }

        return redirect()->back()
            ->with('error', $result['message'])
            ->with('import_report', $result);
    }
}
