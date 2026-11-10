<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Personnel;
use App\Models\Medicine;
use App\Models\Treatment;
use App\Models\Department;
use App\Models\MedicineLog;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $departmentId = $request->get('department_id');

        // Stats
        $studentsCount = Student::when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->count();
        $personnelCount = Personnel::when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->count();
        $medicinesCount = Medicine::where('stock_quantity', '>', 0)->count();
        
        // Treatments Today
        $treatmentsTodayCount = Treatment::whereDate('treated_at', \Carbon\Carbon::today())->count();

        // Common illnesses (top 10 diagnoses)
        $commonIllnesses = Treatment::select('diagnosis', DB::raw('count(*) as total'))
            ->groupBy('diagnosis')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top dispensed medicines
        $topMedicines = MedicineLog::select('medicine_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('medicine_id')
            ->orderByDesc('total')
            ->limit(5)
            ->with('medicine:id,name')
            ->get();

        // Low stock medicines
        $lowStockMedicines = Medicine::whereColumn('stock_quantity', '<=', 'reorder_level')
            ->orderBy('stock_quantity')
            ->limit(5)
            ->get();

        $personnelWithRecords = Personnel::with(['medicalRecord', 'department'])->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->get();
        $bmiCategory = fn ($p) => ($p->medicalRecord && $p->medicalRecord->height_cm > 0 && $p->medicalRecord->weight_kg > 0)
            ? MedicalRecord::calculateBmi((float) $p->medicalRecord->height_cm, (float) $p->medicalRecord->weight_kg)['category']
            : 'No record';
        $overweightPersonnel = $personnelWithRecords->filter(fn ($p) => in_array($bmiCategory($p), ['Overweight', 'Obese'], true))->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'department' => $p->department?->name, 'bmi' => MedicalRecord::calculateBmi((float) $p->medicalRecord->height_cm, (float) $p->medicalRecord->weight_kg)['bmi'], 'category' => $bmiCategory($p)])->values();
        $bmiBreakdown = $personnelWithRecords->groupBy($bmiCategory)->map->count();
        $completedVisits = Appointment::with('patient')->whereIn('status', ['attended', 'completed'])->whereDate('appointment_date', Carbon::today())->where('appointment_date', '<=', now())->latest('appointment_date')->get()->map(fn ($visit) => ['id' => $visit->id, 'patient' => $visit->patient?->name ?: 'Unknown patient', 'patient_type' => class_basename($visit->patient_type), 'date' => optional($visit->appointment_date)->format('M d, Y h:i A'), 'reason' => $visit->reason_for_visit]);
        $visitHistory = Appointment::with('patient')->whereIn('status', ['attended', 'completed'])->where('appointment_date', '<', Carbon::today())->latest('appointment_date')->limit(20)->get()->map(fn ($visit) => ['id' => $visit->id, 'patient' => $visit->patient?->name ?: 'Unknown patient', 'patient_type' => class_basename($visit->patient_type), 'date' => optional($visit->appointment_date)->format('M d, Y h:i A'), 'reason' => $visit->reason_for_visit]);

        // Recent treatments
        $recentTreatments = Treatment::with('treatedBy')
            ->latest('treated_at')
            ->limit(5)
            ->get()
            ->map(function ($t) {
                $patient = $t->patient;
                $t->patient_name = $patient ? $patient->name : 'Unknown';
                $t->patient_type_label = str_contains($t->patient_type, 'Student') ? 'Student' : 'Personnel';
                return $t;
            });

        $departments = Department::all();

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalStudents' => $studentsCount,
                'totalPersonnel' => $personnelCount,
                'totalMedicines' => $medicinesCount,
                'treatmentsToday' => $treatmentsTodayCount,
            ],
            'commonIllnesses' => $commonIllnesses,
            'topMedicines' => $topMedicines,
            'lowStockMedicines' => $lowStockMedicines,
            'overweightPersonnel' => $overweightPersonnel,
            'bmiBreakdown' => $bmiBreakdown,
            'expiringMedicines' => Medicine::whereNotNull('expiry_date')->whereBetween('expiry_date', [Carbon::today(), Carbon::today()->addMonths(2)])->orderBy('expiry_date')->get(['id','name','expiry_date']),
            'completedVisits' => $completedVisits,
            'visitHistory' => $visitHistory,
            'recentTreatments' => $recentTreatments,
            'departments' => $departments,
            'filters' => [
                'department_id' => $departmentId,
            ],
        ]);
    }
}
