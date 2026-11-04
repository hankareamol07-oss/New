package com.techguruji.smartschoolhub.ui.gr;

import android.content.Intent;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityGrRegisterBinding;
import com.techguruji.smartschoolhub.databinding.ItemGrRowBinding;
import com.techguruji.smartschoolhub.ui.students.StudentDetailActivity;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.Collections;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * GrRegisterActivity — General Register: all admitted students ordered by GR number, printable A4 register.
 */
public class GrRegisterActivity extends AppCompatActivity {

    private ActivityGrRegisterBinding binding;
    private final List<StudentListResponse.Student> all = new ArrayList<>();
    private final List<StudentListResponse.Student> shown = new ArrayList<>();
    private String grade = "";
    private String query = "";
    private final GrAdapter adapter = new GrAdapter();

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityGrRegisterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        String[] gradeLabels = new String[UiUtils.GRADE_LABELS.length + 1];
        gradeLabels[0] = getString(R.string.all_grades);
        System.arraycopy(UiUtils.GRADE_LABELS, 0, gradeLabels, 1, UiUtils.GRADE_LABELS.length);
        UiUtils.bindDropdown(binding.ddGrade, gradeLabels, 0);
        binding.ddGrade.setOnItemClickListener((p, v, pos, id) -> {
            grade = pos == 0 ? "" : UiUtils.GRADES[pos - 1];
            load();
        });
        binding.etSearch.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int st, int c, int a) {}
            @Override public void onTextChanged(CharSequence s, int st, int b, int c) {}
            @Override public void afterTextChanged(Editable s) {
                query = s.toString().trim().toLowerCase(Locale.ROOT);
                filter();
            }
        });

        binding.rvGr.setLayoutManager(new LinearLayoutManager(this));
        binding.rvGr.setAdapter(adapter);

        binding.emptyState.tvEmptyIcon.setText("📒");
        binding.emptyState.tvEmptyTitle.setText("नोंद सापडली नाही");
        binding.emptyState.tvEmptyHint.setText("इयत्ता बदला किंवा शोध शब्द तपासा.");

        binding.fabPrint.setOnClickListener(v -> print());
        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
        load();
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getStudents(grade, "").enqueue(new Callback<StudentListResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentListResponse> call,
                                   @NonNull Response<StudentListResponse> response) {
                binding.swipeRefresh.setRefreshing(false);
                all.clear();
                if (response.isSuccessful() && response.body() != null && response.body().getStudents() != null) {
                    all.addAll(response.body().getStudents());
                    Collections.sort(all, (a, b) -> Integer.compare(grNum(a), grNum(b)));
                } else {
                    UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                }
                filter();
            }

            @Override
            public void onFailure(@NonNull Call<StudentListResponse> call, @NonNull Throwable t) {
                binding.swipeRefresh.setRefreshing(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
            }
        });
    }

    private static int grNum(StudentListResponse.Student s) {
        try {
            return Integer.parseInt(UiUtils.safe(s.getGrNo()).replaceAll("\\D", ""));
        } catch (NumberFormatException e) {
            return Integer.MAX_VALUE;
        }
    }

    private void filter() {
        shown.clear();
        for (StudentListResponse.Student s : all) {
            if (query.isEmpty()
                    || UiUtils.safe(s.getName()).toLowerCase(Locale.ROOT).contains(query)
                    || UiUtils.safe(s.getNameMr()).contains(query)
                    || UiUtils.safe(s.getGrNo()).toLowerCase(Locale.ROOT).contains(query)
                    || UiUtils.safe(s.getFatherName()).toLowerCase(Locale.ROOT).contains(query)) {
                shown.add(s);
            }
        }
        binding.toolbar.setSubtitle(shown.size() + " नोंदी · General Register");
        binding.emptyState.layoutEmpty.setVisibility(shown.isEmpty() ? View.VISIBLE : View.GONE);
        adapter.notifyDataSetChanged();
    }

    private void print() {
        if (shown.isEmpty()) {
            UiUtils.snack(binding.getRoot(), "छापण्यासाठी नोंदी नाहीत");
            return;
        }
        SessionManager session = SessionManager.getInstance(this);
        StringBuilder sb = new StringBuilder();
        sb.append("<html><head><meta charset='utf-8'><style>").append(UiUtils.printCss("#BF360C"))
                .append("@page{size:A4 landscape;margin:10mm}th{font-size:10px}td{font-size:10.5px}</style></head><body>")
                .append(UiUtils.printHeader(session, "जनरल रजिस्टर (General Register)"
                        + (grade.isEmpty() ? "" : " — " + UiUtils.gradeLabel(grade))))
                .append("<div class='meta'><span>एकूण नोंदी: ").append(shown.size())
                .append("</span><span>दिनांक: ").append(UiUtils.displayDate(Calendar.getInstance())).append("</span></div>")
                .append("<table><tr><th>GR क्र.</th><th>विद्यार्थ्याचे नाव</th><th>आई/वडिलांचे नाव</th><th>लिंग</th>"
                        + "<th>जन्मतारीख</th><th>जात/प्रवर्ग</th><th>आधार</th><th>इयत्ता/तुकडी</th><th>मोबाईल</th><th>शेरा</th></tr>");
        for (StudentListResponse.Student s : shown) {
            sb.append("<tr><td>").append(e(s.getGrNo())).append("</td><td><b>").append(e(s.getDisplayName())).append("</b>")
                    .append(s.getNameMr() == null || s.getNameMr().isEmpty() ? "" : "<br/><span class='muted'>" + e(s.getName()) + "</span>")
                    .append("</td><td>").append(e(s.getFatherName()))
                    .append(s.getMotherName() == null || s.getMotherName().isEmpty() ? "" : " / " + e(s.getMotherName()))
                    .append("</td><td>").append("F".equalsIgnoreCase(s.getGender()) ? "स्त्री" : "M".equalsIgnoreCase(s.getGender()) ? "पुरुष" : "—")
                    .append("</td><td>").append(e(s.getDateOfBirth()))
                    .append("</td><td>").append(e(s.getCaste()))
                    .append("</td><td>").append(e(s.getAadharNo()))
                    .append("</td><td>").append(e(s.getGrade())).append(" / ").append(e(s.getSection()))
                    .append("</td><td>").append(e(s.getPhone()))
                    .append("</td><td></td></tr>");
        }
        sb.append("</table><div class='sig'><div>लिपिक</div><div>मुख्याध्यापक</div></div></body></html>");
        PdfPrintHelper.printHtml(this, sb.toString(), "GR_Register" + (grade.isEmpty() ? "" : "_Std" + grade));
    }

    private static String e(String s) {
        return s == null || s.isEmpty() ? "—" : UiUtils.escapeHtml(s);
    }

    private class GrAdapter extends RecyclerView.Adapter<GrAdapter.VH> {
        @NonNull
        @Override
        public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
            return new VH(ItemGrRowBinding.inflate(LayoutInflater.from(parent.getContext()), parent, false));
        }

        @Override
        public void onBindViewHolder(@NonNull VH h, int pos) {
            StudentListResponse.Student s = shown.get(pos);
            h.b.tvGrNo.setText(UiUtils.orDash(s.getGrNo()));
            h.b.tvName.setText(s.getDisplayName());
            h.b.tvLine2.setText(UiUtils.gradeLabel(s.getGrade()) + " · " + UiUtils.sectionLabel(s.getSection())
                    + (s.getDateOfBirth() == null || s.getDateOfBirth().isEmpty() ? "" : " · जन्म: " + s.getDateOfBirth()));
            h.b.tvLine3.setText("पालक: " + UiUtils.orDash(s.getFatherName())
                    + (s.getCaste() == null || s.getCaste().isEmpty() ? "" : " · " + s.getCaste()));
            h.b.getRoot().setOnClickListener(v -> {
                Intent i = new Intent(GrRegisterActivity.this, StudentDetailActivity.class);
                i.putExtra(StudentDetailActivity.EXTRA_STUDENT_ID, s.getId());
                startActivity(i);
            });
        }

        @Override
        public int getItemCount() { return shown.size(); }

        class VH extends RecyclerView.ViewHolder {
            final ItemGrRowBinding b;
            VH(ItemGrRowBinding b) { super(b.getRoot()); this.b = b; }
        }
    }
}
