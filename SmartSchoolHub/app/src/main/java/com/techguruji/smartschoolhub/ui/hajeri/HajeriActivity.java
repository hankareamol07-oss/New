package com.techguruji.smartschoolhub.ui.hajeri;

import android.app.DatePickerDialog;
import android.os.Bundle;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.AttendanceAdapter;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.model.AttendanceModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityHajeriBinding;

import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

public class HajeriActivity extends AppCompatActivity {

    private ActivityHajeriBinding binding;
    private AttendanceAdapter adapter;
    private final Calendar selectedCalendar = Calendar.getInstance();
    private final SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault());
    private final String[] grades = {"1", "2", "3", "4", "5", "6", "7", "8"};
    private final String[] gradeNames = {"१ ली", "२ री", "३ री", "४ थी", "५ वी", "६ वी", "७ वी", "८ वी"};
    private final String[] sections = {"A", "B", "C", "अ", "ब", "क"};
    private String selectedGrade = "1";
    private String selectedSection = "A";

    public static final String EXTRA_GRADE = "grade";
    public static final String EXTRA_SECTION = "section";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityHajeriBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        String g = getIntent().getStringExtra(EXTRA_GRADE);
        String s = getIntent().getStringExtra(EXTRA_SECTION);
        if (g != null && !g.isEmpty()) selectedGrade = g;
        if (s != null && !s.isEmpty()) selectedSection = s;

        setupToolbar();
        setupFilters();
        setupRecyclerView();
        setupActions();

        loadAttendance();
    }

    private void setupToolbar() {
        setSupportActionBar(binding.toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("दैनंदिन हजेरी (Attendance)");
        }
        binding.toolbar.setNavigationOnClickListener(v -> finish());
    }

    private void setupFilters() {
        updateDateButtonText();
        binding.btnDate.setOnClickListener(v -> showDatePicker());

        // Grade Spinner
        ArrayAdapter<String> gradeAdapter = new ArrayAdapter<>(this,
                android.R.layout.simple_spinner_dropdown_item, gradeNames);
        binding.spGrade.setAdapter(gradeAdapter);
        binding.spGrade.setSelection(Math.max(0, Arrays.asList(grades).indexOf(selectedGrade)), false);
        binding.spGrade.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedGrade = grades[position];
                loadAttendance();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });

        // Section Spinner
        ArrayAdapter<String> sectionAdapter = new ArrayAdapter<>(this,
                android.R.layout.simple_spinner_dropdown_item, sections);
        binding.spSection.setAdapter(sectionAdapter);
        binding.spSection.setSelection(Math.max(0, Arrays.asList(sections).indexOf(selectedSection)), false);
        binding.spSection.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedSection = sections[position];
                loadAttendance();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void showDatePicker() {
        new DatePickerDialog(this,
                (view, year, month, dayOfMonth) -> {
                    selectedCalendar.set(Calendar.YEAR, year);
                    selectedCalendar.set(Calendar.MONTH, month);
                    selectedCalendar.set(Calendar.DAY_OF_MONTH, dayOfMonth);
                    updateDateButtonText();
                    loadAttendance();
                },
                selectedCalendar.get(Calendar.YEAR),
                selectedCalendar.get(Calendar.MONTH),
                selectedCalendar.get(Calendar.DAY_OF_MONTH)
        ).show();
    }

    private void updateDateButtonText() {
        binding.btnDate.setText(dateFormat.format(selectedCalendar.getTime()));
    }

    private void setupRecyclerView() {
        adapter = new AttendanceAdapter();
        adapter.setOnStatusChangeListener(this::updateStats);
        binding.rvAttendance.setLayoutManager(new LinearLayoutManager(this));
        binding.rvAttendance.setAdapter(adapter);

        binding.swipeRefresh.setOnRefreshListener(this::loadAttendance);
    }

    private void setupActions() {
        // Quick "All Present"
        binding.btnAllPresent.setOnClickListener(v -> {
            adapter.setAllStatus("P");
            updateStats();
        });

        // Save Attendance
        binding.btnSaveAttendance.setOnClickListener(v -> saveAttendance());
    }

    private void updateStats() {
        List<AttendanceModel.AttendanceStudent> list = adapter.getStudents();
        int total = list.size();
        int present = 0;
        int absent = 0;

        for (AttendanceModel.AttendanceStudent s : list) {
            if ("A".equalsIgnoreCase(s.status)) absent++;
            else present++;
        }

        binding.tvStatTotal.setText("एकूण: " + total);
        binding.tvStatPresent.setText("हजर: " + present);
        binding.tvStatAbsent.setText("गैरहजर: " + absent);
    }

    private void loadAttendance() {
        binding.progressBar.setVisibility(View.VISIBLE);
        binding.layoutEmpty.setVisibility(View.GONE);

        String dateStr = dateFormat.format(selectedCalendar.getTime());
        ApiClient.getInstance().getApiService().getAttendance(dateStr, selectedGrade, selectedSection)
                .enqueue(new Callback<AttendanceModel.AttendanceResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<AttendanceModel.AttendanceResponse> call,
                                           @NonNull Response<AttendanceModel.AttendanceResponse> response) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);

                        if (response.isSuccessful() && response.body() != null
                                && response.body().success && response.body().students != null) {
                            adapter.setStudents(response.body().students);
                            updateStats();

                            if (response.body().students.isEmpty()) {
                                binding.layoutEmpty.setVisibility(View.VISIBLE);
                            }
                        } else {
                            binding.layoutEmpty.setVisibility(View.VISIBLE);
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<AttendanceModel.AttendanceResponse> call, @NonNull Throwable t) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);
                        Snackbar.make(binding.getRoot(), "हजेरी लोड करण्यात अडचण: " + t.getMessage(),
                                Snackbar.LENGTH_SHORT).show();
                    }
                });
    }

    private void saveAttendance() {
        List<AttendanceModel.AttendanceStudent> list = adapter.getStudents();
        if (list.isEmpty()) {
            Toast.makeText(this, "हजेरी नोंदवण्यासाठी विद्यार्थी नाहीत", Toast.LENGTH_SHORT).show();
            return;
        }

        binding.btnSaveAttendance.setEnabled(false);
        binding.btnSaveAttendance.setText("जतन होत आहे...");

        List<AttendanceModel.StudentStatusItem> statusList = new ArrayList<>();
        for (AttendanceModel.AttendanceStudent s : list) {
            statusList.add(new AttendanceModel.StudentStatusItem(s.id, s.status));
        }

        String dateStr = dateFormat.format(selectedCalendar.getTime());
        AttendanceModel.AttendanceSaveRequest request =
                new AttendanceModel.AttendanceSaveRequest(dateStr, statusList);

        ApiClient.getInstance().getApiService().saveAttendance(request)
                .enqueue(new Callback<ApiResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<ApiResponse> call,
                                           @NonNull Response<ApiResponse> response) {
                        binding.btnSaveAttendance.setEnabled(true);
                        binding.btnSaveAttendance.setText("हजेरी जतन करा (Save) 💾");

                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            Toast.makeText(HajeriActivity.this, "✅ " + response.body().getMessage(),
                                    Toast.LENGTH_LONG).show();
                        } else {
                            String msg = response.body() != null ? response.body().getMessage() : "त्रुटी आली";
                            Snackbar.make(binding.getRoot(), msg, Snackbar.LENGTH_LONG).show();
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                        binding.btnSaveAttendance.setEnabled(true);
                        binding.btnSaveAttendance.setText("हजेरी जतन करा (Save) 💾");
                        Snackbar.make(binding.getRoot(), "नेटवर्क त्रुटी: " + t.getMessage(),
                                Snackbar.LENGTH_SHORT).show();
                    }
                });
    }
}
