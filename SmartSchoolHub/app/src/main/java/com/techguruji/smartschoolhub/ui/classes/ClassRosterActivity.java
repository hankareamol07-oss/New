package com.techguruji.smartschoolhub.ui.classes;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.chip.Chip;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.StudentAdapter;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityClassRosterBinding;
import com.techguruji.smartschoolhub.ui.fee.FeeActivity;
import com.techguruji.smartschoolhub.ui.hajeri.HajeriActivity;
import com.techguruji.smartschoolhub.ui.students.AddStudentActivity;
import com.techguruji.smartschoolhub.ui.students.StudentDetailActivity;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * ClassRosterActivity — students of one class with section chips and quick actions.
 */
public class ClassRosterActivity extends AppCompatActivity {

    public static final String EXTRA_GRADE = "grade";

    private ActivityClassRosterBinding binding;
    private StudentAdapter adapter;
    private String grade = "1";
    private String section = "";
    private List<StudentListResponse.Student> students = new ArrayList<>();

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityClassRosterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        String g = getIntent().getStringExtra(EXTRA_GRADE);
        if (g != null && !g.isEmpty()) grade = g;

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setTitle(UiUtils.gradeLabel(grade));
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        String[] chips = {"सर्व", "अ (A)", "ब (B)", "क (C)", "ड (D)"};
        String[] codes = {"", "A", "B", "C", "D"};
        for (int i = 0; i < chips.length; i++) {
            Chip chip = new Chip(this, null, com.google.android.material.R.attr.chipStyle);
            chip.setText(chips[i]);
            chip.setCheckable(true);
            chip.setChecked(i == 0);
            final String code = codes[i];
            chip.setOnClickListener(v -> {
                section = code;
                load();
            });
            binding.chipSections.addView(chip);
        }

        adapter = new StudentAdapter(new ArrayList<>(), s -> {
            Intent i = new Intent(this, StudentDetailActivity.class);
            i.putExtra(StudentDetailActivity.EXTRA_STUDENT_ID, s.getId());
            startActivity(i);
        });
        binding.rvStudents.setLayoutManager(new LinearLayoutManager(this));
        binding.rvStudents.setAdapter(adapter);

        binding.emptyState.tvEmptyIcon.setText("🎒");
        binding.emptyState.tvEmptyTitle.setText("या वर्गात विद्यार्थी नाहीत");
        binding.emptyState.tvEmptyHint.setText("खालील बटणाने नवीन विद्यार्थी जोडा.");

        binding.btnHajeri.setOnClickListener(v -> {
            Intent i = new Intent(this, HajeriActivity.class);
            i.putExtra(HajeriActivity.EXTRA_GRADE, grade);
            if (!section.isEmpty()) i.putExtra(HajeriActivity.EXTRA_SECTION, section);
            startActivity(i);
        });
        binding.btnFee.setOnClickListener(v -> {
            Intent i = new Intent(this, FeeActivity.class);
            i.putExtra(FeeActivity.EXTRA_GRADE, grade);
            startActivity(i);
        });
        binding.btnPrint.setOnClickListener(v -> {
            if (students.isEmpty()) {
                UiUtils.snack(binding.getRoot(), "छापण्यासाठी विद्यार्थी नाहीत");
                return;
            }
            PdfPrintHelper.printHtml(this,
                    PdfPrintHelper.generateStudentListHtml(SessionManager.getInstance(this), students),
                    "Class_" + grade + section);
        });
        binding.fabAdd.setOnClickListener(v -> startActivity(new Intent(this, AddStudentActivity.class)));

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
    }

    @Override
    protected void onResume() {
        super.onResume();
        load();
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getStudents(grade, section).enqueue(new Callback<StudentListResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentListResponse> call,
                                   @NonNull Response<StudentListResponse> response) {
                binding.swipeRefresh.setRefreshing(false);
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    students = response.body().getStudents() != null ? response.body().getStudents() : new ArrayList<>();
                    adapter.updateList(students);
                    binding.toolbar.setSubtitle(students.size() + " विद्यार्थी"
                            + (section.isEmpty() ? "" : " · " + UiUtils.sectionLabel(section)));
                    binding.emptyState.layoutEmpty.setVisibility(students.isEmpty() ? View.VISIBLE : View.GONE);
                } else {
                    UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                }
            }

            @Override
            public void onFailure(@NonNull Call<StudentListResponse> call, @NonNull Throwable t) {
                binding.swipeRefresh.setRefreshing(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
            }
        });
    }
}
