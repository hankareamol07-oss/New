package com.techguruji.smartschoolhub.ui.students;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.StudentDetailResponse;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityStudentDetailBinding;
import com.techguruji.smartschoolhub.databinding.ItemDetailRowBinding;
import com.techguruji.smartschoolhub.ui.bonafide.BonafideActivity;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.Locale;
import java.util.Map;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * StudentDetailActivity — native student profile with attendance summary and quick actions.
 */
public class StudentDetailActivity extends AppCompatActivity {

    public static final String EXTRA_STUDENT_ID = "student_id";

    private ActivityStudentDetailBinding binding;
    private int studentId;
    private StudentListResponse.Student student;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityStudentDetailBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        studentId = getIntent().getIntExtra(EXTRA_STUDENT_ID, 0);
        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);

        binding.btnBonafide.setOnClickListener(v -> {
            Intent i = new Intent(this, BonafideActivity.class);
            i.putExtra(BonafideActivity.EXTRA_STUDENT_ID, studentId);
            startActivity(i);
        });
        binding.btnCall.setOnClickListener(v -> {
            if (student != null && student.getPhone() != null && !student.getPhone().isEmpty()) {
                startActivity(new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + student.getPhone())));
            } else {
                UiUtils.snack(binding.getRoot(), "फोन नंबर उपलब्ध नाही");
            }
        });
        binding.btnPrint.setOnClickListener(v -> print());

        load();
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getStudentDetail(studentId)
                .enqueue(new Callback<StudentDetailResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<StudentDetailResponse> call,
                                           @NonNull Response<StudentDetailResponse> response) {
                        binding.swipeRefresh.setRefreshing(false);
                        if (response.isSuccessful() && response.body() != null
                                && response.body().success && response.body().student != null) {
                            student = response.body().student;
                            bind(response.body().attendanceSummary);
                        } else {
                            UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<StudentDetailResponse> call, @NonNull Throwable t) {
                        binding.swipeRefresh.setRefreshing(false);
                        UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
                    }
                });
    }

    private void bind(Map<String, Integer> att) {
        String name = UiUtils.safe(student.getDisplayName());
        binding.tvName.setText(name);
        binding.tvNameEn.setText(UiUtils.safe(student.getName()));
        binding.tvNameEn.setVisibility(name.equals(student.getName()) ? View.GONE : View.VISIBLE);
        binding.tvAvatar.setText(name.isEmpty() ? "?" : name.substring(0, 1).toUpperCase(Locale.ROOT));
        binding.tvMeta.setText(UiUtils.gradeLabel(student.getGrade()) + " · "
                + UiUtils.sectionLabel(student.getSection())
                + " · हजेरी क्र. " + UiUtils.orDash(student.getRollNo()));

        int p = att != null && att.get("P") != null ? att.get("P") : 0;
        int a = att != null && att.get("A") != null ? att.get("A") : 0;
        int l = att != null && att.get("L") != null ? att.get("L") : 0;
        int total = p + a + l;
        binding.tvPresent.setText(String.valueOf(p));
        binding.tvAbsent.setText(String.valueOf(a));
        binding.tvPercent.setText(total == 0 ? "—" : Math.round(100.0 * p / total) + "%");

        binding.layoutDetails.removeAllViews();
        addRow("GR क्रमांक", student.getGrNo());
        addRow("हजेरी क्रमांक", student.getRollNo());
        addRow("लिंग", genderLabel(student.getGender()));
        addRow("जन्मतारीख", student.getDateOfBirth());
        addRow("वडिलांचे नाव", student.getFatherName());
        addRow("आईचे नाव", student.getMotherName());
        addRow("फोन", student.getPhone());
        addRow("आधार क्रमांक", maskAadhar(student.getAadharNo()));
        addRow("APAAR ID", student.getApaarId());
        addRow("जात / प्रवर्ग", student.getCaste());
        addRow("रक्तगट", student.getBloodGroup());
    }

    private void addRow(String label, String value) {
        ItemDetailRowBinding row = ItemDetailRowBinding.inflate(getLayoutInflater(), binding.layoutDetails, true);
        row.tvLabel.setText(label);
        row.tvValue.setText(UiUtils.orDash(value));
    }

    private static String genderLabel(String g) {
        if (g == null) return "—";
        switch (g.toUpperCase(Locale.ROOT)) {
            case "M": return "मुलगा";
            case "F": return "मुलगी";
            case "O": return "इतर";
            default: return g;
        }
    }

    private static String maskAadhar(String a) {
        if (a == null || a.length() < 8) return a;
        return "XXXX XXXX " + a.substring(a.length() - 4);
    }

    private void print() {
        if (student == null) return;
        SessionManager session = SessionManager.getInstance(this);
        StringBuilder html = new StringBuilder("<html><head><meta charset='utf-8'><style>")
                .append(UiUtils.printCss("#E64A19")).append("</style></head><body>")
                .append(UiUtils.printHeader(session, "विद्यार्थी माहिती पत्रक"))
                .append("<table>");
        String[][] rows = {
                {"विद्यार्थ्याचे नाव", student.getDisplayName()}, {"Name (English)", student.getName()},
                {"इयत्ता / तुकडी", UiUtils.gradeLabel(student.getGrade()) + " / " + UiUtils.sectionLabel(student.getSection())},
                {"GR क्रमांक", student.getGrNo()}, {"हजेरी क्रमांक", student.getRollNo()},
                {"लिंग", genderLabel(student.getGender())}, {"जन्मतारीख", student.getDateOfBirth()},
                {"वडिलांचे नाव", student.getFatherName()}, {"आईचे नाव", student.getMotherName()},
                {"फोन", student.getPhone()}, {"आधार क्रमांक", student.getAadharNo()},
                {"APAAR ID", student.getApaarId()}, {"जात / प्रवर्ग", student.getCaste()},
                {"रक्तगट", student.getBloodGroup()}};
        for (String[] r : rows) {
            html.append("<tr><th style='width:38%'>").append(r[0]).append("</th><td>")
                    .append(UiUtils.escapeHtml(UiUtils.orDash(r[1]))).append("</td></tr>");
        }
        html.append("</table><div class='sig'><div>वर्गशिक्षक</div><div>मुख्याध्यापक</div></div></body></html>");
        PdfPrintHelper.printHtml(this, html.toString(), "Student_" + studentId);
    }
}
