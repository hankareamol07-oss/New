package com.techguruji.smartschoolhub.ui.students;

import android.os.Bundle;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.datepicker.CalendarConstraints;
import com.google.android.material.datepicker.DateValidatorPointBackward;
import com.google.android.material.datepicker.MaterialDatePicker;
import com.google.android.material.textfield.TextInputEditText;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.AddStudentRequest;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityAddStudentBinding;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.Calendar;
import java.util.TimeZone;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * AddStudentActivity — native student registration form (students.php POST).
 */
public class AddStudentActivity extends AppCompatActivity {

    private static final String[] GENDER_LABELS = {"मुलगा", "मुलगी", "इतर"};
    private static final String[] GENDER_CODES = {"M", "F", "O"};
    private static final String[] CASTES = {"खुला (Open)", "OBC", "SC", "ST", "VJ/NT", "SBC", "EWS", "इतर"};
    private static final String[] BLOOD = {"", "A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"};

    private ActivityAddStudentBinding binding;
    private String dobApi = "";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityAddStudentBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        UiUtils.bindDropdown(binding.ddGrade, UiUtils.GRADE_LABELS, 0);
        UiUtils.bindDropdown(binding.ddSection, UiUtils.SECTION_LABELS, 0);
        UiUtils.bindDropdown(binding.ddGender, GENDER_LABELS, 0);
        UiUtils.bindDropdown(binding.ddCaste, CASTES, -1);
        UiUtils.bindDropdown(binding.ddBlood, BLOOD, -1);

        binding.etDob.setOnClickListener(v -> pickDob());
        binding.fabSave.setOnClickListener(v -> save());
    }

    private void pickDob() {
        MaterialDatePicker<Long> picker = MaterialDatePicker.Builder.datePicker()
                .setTitleText(R.string.dob)
                .setCalendarConstraints(new CalendarConstraints.Builder()
                        .setValidator(DateValidatorPointBackward.now()).build())
                .build();
        picker.addOnPositiveButtonClickListener(millis -> {
            Calendar utc = Calendar.getInstance(TimeZone.getTimeZone("UTC"));
            utc.setTimeInMillis(millis);
            Calendar local = Calendar.getInstance();
            local.set(utc.get(Calendar.YEAR), utc.get(Calendar.MONTH), utc.get(Calendar.DAY_OF_MONTH));
            dobApi = UiUtils.apiDate(local);
            binding.etDob.setText(UiUtils.displayDate(local));
        });
        picker.show(getSupportFragmentManager(), "dob");
    }

    private void save() {
        String name = text(binding.etName);
        if (name.isEmpty()) {
            binding.tilName.setError(getString(R.string.required_field));
            binding.etName.requestFocus();
            return;
        }
        binding.tilName.setError(null);

        String phone = text(binding.etPhone);
        if (!phone.isEmpty() && phone.length() != 10) {
            binding.tilPhone.setError("१० अंकी मोबाईल नंबर टाका");
            return;
        }
        binding.tilPhone.setError(null);

        String aadhar = text(binding.etAadhar);
        if (!aadhar.isEmpty() && aadhar.length() != 12) {
            binding.tilAadhar.setError("१२ अंकी आधार क्रमांक टाका");
            return;
        }
        binding.tilAadhar.setError(null);

        int gIdx = Math.max(0, indexOf(UiUtils.GRADE_LABELS, binding.ddGrade.getText().toString()));
        int sIdx = Math.max(0, indexOf(UiUtils.SECTION_LABELS, binding.ddSection.getText().toString()));
        int genderIdx = Math.max(0, indexOf(GENDER_LABELS, binding.ddGender.getText().toString()));

        AddStudentRequest req = new AddStudentRequest(
                name, text(binding.etNameMr), text(binding.etGrNo),
                text(binding.etRollNo).isEmpty() ? "1" : text(binding.etRollNo),
                UiUtils.GRADES[gIdx], UiUtils.SECTIONS[sIdx], GENDER_CODES[genderIdx], dobApi,
                text(binding.etFather), text(binding.etMother), phone,
                aadhar, text(binding.etApaar),
                binding.ddCaste.getText().toString().trim(),
                binding.ddBlood.getText().toString().trim());

        UiUtils.hideKeyboard(this);
        binding.fabSave.setEnabled(false);
        binding.fabSave.setText(R.string.saving);
        ApiClient.getInstance().getApiService().addStudent(req).enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(@NonNull Call<ApiResponse> call, @NonNull Response<ApiResponse> response) {
                binding.fabSave.setEnabled(true);
                binding.fabSave.setText(R.string.save);
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    setResult(RESULT_OK);
                    UiUtils.snack(binding.getRoot(), getString(R.string.student_saved));
                    binding.getRoot().postDelayed(() -> finish(), 900);
                } else {
                    UiUtils.snack(binding.getRoot(), response.body() != null && response.body().getMessage() != null
                            ? response.body().getMessage() : getString(R.string.error_generic));
                }
            }

            @Override
            public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                binding.fabSave.setEnabled(true);
                binding.fabSave.setText(R.string.save);
                UiUtils.snack(binding.getRoot(), getString(R.string.network_error));
            }
        });
    }

    private static String text(TextInputEditText et) {
        return et.getText() == null ? "" : et.getText().toString().trim();
    }

    private static int indexOf(String[] arr, String v) {
        for (int i = 0; i < arr.length; i++) if (arr[i].equals(v)) return i;
        return -1;
    }
}
