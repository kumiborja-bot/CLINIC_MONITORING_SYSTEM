<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Department;
use App\Models\Course;
use App\Models\MedicalRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentImportService
{
    /**
     * Import students from a CSV file path.
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

        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;
        $totalRecords = 0;
        $errors = [];
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

                // Helper to get value dynamically by mapped header key
                $getValue = function($key) use ($row, $headerMap) {
                    $idx = $headerMap[$key] ?? null;
                    return ($idx !== null && isset($row[$idx])) ? trim($row[$idx]) : '';
                };

                // Check for required student ID
                $idNo = $getValue('idno');
                if (empty($idNo)) {
                    $skippedCount++;
                    $errors[] = "Row {$rowCount}: Skipped due to missing Student ID / IDNO.";
                    continue;
                }
                if (Student::where('student_id_number', $idNo)->exists()) {
                    $duplicateCount++;
                    $errors[] = "Row {$rowCount}: Duplicate student ID '{$idNo}' skipped.";
                    continue;
                }

                // Parse birthdate
                $birthdateStr = $getValue('birthdate');
                $birthdate = null;
                $age = null;
                if (!empty($birthdateStr)) {
                    try {
                        // Support common formats
                        $birthdate = Carbon::parse($birthdateStr)->format('Y-m-d');
                        $age = Carbon::parse($birthdate)->age;
                    } catch (\Exception $e) {
                        Log::warning("Row {$rowCount}: Failed to parse birthdate '{$birthdateStr}'");
                    }
                }

                // Map Sex/Gender
                $gender = strtoupper($getValue('gender'));
                $sex = null;
                if ($gender === 'M' || $gender === 'MALE') {
                    $sex = 'Male';
                } elseif ($gender === 'F' || $gender === 'FEMALE') {
                    $sex = 'Female';
                }

                // Construct Name (LASTNAME, FIRSTNAME MIDDLE)
                $lastName = $getValue('lastname');
                $firstName = $getValue('firstname');
                $middleName = $getValue('middlename');

                if (empty($lastName) && empty($firstName)) {
                    $skippedCount++;
                    $errors[] = "Row {$rowCount}: Skipped due to missing student name.";
                    continue;
                }

                $name = $lastName;
                if (!empty($firstName)) {
                    $name .= (empty($name) ? '' : ', ') . $firstName;
                }
                if (!empty($middleName) && $middleName !== 'N/A' && $middleName !== 'n/a') {
                    $name .= ' ' . $middleName;
                }
                $name = trim($name);

                // Map Program to Department and Course
                $programName = $getValue('program_name');
                $mapped = self::mapProgramToDeptAndCourse($programName);

                // Construct Address
                $addressParts = array_filter([
                    $getValue('address_st'),
                    $getValue('address_city'),
                    $getValue('address_province'),
                    $getValue('address_zip')
                ]);
                $address = implode(', ', $addressParts);
                if (empty($address) || $address === ', , ,') {
                    $address = null;
                }

                // Emergency Contact details from Parents
                $emergencyPerson = '';
                $emergencyRelationship = '';
                
                $fatherFirst = $getValue('father_firstname');
                $fatherLast = $getValue('father_lastname');
                $fatherMiddle = $getValue('father_middlename');
                
                $motherFirst = $getValue('mother_firstname');
                $motherLast = $getValue('mother_lastname');
                $motherMiddle = $getValue('mother_middlename');

                if (!empty($fatherFirst) || !empty($fatherLast)) {
                    $fatherName = trim($fatherFirst . ' ' . $fatherMiddle . ' ' . $fatherLast);
                    $fatherName = preg_replace('/\s+/', ' ', $fatherName);
                    $emergencyPerson = $fatherName;
                    $emergencyRelationship = 'Father';
                } elseif (!empty($motherFirst) || !empty($motherLast)) {
                    $motherName = trim($motherFirst . ' ' . $motherMiddle . ' ' . $motherLast);
                    $motherName = preg_replace('/\s+/', ' ', $motherName);
                    $emergencyPerson = $motherName;
                    $emergencyRelationship = 'Mother';
                }

                // Update or Create Student
                $student = Student::create([
                        'student_id_number' => $idNo,
                        'name' => $name,
                        'birthdate' => $birthdate,
                        'age' => $age,
                        'sex' => $sex,
                        'address' => $address,
                        'department_id' => $mapped['department_id'],
                        'course_id' => $mapped['course_id'],
                        'year_level' => $getValue('year_level'),
                        'emergency_contact_person' => $emergencyPerson ?: null,
                        'emergency_contact_relationship' => $emergencyRelationship ?: null,
                        'emergency_contact_address' => $emergencyPerson ? $address : null,
                    ]);

                // Handle Disability / Medical Record creation
                $disability = $getValue('disability');
                $cleanDisability = strtolower($disability);
                $ignoredDisabilities = ['n/a', 'n\\a', 'none', 'na', 'n.a.', '-', 'n/a.', 'n\\a.', 'no', 'nome', 'nothing', 'n', 'nk', 'non'];
                if (!empty($disability) && !in_array($cleanDisability, $ignoredDisabilities)) {
                    $student->medicalRecord()->updateOrCreate(
                        [
                            'recordable_type' => Student::class,
                            'recordable_id' => $student->id
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
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            Log::error("Failed to import students CSV: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Import failed: " . $e->getMessage(),
                'imported_count' => 0,
                'duplicate_count' => $duplicateCount ?? 0,
                'total_count' => $totalRecords ?? 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Match headers dynamically to indexes in the CSV row.
     */
    private function matchHeadersDynamically(array $headers): array
    {
        $map = [
            'idno' => null,
            'lastname' => null,
            'firstname' => null,
            'middlename' => null,
            'gender' => null,
            'birthdate' => null,
            'program_name' => null,
            'year_level' => null,
            'address_st' => null,
            'address_city' => null,
            'address_province' => null,
            'address_zip' => null,
            'father_lastname' => null,
            'father_firstname' => null,
            'father_middlename' => null,
            'mother_lastname' => null,
            'mother_firstname' => null,
            'mother_middlename' => null,
            'disability' => null,
            'lrn' => null,
        ];

        // 1. First pass: Match exact or strong indicators for student ID (excluding LRN)
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));
            if ($normalized === 'idno' || $normalized === 'id_no' || $normalized === 'idno.' || $normalized === 'student_id' || $normalized === 'id_number' || $normalized === 'student id' || $normalized === 'id') {
                $map['idno'] = $index;
                break;
            }
        }

        // 2. Second pass: Match everything else
        foreach ($headers as $index => $header) {
            $normalized = strtolower(trim($header));
            
            // Match LRN specifically
            if ($normalized === 'lrn' || str_contains($normalized, 'lrn')) {
                $map['lrn'] = $index;
                // If we didn't find a strong IDNO column, use LRN as IDNO
                if ($map['idno'] === null) {
                    $map['idno'] = $index;
                }
                continue;
            }

            // Match last name
            if ($map['lastname'] === null && (str_contains($normalized, 'lastname') || str_contains($normalized, 'last_name') || str_contains($normalized, 'last name') || str_contains($normalized, 'surname') || str_contains($normalized, 'family name') || str_contains($normalized, 'family_name'))) {
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
            // Match gender
            elseif ($map['gender'] === null && (str_contains($normalized, 'gender') || str_contains($normalized, 'sex'))) {
                $map['gender'] = $index;
            }
            // Match birthdate
            elseif ($map['birthdate'] === null && (str_contains($normalized, 'birthdate') || str_contains($normalized, 'birth_date') || str_contains($normalized, 'birth date') || str_contains($normalized, 'bdate') || str_contains($normalized, 'dob') || str_contains($normalized, 'birthday') || str_contains($normalized, 'birth_day'))) {
                $map['birthdate'] = $index;
            }
            // Match program/course
            elseif ($map['program_name'] === null && (str_contains($normalized, 'program_name') || str_contains($normalized, 'program name') || str_contains($normalized, 'program') || str_contains($normalized, 'course') || str_contains($normalized, 'strand') || str_contains($normalized, 'degree'))) {
                $map['program_name'] = $index;
            }
            // Match year level
            elseif ($map['year_level'] === null && (str_contains($normalized, 'year_level') || str_contains($normalized, 'year level') || str_contains($normalized, 'year') || str_contains($normalized, 'yr') || str_contains($normalized, 'level') || str_contains($normalized, 'grade_level') || str_contains($normalized, 'grade level') || $normalized === 'grade')) {
                $map['year_level'] = $index;
            }
            // Match address street/barangay
            elseif ($map['address_st'] === null && (str_contains($normalized, 'address_st') || str_contains($normalized, 'street') || str_contains($normalized, 'brgy') || str_contains($normalized, 'barangay') || str_contains($normalized, 'addr') || $normalized === 'address')) {
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
            // Match father names
            elseif (str_contains($normalized, 'father')) {
                if ($map['father_lastname'] === null && (str_contains($normalized, 'lastname') || str_contains($normalized, 'last_name') || str_contains($normalized, 'last name') || str_contains($normalized, 'surname') || str_contains($normalized, 'family'))) {
                    $map['father_lastname'] = $index;
                } elseif ($map['father_firstname'] === null && (str_contains($normalized, 'firstname') || str_contains($normalized, 'first_name') || str_contains($normalized, 'first name') || str_contains($normalized, 'given'))) {
                    $map['father_firstname'] = $index;
                } elseif ($map['father_middlename'] === null && (str_contains($normalized, 'middlename') || str_contains($normalized, 'middle_name') || str_contains($normalized, 'middle name') || str_contains($normalized, 'middle'))) {
                    $map['father_middlename'] = $index;
                }
            }
            // Match mother names
            elseif (str_contains($normalized, 'mother')) {
                if ($map['mother_lastname'] === null && (str_contains($normalized, 'lastname') || str_contains($normalized, 'last_name') || str_contains($normalized, 'last name') || str_contains($normalized, 'surname') || str_contains($normalized, 'family'))) {
                    $map['mother_lastname'] = $index;
                } elseif ($map['mother_firstname'] === null && (str_contains($normalized, 'firstname') || str_contains($normalized, 'first_name') || str_contains($normalized, 'first name') || str_contains($normalized, 'given'))) {
                    $map['mother_firstname'] = $index;
                } elseif ($map['mother_middlename'] === null && (str_contains($normalized, 'middlename') || str_contains($normalized, 'middle_name') || str_contains($normalized, 'middle name') || str_contains($normalized, 'middle'))) {
                    $map['mother_middlename'] = $index;
                }
            }
            // Match disability
            elseif ($map['disability'] === null && (str_contains($normalized, 'disability') || str_contains($normalized, 'handicap') || str_contains($normalized, 'impairment') || str_contains($normalized, 'condition'))) {
                $map['disability'] = $index;
            }
        }

        // 3. Fallback: If we still don't have idno mapped but have an ID header on second pass
        if ($map['idno'] === null) {
            foreach ($headers as $index => $header) {
                $normalized = strtolower(trim($header));
                if (str_contains($normalized, 'idno') || str_contains($normalized, 'id_no') || str_contains($normalized, 'student_id') || str_contains($normalized, 'id_number') || str_contains($normalized, 'student id') || $normalized === 'id') {
                    $map['idno'] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Map a program/course name from CSV to Department and Course models.
     *
     * @param string $programName
     * @return array
     */
    private static function mapProgramToDeptAndCourse(string $programName): array
    {
        $programName = strtoupper(trim($programName));
        
        $deptCode = 'CTEAS'; // Default
        $courseName = null;

        // Segregate Junior High and Senior High into Basic Education (BSED) department
        if (str_contains($programName, 'JUNIOR HIGH') || str_contains($programName, 'JHS') || preg_match('/GRADE\s*(7|8|9|10)\b/', $programName)) {
            $deptCode = 'BSED';
            $courseName = 'Junior High School';
        } elseif (str_contains($programName, 'SENIOR HIGH') || str_contains($programName, 'SHS') || preg_match('/GRADE\s*(11|12)\b/', $programName) || str_contains($programName, 'STRAND') || str_contains($programName, 'GAS') || str_contains($programName, 'HUMSS') || str_contains($programName, 'ABM') || str_contains($programName, 'STEM') || str_contains($programName, 'TVL')) {
            $deptCode = 'BSED';
            $courseName = 'Senior High School';
        }
        // College courses
        elseif (str_contains($programName, 'INFORMATION SYSTEMS') || str_contains($programName, 'INFORMATION SYSTEM')) {
            $deptCode = 'CCLS';
            $courseName = 'Information System';
        } elseif (str_contains($programName, 'LIBRARY') || str_contains($programName, 'INFORMATION SCIENCE')) {
            $deptCode = 'CCLS';
            $courseName = 'Library Information System';
        } elseif (str_contains($programName, 'CRIMINOLOGY')) {
            $deptCode = 'CCJE';
            $courseName = 'Criminology';
        } elseif (str_contains($programName, 'PSYCHOLOGY')) {
            $deptCode = 'CTEAS';
            $courseName = 'Psychology';
        } elseif (str_contains($programName, 'PHYSICAL EDUCATION') || str_contains($programName, 'BSED')) {
            $deptCode = 'CTEAS';
            $courseName = 'Education';
        } elseif (str_contains($programName, 'TOURISM')) {
            $deptCode = 'CBTM';
            $courseName = 'Tourism';
        } elseif (str_contains($programName, 'ENTREPRENEURSHIP') || str_contains($programName, 'ENTREPRENEUR')) {
            $deptCode = 'CBTM';
            $courseName = 'Entrepreneur';
        } elseif (str_contains($programName, 'MANAGEMENT ACCOUNTING') || str_contains($programName, 'ACCOUNTING')) {
            $deptCode = 'CBTM';
            $courseName = 'Management Accounting';
        } elseif (str_contains($programName, 'PUBLIC ADMINISTRATION')) {
            $deptCode = 'CBTM';
            $courseName = 'Public Administration';
        } elseif (str_contains($programName, 'MARINE TRANSPORTATION') || str_contains($programName, 'TRANSPORTATION')) {
            $deptCode = 'CME';
            $courseName = 'Transportation';
        } elseif (str_contains($programName, 'MARINE ENGINEERING') || str_contains($programName, 'ENGINEERING')) {
            $deptCode = 'CME';
            $courseName = 'Engineering';
        } elseif (!empty($programName)) {
            $courseName = ucwords(strtolower($programName));
        }

        // Get or create department
        $dept = Department::firstOrCreate(
            ['code' => $deptCode],
            [
                'name' => $deptCode,
                'full_name' => self::getDepartmentFullName($deptCode),
                'type' => 'student',
            ]
        );

        // Get or create course
        $course = null;
        if ($courseName) {
            $course = Course::firstOrCreate(
                [
                    'department_id' => $dept->id,
                    'name' => $courseName
                ],
                [
                    'code' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $courseName), 0, 4))
                ]
            );
        }

        return [
            'department_id' => $dept->id,
            'course_id' => $course ? $course->id : null
        ];
    }

    /**
     * Helper to get full name of seeded departments.
     *
     * @param string $code
     * @return string
     */
    private static function getDepartmentFullName(string $code): string
    {
        $names = [
            'CTEAS' => 'College of Teacher Education Arts and Sciences',
            'CCLS' => 'College of Computing and Library Studies',
            'CBTM' => 'College of Business and Tourism Management',
            'CME' => 'College of Maritime Education',
            'CCJE' => 'College of Criminal Justice Education',
            'BSED' => 'Basic Education',
        ];
        return $names[$code] ?? $code;
    }
}
