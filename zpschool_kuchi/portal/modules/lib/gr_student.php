<?php
declare(strict_types=1);

require_once __DIR__ . '/module_helpers.php';

function student_form_fields(array $student = []): array
{
    return [
        'book_no'=>['पुस्तक क्र.','number','',true], 'gr_no'=>['GR क्र.','number','',true],
        'admission_date'=>['प्रवेश दिनांक','date','',true], 'admission_class'=>['प्रवेश इयत्ता','select_class','',true],
        'stream'=>['शाखा','select_stream','',false], 'admission_division'=>['प्रवेश तुकडी','text','A, B, C...',false],
        'medium'=>['माध्यम','select_medium','मराठी',true], 'board'=>['बोर्ड','text','SSC, CBSE, ICSE...',false],
        'prev_school_name'=>['मागील शाळेचे नाव','text','',false], 'prev_school_class'=>['मागील इयत्ता','number','',false],
        'aadhar_no'=>['आधार क्र.','text','12 अंक',false], 'first_name'=>['विद्यार्थ्याचे नाव','text','',true],
        'father_name'=>['वडिलांचे नाव','text','',false], 'last_name'=>['आडनाव','text','',true],
        'mother_name'=>['आईचे नाव','text','',false], 'gender'=>['लिंग','select_gender','',true],
        'nationality'=>['राष्ट्रीयत्व','text','भारतीय',false], 'mother_tongue'=>['मातृभाषा','select_tongue','मराठी',false],
        'religion'=>['धर्म','select_religion','',false], 'caste'=>['जात','text','',false],
        'caste_category'=>['जात प्रवर्ग','select_caste','open',false], 'father_mobile'=>['वडिलांचा मोबाईल','text','',false],
        'mother_mobile'=>['आईचा मोबाईल','text','',false], 'dob'=>['जन्म तारीख (अंकी)','date','',true],
        'dob_words'=>['जन्म तारीख (अक्षरी)','text','आपोआप भरेल',false], 'birth_place'=>['जन्मस्थळ','text','',false],
        'birth_taluka'=>['तालुका','text','',false], 'birth_district'=>['जिल्हा','text','',false],
        'birth_state'=>['जन्म राज्य','text','महाराष्ट्र',false], 'birth_country'=>['जन्म देश','text','भारत',false],
        'permanent_address'=>['कायमचा पत्ता','textarea','',false], 'village'=>['गाव','text','',false],
        'student_pen'=>['PEN','text','',false], 'saral_id'=>['सरल आय.डी.','text','',false],
    ];
}

function student_form_value(array $student, string $name): string
{
    $value = $student[$name] ?? '';
    if ($name === 'last_name' && $value === '') {
        $value = $student['surname'] ?? '';
    }
    if ($name === 'aadhar_no' && $value === '') {
        $value = $student['aadhaar'] ?? '';
    }
    if ($name === 'prev_school_name' && $value === '') {
        $value = $student['previous_school'] ?? '';
    }
    if ($name === 'permanent_address' && $value === '') {
        $value = $student['address'] ?? '';
    }
    if ($name === 'father_mobile' && $value === '') {
        $value = $student['phone'] ?? '';
    }
    return (string) $value;
}

function student_payload(array $source, int $classId = 0): array
{
    $date = trim((string) ($source['dob'] ?? ''));
    $lastName = trim((string) ($source['last_name'] ?? $source['surname'] ?? ''));
    $address = trim((string) ($source['permanent_address'] ?? $source['address'] ?? ''));
    $fatherMobile = trim((string) ($source['father_mobile'] ?? $source['phone'] ?? ''));
    return [
        'class_id'=>$classId ?: (int) ($source['class_id'] ?? 0),
        'book_no'=>max(1, (int) ($source['book_no'] ?? 1)),
        'gr_no'=>max(1, (int) ($source['gr_no'] ?? 1)),
        'roll_no'=>max(1, (int) ($source['roll_no'] ?? 1)),
        'admission_date'=>trim((string) ($source['admission_date'] ?? date('Y-m-d'))),
        'admission_class'=>max(1, min(12, (int) ($source['admission_class'] ?? 1))),
        'stream'=>trim((string) ($source['stream'] ?? '')),
        'admission_division'=>trim((string) ($source['admission_division'] ?? '')),
        'medium'=>trim((string) ($source['medium'] ?? 'मराठी')),
        'board'=>trim((string) ($source['board'] ?? '')),
        'prev_school_name'=>trim((string) ($source['prev_school_name'] ?? $source['previous_school'] ?? '')),
        'prev_school_class'=>($source['prev_school_class'] ?? '') === '' ? null : (int) $source['prev_school_class'],
        'aadhar_no'=>trim((string) ($source['aadhar_no'] ?? $source['aadhaar'] ?? '')),
        'first_name'=>trim((string) ($source['first_name'] ?? '')),
        'father_name'=>trim((string) ($source['father_name'] ?? '')),
        'last_name'=>$lastName,
        'mother_name'=>trim((string) ($source['mother_name'] ?? '')),
        'gender'=>trim((string) ($source['gender'] ?? '')),
        'nationality'=>trim((string) ($source['nationality'] ?? 'भारतीय')),
        'mother_tongue'=>trim((string) ($source['mother_tongue'] ?? 'मराठी')),
        'religion'=>trim((string) ($source['religion'] ?? '')),
        'caste'=>trim((string) ($source['caste'] ?? '')),
        'caste_category'=>trim((string) ($source['caste_category'] ?? 'open')),
        'father_mobile'=>$fatherMobile,
        'mother_mobile'=>trim((string) ($source['mother_mobile'] ?? '')),
        'dob'=>$date,
        'dob_words'=>$date !== '' ? marathi_date_words($date) : '',
        'birth_place'=>trim((string) ($source['birth_place'] ?? '')),
        'birth_taluka'=>trim((string) ($source['birth_taluka'] ?? '')),
        'birth_district'=>trim((string) ($source['birth_district'] ?? '')),
        'birth_state'=>trim((string) ($source['birth_state'] ?? 'महाराष्ट्र')),
        'birth_country'=>trim((string) ($source['birth_country'] ?? 'भारत')),
        'permanent_address'=>$address,
        'village'=>trim((string) ($source['village'] ?? '')),
        'student_pen'=>trim((string) ($source['student_pen'] ?? '')),
        'saral_id'=>trim((string) ($source['saral_id'] ?? '')),
        'surname'=>$lastName,
        'aadhaar'=>trim((string) ($source['aadhar_no'] ?? $source['aadhaar'] ?? '')),
        'previous_school'=>trim((string) ($source['prev_school_name'] ?? $source['previous_school'] ?? '')),
        'address'=>$address,
        'phone'=>$fatherMobile,
    ];
}

function render_student_field(string $name, array $definition, array $student): void
{
    [$label, $type, $placeholder, $required] = $definition;
    $value = student_form_value($student, $name);
    echo '<label class="text-sm font-semibold text-slate-700">' . e($label);
    if ($type === 'textarea') {
        echo '<textarea name="' . e($name) . '" class="w-full mt-2" placeholder="' . e($placeholder) . '"' . ($required ? ' required' : '') . '>' . e($value) . '</textarea>';
    } elseif ($type === 'select_class') {
        echo '<select name="' . e($name) . '" class="w-full mt-2"' . ($required ? ' required' : '') . '><option value="">-- निवडा --</option>';
        foreach (class_labels() as $number => $classLabel) echo '<option value="' . $number . '"' . ((int) $value === $number ? ' selected' : '') . '>' . e($classLabel) . '</option>';
        echo '</select>';
    } elseif ($type === 'select_stream') {
        echo '<select name="' . e($name) . '" class="w-full mt-2"><option value="">-- निवडा --</option>';
        foreach (stream_options() as $labelValue => $optionValue) echo '<option value="' . e($labelValue) . '"' . ($value === $labelValue || $value === $optionValue ? ' selected' : '') . '>' . e($labelValue) . '</option>';
        echo '</select>';
    } elseif ($type === 'select_medium') {
        echo '<select name="' . e($name) . '" class="w-full mt-2">';
        foreach (['मराठी','हिंदी','English','उर्दू','सेमी-इंग्रजी'] as $option) echo '<option' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
        echo '</select>';
    } elseif ($type === 'select_gender') {
        echo '<select name="' . e($name) . '" class="w-full mt-2"><option value="">-- निवडा --</option>';
        foreach (student_gender_options() as $labelValue => $optionValue) echo '<option value="' . e($labelValue) . '"' . ($value === $labelValue || $value === $optionValue ? ' selected' : '') . '>' . e($labelValue) . ' (' . e($optionValue === 'female' ? 'G' : ($optionValue === 'male' ? 'M' : 'O')) . ')</option>';
        echo '</select>';
    } elseif ($type === 'select_tongue') {
        echo '<select name="' . e($name) . '" class="w-full mt-2">';
        foreach (mother_tongue_options() as $option) echo '<option' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
        echo '</select>';
    } elseif ($type === 'select_religion') {
        echo '<select name="' . e($name) . '" class="w-full mt-2"><option value="">-- निवडा --</option>';
        foreach (religion_options() as $option) echo '<option' . ($value === $option ? ' selected' : '') . '>' . e($option) . '</option>';
        echo '</select>';
    } elseif ($type === 'select_caste') {
        echo '<select name="' . e($name) . '" class="w-full mt-2">';
        foreach (caste_options() as $labelValue => $optionValue) echo '<option value="' . e($optionValue) . '"' . ($value === $optionValue || $value === $labelValue ? ' selected' : '') . '>' . e($labelValue) . '</option>';
        echo '</select>';
    } else {
        echo '<input name="' . e($name) . '" type="' . e($type) . '" class="w-full mt-2" placeholder="' . e($placeholder) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . ($name === 'dob_words' ? ' readonly' : '') . '>';
    }
    echo '</label>';
}
