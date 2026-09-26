package com.techguruji.smartschoolhub.ui.classes;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.GridLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityClassesBinding;
import com.techguruji.smartschoolhub.databinding.ItemClassCardBinding;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.List;
import java.util.TreeSet;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * ClassesActivity — class overview (Std 1–8): strength, gender split, sections. Tap → roster.
 */
public class ClassesActivity extends AppCompatActivity {

    private ActivityClassesBinding binding;
    private final List<ClassSummary> classes = new ArrayList<>();
    private ClassAdapter adapter;

    static class ClassSummary {
        final String grade;
        int total, boys, girls;
        final TreeSet<String> sections = new TreeSet<>();
        ClassSummary(String grade) { this.grade = grade; }
    }

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityClassesBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        adapter = new ClassAdapter();
        binding.rvClasses.setLayoutManager(new GridLayoutManager(this, 2));
        binding.rvClasses.setAdapter(adapter);

        binding.emptyState.tvEmptyIcon.setText("🏫");
        binding.emptyState.tvEmptyTitle.setText("अद्याप विद्यार्थी नोंद नाही");
        binding.emptyState.tvEmptyHint.setText("विद्यार्थी मॉड्यूलमधून प्रथम विद्यार्थी जोडा.");

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
        load();
    }

    @Override
    protected void onResume() {
        super.onResume();
        if (!classes.isEmpty()) load();
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getStudents("", "").enqueue(new Callback<StudentListResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentListResponse> call,
                                   @NonNull Response<StudentListResponse> response) {
                binding.swipeRefresh.setRefreshing(false);
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    summarize(response.body().getStudents());
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

    private void summarize(List<StudentListResponse.Student> students) {
        classes.clear();
        int boys = 0, girls = 0, total = 0;
        for (String g : UiUtils.GRADES) classes.add(new ClassSummary(g));
        if (students != null) {
            for (StudentListResponse.Student s : students) {
                ClassSummary cs = find(UiUtils.safe(s.getGrade()));
                if (cs == null) {
                    cs = new ClassSummary(UiUtils.safe(s.getGrade()));
                    classes.add(cs);
                }
                cs.total++;
                total++;
                if ("F".equalsIgnoreCase(s.getGender())) { cs.girls++; girls++; } else { cs.boys++; boys++; }
                if (s.getSection() != null && !s.getSection().isEmpty()) cs.sections.add(s.getSection());
            }
        }
        int active = 0;
        for (ClassSummary c : classes) if (c.total > 0) active++;
        binding.tvTotal.setText(String.valueOf(total));
        binding.tvBoys.setText(String.valueOf(boys));
        binding.tvGirls.setText(String.valueOf(girls));
        binding.tvClasses.setText(String.valueOf(active));
        binding.emptyState.layoutEmpty.setVisibility(total == 0 ? View.VISIBLE : View.GONE);
        adapter.notifyDataSetChanged();
    }

    private ClassSummary find(String grade) {
        for (ClassSummary c : classes) if (c.grade.equals(grade)) return c;
        return null;
    }

    private class ClassAdapter extends RecyclerView.Adapter<ClassAdapter.VH> {
        @NonNull
        @Override
        public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
            return new VH(ItemClassCardBinding.inflate(LayoutInflater.from(parent.getContext()), parent, false));
        }

        @Override
        public void onBindViewHolder(@NonNull VH h, int position) {
            ClassSummary c = classes.get(position);
            h.b.tvGradeNum.setText(c.grade);
            h.b.tvGrade.setText(UiUtils.gradeLabel(c.grade));
            h.b.tvCount.setText(String.valueOf(c.total));
            h.b.tvBreakdown.setText("मुले " + c.boys + " · मुली " + c.girls);
            StringBuilder sec = new StringBuilder();
            for (String s : c.sections) sec.append(sec.length() == 0 ? "" : ", ").append(s);
            h.b.tvSections.setText(sec.length() == 0 ? "तुकडी नाही" : "तुकड्या: " + sec);
            h.b.getRoot().setAlpha(c.total == 0 ? 0.6f : 1f);
            h.b.getRoot().setOnClickListener(v -> {
                Intent i = new Intent(ClassesActivity.this, ClassRosterActivity.class);
                i.putExtra(ClassRosterActivity.EXTRA_GRADE, c.grade);
                startActivity(i);
                overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
            });
        }

        @Override
        public int getItemCount() { return classes.size(); }

        class VH extends RecyclerView.ViewHolder {
            final ItemClassCardBinding b;
            VH(ItemClassCardBinding b) { super(b.getRoot()); this.b = b; }
        }
    }
}
