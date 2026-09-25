package com.techguruji.smartschoolhub.ui.bonafide;

import android.os.Bundle;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.StudentDetailResponse;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityBonafideBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * BonafideActivity — native one-tap Bonafide certificate generator (Marathi + English).
 */
public class BonafideActivity extends AppCompatActivity {

    public static final String EXTRA_STUDENT_ID = "student_id";

    private static final String[] PURPOSES = {
            "शिष्यवृत्ती (Scholarship)", "बँक खाते उघडणे", "पासपोर्ट / ओळखपत्र",
            "जात प्रमाणपत्र", "शाळा बदली (Transfer)", "क्रीडा स्पर्धा", "इतर"};

    private ActivityBonafideBinding binding;
    private List<StudentListResponse.Student> students = new ArrayList<>();
    private StudentListResponse.Student selected;
    private String grade = UiUtils.GRADES[0];
    private String section = UiUtils.SECTIONS[0];

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityBonafideBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        UiUtils.bindDropdown(binding.ddGrade, UiUtils.GRADE_LABELS, 0);
        UiUtils.bindDropdown(binding.ddSection, UiUtils.SECTION_LABELS, 0);
        UiUtils.bindDropdown(binding.ddPurpose, PURPOSES, 0);
        binding.etYear.setText(academicYear());

        binding.ddGrade.setOnItemClickListener((p, v, pos, id) -> {
            grade = UiUtils.GRADES[pos];
            loadStudents();
        });
        binding.ddSection.setOnItemClickListener((p, v, pos, id) -> {
            section = UiUtils.SECTIONS[pos];
            loadStudents();
        });
        binding.ddStudent.setOnItemClickListener((p, v, pos, id) -> select(students.get(pos)));
        binding.btnGenerate.setOnClickListener(v -> generate());

        int preselect = getIntent().getIntExtra(EXTRA_STUDENT_ID, 0);
        if (preselect > 0) {
            loadSingle(preselect);
        } else {
            loadStudents();
        }
    }

    private void loadSingle(int id) {
        binding.progress.setVisibility(View.VISIBLE);
        ApiClient.getInstance().getApiService().getStudentDetail(id).enqueue(new Callback<StudentDetailResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentDetailResponse> call,
                                   @NonNull Response<StudentDetailResponse> response) {
                binding.progress.setVisibility(View.GONE);
                if (response.isSuccessful() && response.body() != null && response.body().student != null) {
                    StudentListResponse.Student s = response.body().student;
                    grade = UiUtils.safe(s.getGrade());
                    section = UiUtils.safe(s.getSection());
                    binding.ddGrade.setText(UiUtils.gradeLabel(grade), false);
                    binding.ddSection.setText(UiUtils.sectionLabel(section), false);
                    students = new ArrayList<>();
                    students.add(s);
                    bindStudentDropdown();
                    binding.ddStudent.setText(label(s), false);
                    select(s);
                } else {
                    loadStudents();
                }
            }

            @Override
            public void onFailure(@NonNull Call<StudentDetailResponse> call, @NonNull Throwable t) {
                binding.progress.setVisibility(View.GONE);
                UiUtils.snack(binding.getRoot(), getString(R.string.network_error));
            }
        });
    }

    private void loadStudents() {
        selected = null;
        binding.btnGenerate.setEnabled(false);
        binding.cardPreview.setVisibility(View.GONE);
        binding.ddStudent.setText("", false);
        binding.progress.setVisibility(View.VISIBLE);
        ApiClient.getInstance().getApiService().getStudents(grade, section).enqueue(new Callback<StudentListResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentListResponse> call,
                                   @NonNull Response<StudentListResponse> response) {
                binding.progress.setVisibility(View.GONE);
                students = response.isSuccessful() && response.body() != null && response.body().getStudents() != null
                        ? response.body().getStudents() : new ArrayList<>();
                bindStudentDropdown();
                binding.tilStudent.setHelperText(students.isEmpty() ? "या वर्गात विद्यार्थी नाहीत" : students.size() + " विद्यार्थी");
            }

            @Override
            public void onFailure(@NonNull Call<StudentListResponse> call, @NonNull Throwable t) {
                binding.progress.setVisibility(View.GONE);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> loadStudents());
            }
        });
    }

    private void bindStudentDropdown() {
        String[] labels = new String[students.size()];
        for (int i = 0; i < students.size(); i++) labels[i] = label(students.get(i));
        UiUtils.bindDropdown(binding.ddStudent, labels, -1);
    }

    private static String label(StudentListResponse.Student s) {
        String roll = s.getRollNo() == null || s.getRollNo().isEmpty() ? "" : s.getRollNo() + ". ";
        return roll + s.getDisplayName();
    }

    private void select(StudentListResponse.Student s) {
        selected = s;
        binding.btnGenerate.setEnabled(true);
        binding.cardPreview.setVisibility(View.VISIBLE);
        binding.tvPreview.setText(bodyMarathi(s));
    }

    private String bodyMarathi(StudentListResponse.Student s) {
        boolean female = "F".equalsIgnoreCase(s.getGender());
        String pronoun = female ? "ती" : "तो";
        String studentWord = female ? "विद्यार्थिनी" : "विद्यार्थी";
        String parent = s.getFatherName() == null || s.getFatherName().isEmpty() ? "" : " (पालक: " + s.getFatherName() + ")";
        return "प्रमाणित करण्यात येते की, " + s.getDisplayName() + parent + " हा/ही या शाळेचा/ची " + studentWord
                + " असून " + pronoun + " शैक्षणिक वर्ष " + text(binding.etYear) + " मध्ये "
                + UiUtils.gradeLabel(s.getGrade()) + " (तुकडी " + UiUtils.safe(s.getSection()) + ") मध्ये शिकत आहे."
                + (s.getDateOfBirth() == null || s.getDateOfBirth().isEmpty() ? "" : " जन्मतारीख: " + s.getDateOfBirth() + ".")
                + (s.getGrNo() == null || s.getGrNo().isEmpty() ? "" : " GR क्रमांक: " + s.getGrNo() + ".")
                + (binding.swConduct.isChecked() ? " शाळेतील वर्तणूक चांगली आहे." : "")
                + "\n\nहा दाखला " + binding.ddPurpose.getText() + " या कारणासाठी देण्यात येत आहे.";
    }

    private void generate() {
        if (selected == null) return;
        SessionManager session = SessionManager.getInstance(this);
        String certNo = text(binding.etCertNo);
        String today = UiUtils.displayDate(Calendar.getInstance());
        String html = "<html><head><meta charset='utf-8'><style>" + UiUtils.printCss("#6A1B9A")
                + "body{padding:18px}.frame{border:4px double #6A1B9A;padding:28px 34px;min-height:640px}"
                + ".ctitle{font-size:26px;font-weight:700;text-align:center;color:#6A1B9A;letter-spacing:2px;margin:18px 0 4px}"
                + ".csub{text-align:center;color:#475569;font-size:13px;margin-bottom:24px}"
                + ".body{font-size:16px;line-height:2;text-align:justify}"
                + ".body b{color:#1e293b}.photo{float:right;width:100px;height:120px;border:1px dashed #94a3b8;text-align:center;font-size:10px;color:#94a3b8;line-height:120px;margin-left:12px}"
                + "</style></head><body><div class='frame'>"
                + UiUtils.printHeader(session, "")
                + "<div class='meta'><span>दाखला क्र.: " + UiUtils.escapeHtml(certNo.isEmpty() ? "______" : certNo)
                + "</span><span>दिनांक: " + today + "</span></div>"
                + "<div class='ctitle'>बोनाफाईड दाखला</div><div class='csub'>BONAFIDE CERTIFICATE</div>"
                + "<div class='photo'>फोटो</div>"
                + "<div class='body'>" + UiUtils.escapeHtml(bodyMarathi(selected)) + "</div>"
                + "<div class='body' style='font-size:13px;color:#475569;margin-top:14px'>This is to certify that <b>"
                + UiUtils.escapeHtml(UiUtils.safe(selected.getName())) + "</b> is a bonafide student of this school, studying in Std. "
                + UiUtils.escapeHtml(UiUtils.safe(selected.getGrade())) + " (Div. " + UiUtils.escapeHtml(UiUtils.safe(selected.getSection()))
                + ") during the academic year " + UiUtils.escapeHtml(text(binding.etYear)) + ".</div>"
                + "<div class='sig'><div>वर्गशिक्षक</div><div>मुख्याध्यापक<br/><span class='muted'>(शिक्का व सही)</span></div></div>"
                + "</div></body></html>";
        PdfPrintHelper.printHtml(this, html, "Bonafide_" + selected.getId());
    }

    private String text(com.google.android.material.textfield.TextInputEditText et) {
        return et.getText() == null ? "" : et.getText().toString().trim();
    }

    private static String academicYear() {
        Calendar c = Calendar.getInstance();
        int y = c.get(Calendar.YEAR);
        if (c.get(Calendar.MONTH) < Calendar.JUNE) y--;
        return String.format(Locale.US, "%d-%02d", y, (y + 1) % 100);
    }
}
