<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\Department;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PersonnelImportService
{
    /**
     * Import personnel from a CSV file path.
     *
     * @param string $filePath
     * @return array
     */
    public function importFromPath(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'message' => "The file at '{$filePath}' does not exist.",
                'imported_count' => 0,
                'errors' => ["File not found."]
            ];
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return [
                'success' => false,
                'message' => "Unable to open the CSV file.",
                'imported_count' => 0,
                'errors' => ["Could not read file."]
            ];
        }

        // Parse header
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return [
                'success' => false,
                'message' => "The CSV file is empty.",
                'imported_count' => 0,
                'errors' => ["Empty file."]
            ];
        }

        // Clean headers (remove BOM and trim spaces)
        $headers = array_map(function ($header) {
            $header = preg_replace('/[\x{00EF}\x{00BB}\x{00BF}]/u', '', $header); // Remove UTF-8 BOM
            return trim($header);
        }, $headers);

        // Dynamically match headers to database columns
        $headerMap = $this->matchHeadersDynamically($headers);
        $headerValidation = $this->validateHeaders($headers, $headerMap);

        if (!$headerValidation['success']) {
            fclose($handle);

            return [
                'success' => false,
                'message' => 'CSV import failed because the column headers are invalid.',
                'imported_count' => 0,
                'skipped_count' => 0,
                'errors' => $headerValidation['errors'],
                'warnings' => $headerValidation['warnings'],
            ];
        }

        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;
        $totalRecords = 0;
        $errors = $headerValidation['warnings'];
        $rowCount = 1;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowCount++;
                
                // Skip empty rows
                if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                    continue;
                }
                $totalRecords++;

                if (count($row) !== count($headers)) {
                    $skippedCount++;
                    $errors[] = "Row {$rowCount}: Expected " . count($headers) . ' columns but found ' . count($row) . '. Check for missing commas or unclosed quotes.';
                    continue;
                }

                // Helper to get value dynamically by mapped header key
                $getValue = function($key) use ($row, $headerMap) {
                    $idx = $headerMap[$key] ?? null;
                    return ($idx !== null && isset($row[$idx])) ? trim($row[$idx]) : '';
                };

                // Check for required employee ID
                $employeeId = $getValue('employee_id');
                if (empty($employeeId)) {
                    $skippedCount++;
                    $errors[] = "Row {$rowCount}: Skipped due to missing Employee ID.";
                    continue;
                }
                if (Personnel::where('employee_id', $employeeId)->exists()) {
                    $duplicateCount++;
                    $errors[] = "Row {$rowCount}: Duplicate employee ID '{$employeeId}' skipped.";
                    continue;
                }

                // Map Sex/Gender
                $gender = strtoupper($getValue('gender'));
                $sex = null;
                if ($gender === 'M' || $gender === 'MALE') {
                    $sex = 'Male';
                } elseif ($gender === 'F' || $gender === 'FEMALE') {
                    $sex = 'Female';
                }

                // Construct Name
                $lastName = $getValue('lastname');
                $firstName = $getValue('firstname');
                $middleName = $getValue('middlename');

                if (empty($lastName) && empty($firstName)) {
                    // Try getting from single name field if available
                    $name = $getValue('name');
                    if (empty($name)) {
                        $skippedCount++;
                        $errors[] = "Row {$rowCount}: Skipped due to missing personnel name.";
                        continue;
                    }
                } else {
                    $name = $lastName;
                    if (!empty($firstName)) {
                        $name .= (empty($name) ? '' : ', ') . $firstName;
                    }
                    if (!empty($middleName) && $middleName !== 'N/A' && $middleName !== 'n/a') {
                        $name .= ' ' . $middleName;
                    }
                    $name = trim($name);
                }

                // Department
                $deptNameOrCode = $getValue('department');
                $departmentId = $this->getDefaultDepartmentId();
                if (!empty($deptNameOrCode)) {
                    $deptCode = strtoupper(trim($deptNameOrCode));
                    // Find by code first
                    $dept = Department::where('code', $deptCode)->first();
                    if (!$dept) {
                        // Find by name
                        $dept = Department::where('name', 'like', $deptNameOrCode)->first();
                    }
                    if (!$dept) {
                        // Create a new personnel department
                        $dept = Department::create([
                            'code' => substr($deptCode, 0, 10),
                            'name' => $deptNameOrCode,
                            'full_name' => $deptNameOrCode,
                            'type' => 'personnel'
                        ]);
                    }
                    $departmentId = $dept->id;
                }

                // Update or Create Personnel
                $personnel = Personnel::create([
                        'employee_id' => $employeeId,
                        'name' => $name,
                        'birthdate' => null,
                        'age' => null,
                        'sex' => $sex,
                        'address' => null,
                        'civil_status' => null,
                        'religion' => null,
                        'department_id' => $departmentId,
                        'position' => $getValue('position') ?: null,
                        'contact_no' => null,
                        'emergency_contact_person' => null,
                        'emergency_contact_relationship' => null,
                        'emergency_contact_address' => null,
                        'emergency_contact_no' => null,
                    ]);

                // Handle Medical Record
                $disability = $getValue('disability');
                $cleanDisability = strtolower($disability);
                $ignoredDisabilities = ['n/a', 'n\\a', 'none', 'na', 'n.a.', '-', 'n/a.', 'n\\a.', 'no', 'nome', 'nothing', 'n', 'nk', 'non'];
                if (!empty($disability) && !in_array($cleanDisability, $ignoredDisabilities)) {
                    $personnel->medicalRecord()->updateOrCreate(
                        [
                            'recordable_type' => Personnel::class,
                            'recordable_id' => $personnel->id
                        ],
                        [
                            'past_medical_history' => "Disability: " . $disability,
                            'examination_date' => Carbon::now()->format('Y-m-d'),
                        ]
                    );
                }

                $importedCount++;
            }

            DB::commit();
            fclose($handle);

            return [
                'success' => true,
                'message' => "Import completed successfully.",
                'imported_count' => $importedCount,
                'skipped_count' => $skippedCount,
                'duplicate_count' => $duplicateCount,
                'total_count' => $totalRecords,
                'failed_count' => $skippedCount,
                'errors' => $errors,
                'warnings' => $headerValidation['warnings'],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            Log::error("Failed to import personnel CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Import failed: " . $e->getMessage(),
                'imported_count' => 0,
                'skipped_count' => $skippedCount,
                'duplicate_count' => $duplicateCount ?? 0,
                'total_count' => $totalRecords ?? 0,
                'errors' => array_merge($errors, [$e->getMessage()]),
                'warnings' => $headerValidation['warnings'] ?? [],
            ];
        }
    }

    /**
     * Validate that the CSV contains usable personnel columns before importing rows.
     */
    private function validateHeaders(array $headers, array $headerMap): array
    {
        $errors = [];
        $warnings = [];
        $recognizedIndexes = array_filter($headerMap, fn ($index) => $index !== null);
        $recognizedIndexSet = array_flip(array_values($recognizedIndexes));
        $unknownHeaders = [];

        foreach ($headers as $index => $header) {
            if (!isset($recognizedIndexSet[$index])) {
                $unknownHeaders[] = $header ?: "Column " . ($index + 1);
            }
        }

        if ($headerMap['employee_id'] === null) {
            $errors[] = 'Missing required column: Employee ID. Accepted headers include employee_id, employee id, employee_no, employee no, idno, id_no, or id.';
        }

        if ($headerMap['name'] === null && $headerMap['lastname'] === null && $headerMap['firstname'] === null) {
            $errors[] = 'Missing required name column. Use a Name column, or separate Last Name and First Name columns.';
        }

        if (empty($recognizedIndexes)) {
            $errors[] = 'No supported personnel columns were found in the CSV header row.';
        }

        if (!empty($unknownHeaders)) {
            $warnings[] = 'Unrecognized column header' . (count($unknownHeaders) === 1 ? '' : 's') . ': ' . implode(', ', $unknownHeaders) . '. These columns will be ignored.';
        }

        return [
            'success' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Personnel rows need a department_id, even when CSV department is blank.
     */
    private function getDefaultDepartmentId(): int
    {
        return Department::firstOrCreate(
            ['code' => 'UNASSIGNED', 'type' => 'personnel'],
            [
                'name' => 'Unassigned',
                'full_name' => 'Unassigned',
            ]
        )->id;
    }

    /**
     * Match headers dynamically to indexes in the CSV row.
     */
    private function matchHeadersDynamically(array $headers): array
    {
        $map = [
            'employee_id' => null,
            'name' => null,
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
            'gender' => null,
            'birthdate' => null,
            'address' => null,
            'address_st' => null,
            'address_city' => null,
            'address_province' => null,
            'address_zip' => null,
            'civil_status' => null,
            'religion' => null,
            'department' => null,
            'position' => null,
            'contact_no' => null,
            'emergency_contact_person' => null,
            'emergency_contact_relationship' => null,
            'emergency_contact_address' => null,
            'emergency_contact_no' => null,
            'disability' => null,
            'bmi' => null,
        ];

        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));

            // Match employee id
            if ($map['employee_id'] === null && (str_contains($normalized, 'employee_id') || str_contains($normalized, 'employee id') || str_contains($normalized, 'employee_no') || str_contains($normalized, 'employee no') || $normalized === 'idno' || $normalized === 'id_no' || $normalized === 'id')) {
                $map['employee_id'] = $index;
            }
            // Match last name
            elseif ($map['lastname'] === null && (str_contains($normalized, 'lastname') || str_contains($normalized, 'last_name') || str_contains($normalized, 'last name') || str_contains($normalized, 'surname') || str_contains($normalized, 'family name') || str_contains($normalized, 'family_name'))) {
                $map['lastname'] = $index;
            }
            // Match first name
            elseif ($map['firstname'] === null && (str_contains($normalized, 'firstname') || str_contains($normalized, 'first_name') || str_contains($normalized, 'first name') || str_contains($normalized, 'given name') || str_contains($normalized, 'given_name'))) {
                $map['firstname'] = $index;
            }
            // Match middle name
            elseif ($map['middlename'] === null && (str_contains($normalized, 'middlename') || str_contains($normalized, 'middle_name') || str_contains($normalized, 'middle name') || $normalized === 'middle')) {
                $map['middlename'] = $index;
            }
            // Match full name
            elseif ($map['name'] === null && ($normalized === 'name' || $normalized === 'fullname' || $normalized === 'full name' || $normalized === 'personnel name' || $normalized === 'employee name')) {
                $map['name'] = $index;
            }
            // Match gender
            elseif ($map['gender'] === null && (str_contains($normalized, 'gender') || str_contains($normalized, 'sex'))) {
                $map['gender'] = $index;
            }
            // Match birthdate
            elseif ($map['birthdate'] === null && (str_contains($normalized, 'birthdate') || str_contains($normalized, 'birth_date') || str_contains($normalized, 'birth date') || str_contains($normalized, 'bdate') || str_contains($normalized, 'dob') || str_contains($normalized, 'birthday') || str_contains($normalized, 'birth_day'))) {
                $map['birthdate'] = $index;
            }
            // Match department
            elseif ($map['department'] === null && (str_contains($normalized, 'department') || str_contains($normalized, 'dept') || str_contains($normalized, 'office') || str_contains($normalized, 'unit'))) {
                $map['department'] = $index;
            }
            // Match position
            elseif ($map['position'] === null && (str_contains($normalized, 'position') || str_contains($normalized, 'job') || str_contains($normalized, 'role') || str_contains($normalized, 'designation') || str_contains($normalized, 'title'))) {
                $map['position'] = $index;
            }
            // Match contact no
            elseif ($map['contact_no'] === null && (str_contains($normalized, 'contact_no') || str_contains($normalized, 'contact no') || str_contains($normalized, 'contact_number') || str_contains($normalized, 'contact') || str_contains($normalized, 'phone') || str_contains($normalized, 'mobile'))) {
                $map['contact_no'] = $index;
            }
            // Match address street/barangay
            elseif ($map['address_st'] === null && (str_contains($normalized, 'address_st') || str_contains($normalized, 'street') || str_contains($normalized, 'brgy') || str_contains($normalized, 'barangay'))) {
                $map['address_st'] = $index;
            }
            // Match town/city
            elseif ($map['address_city'] === null && (str_contains($normalized, 'city') || str_contains($normalized, 'town') || str_contains($normalized, 'municipality'))) {
                $map['address_city'] = $index;
            }
            // Match province
            elseif ($map['address_province'] === null && str_contains($normalized, 'province')) {
                $map['address_province'] = $index;
            }
            // Match zipcode
            elseif ($map['address_zip'] === null && (str_contains($normalized, 'zip') || str_contains($normalized, 'zipcode') || str_contains($normalized, 'postal'))) {
                $map['address_zip'] = $index;
            }
            // Match full address
            elseif ($map['address'] === null && ($normalized === 'address' || $normalized === 'addr' || $normalized === 'home address' || $normalized === 'residence')) {
                $map['address'] = $index;
            }
            // Match civil status
            elseif ($map['civil_status'] === null && (str_contains($normalized, 'civil_status') || str_contains($normalized, 'civil status') || str_contains($normalized, 'marital_status') || str_contains($normalized, 'marital status') || $normalized === 'status')) {
                $map['civil_status'] = $index;
            }
            // Match religion
            elseif ($map['religion'] === null && str_contains($normalized, 'religion')) {
                $map['religion'] = $index;
            }
            // Match emergency contacts
            elseif ($map['emergency_contact_person'] === null && (str_contains($normalized, 'emergency_contact_person') || str_contains($normalized, 'emergency contact person') || str_contains($normalized, 'emergency_person') || str_contains($normalized, 'emergency person') || str_contains($normalized, 'emergency contact') || $normalized === 'in case of emergency' || $normalized === 'ice')) {
                $map['emergency_contact_person'] = $index;
            }
            elseif ($map['emergency_contact_relationship'] === null && (str_contains($normalized, 'emergency_contact_relationship') || str_contains($normalized, 'emergency contact relationship') || str_contains($normalized, 'emergency_relationship') || str_contains($normalized, 'emergency relationship') || $normalized === 'relationship')) {
                $map['emergency_contact_relationship'] = $index;
            }
            elseif ($map['emergency_contact_address'] === null && (str_contains($normalized, 'emergency_contact_address') || str_contains($normalized, 'emergency contact address') || str_contains($normalized, 'emergency_address') || str_contains($normalized, 'emergency address'))) {
                $map['emergency_contact_address'] = $index;
            }
            elseif ($map['emergency_contact_no'] === null && (str_contains($normalized, 'emergency_contact_no') || str_contains($normalized, 'emergency contact no') || str_contains($normalized, 'emergency_contact_number') || str_contains($normalized, 'emergency contact phone') || str_contains($normalized, 'emergency_phone') || str_contains($normalized, 'emergency phone') || str_contains($normalized, 'emergency_no') || str_contains($normalized, 'emergency no'))) {
                $map['emergency_contact_no'] = $index;
            }
            // Match disability
            elseif ($map['disability'] === null && (str_contains($normalized, 'disability') || str_contains($normalized, 'handicap') || str_contains($normalized, 'impairment') || str_contains($normalized, 'condition'))) {
                $map['disability'] = $index;
            }
            // Match display-only BMI column
            elseif ($map['bmi'] === null && $normalized === 'bmi') {
                $map['bmi'] = $index;
            }
        }

        return $map;
    }
}
