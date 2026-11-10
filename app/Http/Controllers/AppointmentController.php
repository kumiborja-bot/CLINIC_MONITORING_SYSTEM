<?php

namespace App\Http\Controllers;

use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Mail\AppointmentStatusMail;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Medicine;
use App\Models\MedicineLog;
use App\Models\Personnel;
use App\Models\Student;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $patientType = $request->input('patient_type');

        $query = Appointment::with(['patient', 'assignedProvider'])
            ->orderBy('appointment_date', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHasMorph('patient', [Student::class, Personnel::class], function ($morphQ) use ($search) {
                    $morphQ->where('name', 'like', "%{$search}%");
                })
                ->orWhere('assigned_provider_name', 'like', "%{$search}%")
                ->orWhere('clinic_location', 'like', "%{$search}%")
                ->orWhere('reason_for_visit', 'like', "%{$search}%");
            });
        }

        if ($status && $status !== 'all') {
            if ($status === 'history') {
                $query->whereIn('status', ['attended', 'completed']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($patientType && $patientType !== 'all') {
            if ($patientType === 'student') {
                $query->where('patient_type', Student::class);
            } elseif ($patientType === 'personnel') {
                $query->where('patient_type', Personnel::class);
            }
        }

        $appointments = $query->paginate(15)->withQueryString();

        // Calculate statistics
        $stats = [
            'total' => Appointment::count(),
            'pending' => Appointment::where('status', 'pending')->count(),
            'scheduled' => Appointment::where('status', 'scheduled')->count(),
            'attended' => Appointment::whereIn('status', ['attended', 'completed'])->count(),
            'cancelled' => Appointment::where('status', 'cancelled')->count(),
        ];

        // Fetch patients and departments for searchable select
        $students = Student::with('department:id,name,code')
            ->orderBy('name')
            ->get();

        $personnel = Personnel::with('department:id,name,code')
            ->orderBy('name')
            ->get();

        $departments = Department::select('id', 'name', 'code', 'type')
            ->orderBy('name')
            ->get();

        // Healthcare providers list
        $providers = User::select('id', 'name', 'role', 'email')
            ->orderBy('name')
            ->get();

        // Medicines inventory list for consultation dispensing (only medicines with stock)
        $medicines = Medicine::select('id', 'name', 'stock_quantity', 'unit', 'supplier', 'description')
            ->where('stock_quantity', '>', 0)
            ->orderBy('name')
            ->get();

        return Inertia::render('Appointments/Index', [
            'appointments' => $appointments,
            'filters' => [
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'patient_type' => $patientType ?? 'all',
            ],
            'stats' => $stats,
            'students' => $students,
            'personnel' => $personnel,
            'departments' => $departments,
            'providers' => $providers,
            'medicines' => $medicines,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_type' => 'required|string',
            'patient_id' => 'required|integer',
            'email' => 'nullable|email',
            'appointment_date' => 'required|date',
            'clinic_location' => 'required|string|max:255',
            'assigned_provider_id' => 'nullable|exists:users,id',
            'assigned_provider_name' => 'nullable|string|max:255',
            'reason_for_visit' => 'nullable|string',
            'status' => 'required|in:pending,scheduled,attended,cancelled,rescheduled,completed',
            'send_email' => 'boolean',
        ]);

        if (in_array($validated['patient_type'], ['student', Student::class])) {
            $validated['patient_type'] = Student::class;
        } else {
            $validated['patient_type'] = Personnel::class;
        }

        if ($validated['assigned_provider_id'] && empty($validated['assigned_provider_name'])) {
            $user = User::find($validated['assigned_provider_id']);
            $validated['assigned_provider_name'] = $user?->name;
        }

        $appointment = Appointment::create($validated);

        // Also update email on patient record if provided
        $patientClass = $validated['patient_type'];
        $patient = $patientClass::find($validated['patient_id']);
        if ($patient && !empty($validated['email']) && empty($patient->email)) {
            $patient->update(['email' => $validated['email']]);
        }

        $recipientEmail = $validated['email'] ?? $patient?->email;

        // Send email confirmation if requested and recipient email is present
        if ($request->boolean('send_email') && $recipientEmail) {
            try {
                Mail::to($recipientEmail)->send(new AppointmentConfirmationMail($appointment));
            } catch (\Throwable $e) {
                // Log exception silently or continue
            }
        }

        return redirect()->route('appointments.index')->with('success', 'Appointment saved successfully.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'appointment_date' => 'sometimes|required|date',
            'clinic_location' => 'sometimes|required|string|max:255',
            'assigned_provider_id' => 'nullable|exists:users,id',
            'assigned_provider_name' => 'nullable|string|max:255',
            'reason_for_visit' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,scheduled,attended,cancelled,rescheduled,completed',
            'cancellation_reason' => 'nullable|string',
            'notes' => 'nullable|string',
            'send_email' => 'boolean',
        ]);

        if (isset($validated['assigned_provider_id']) && $validated['assigned_provider_id']) {
            $user = User::find($validated['assigned_provider_id']);
            $validated['assigned_provider_name'] = $user?->name;
        }

        $oldStatus = $appointment->status;
        $appointment->update($validated);

        $recipientEmail = $appointment->email ?? $appointment->patient?->email;

        // Send notification email for status updates (cancelled / rescheduled)
        if ($request->boolean('send_email') && $recipientEmail) {
            if ($appointment->status === 'cancelled') {
                try {
                    Mail::to($recipientEmail)->send(new AppointmentStatusMail($appointment, 'cancelled'));
                } catch (\Throwable $e) {}
            } elseif ($appointment->status === 'rescheduled') {
                try {
                    Mail::to($recipientEmail)->send(new AppointmentStatusMail($appointment, 'rescheduled'));
                } catch (\Throwable $e) {}
            } elseif ($appointment->status === 'scheduled' && $oldStatus === 'pending') {
                try {
                    Mail::to($recipientEmail)->send(new AppointmentConfirmationMail($appointment));
                } catch (\Throwable $e) {}
            }
        }

        return redirect()->route('appointments.index')->with('success', 'Appointment updated successfully.');
    }

    public function sendReminder(Appointment $appointment, Request $request): RedirectResponse
    {
        $recipientEmail = $appointment->email ?? $appointment->patient?->email;

        if (!$recipientEmail) {
            return redirect()->back()->with('error', 'No email address available for this patient.');
        }

        $isFollowUp = $request->boolean('is_follow_up', false);

        try {
            Mail::to($recipientEmail)->send(new AppointmentReminderMail($appointment, $isFollowUp));
            $appointment->update(['reminder_sent' => true]);
            return redirect()->back()->with('success', 'Appointment reminder email sent successfully.');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to send email notification: ' . $e->getMessage());
        }
    }

    public function recordConsultation(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'diagnosis' => 'required|string|max:255',
            'treatment_given' => 'nullable|string',
            'medication_given' => 'nullable|string',
            'medicine_id' => 'nullable|exists:medicines,id',
            'medicine_quantity' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
        ]);

        $medicationText = $validated['medication_given'] ?? null;
        $medicine = null;
        $qty = $validated['medicine_quantity'] ?? 1;

        if (!empty($validated['medicine_id'])) {
            $medicine = Medicine::find($validated['medicine_id']);
            if ($medicine) {
                if (empty($medicationText)) {
                    $medicationText = "{$medicine->name} (Qty: {$qty} {$medicine->unit})";
                } else {
                    $medicationText .= " - {$medicine->name} (Qty: {$qty} {$medicine->unit})";
                }
            }
        }

        // Record consultation as a Treatment entry in patient health history
        $treatment = Treatment::create([
            'appointment_id' => $appointment->id,
            'patient_type' => $appointment->patient_type,
            'patient_id' => $appointment->patient_id,
            'diagnosis' => $validated['diagnosis'],
            'treatment_given' => $validated['treatment_given'] ?? null,
            'medication_given' => $medicationText,
            'medicine_id' => $medicine?->id,
            'medicine_quantity' => $medicine ? $qty : null,
            'treated_by' => auth()->id(),
            'treated_at' => now(),
            'notes' => $validated['notes'] ?? null,
            'status' => 'completed',
            'follow_up_date' => $validated['follow_up_date'] ?? null,
        ]);

        // If medicine selected, deduct stock and log dispensing in medicine_logs
        if ($medicine) {
            $medicine->decrement('stock_quantity', $qty);

            MedicineLog::create([
                'medicine_id' => $medicine->id,
                'treatment_id' => $treatment->id,
                'appointment_id' => $appointment->id,
                'patient_type' => $appointment->patient_type,
                'patient_id' => $appointment->patient_id,
                'quantity' => $qty,
                'dispensed_by' => auth()->id(),
                'dispensed_at' => now(),
                'notes' => 'Dispensed during consultation for appointment #' . $appointment->id,
            ]);
        }

        // Update appointment status to completed/attended
        $appointment->update([
            'status' => 'completed',
            'notes' => ($appointment->notes ? $appointment->notes . "\n" : '') . "Consultation Recorded: " . $validated['diagnosis'],
        ]);

        // If follow up date is set, optional reminder trigger can be scheduled
        if (!empty($validated['follow_up_date']) && $request->boolean('send_followup_reminder')) {
            $recipientEmail = $appointment->email ?? $appointment->patient?->email;
            if ($recipientEmail) {
                try {
                    Mail::to($recipientEmail)->send(new AppointmentReminderMail($appointment, true));
                } catch (\Throwable $e) {}
            }
        }

        return redirect()->route('appointments.index')->with('success', 'Consultation recorded, medicine linked & dispensed, and patient health record updated successfully.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $appointment->delete();
        return redirect()->route('appointments.index')->with('success', 'Appointment deleted successfully.');
    }
}
