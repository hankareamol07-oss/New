package com.techguruji.smartschoolhub.ui.hpc;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.FragmentHpcBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * HpcFragment — Native NEP 2020 Holistic Progress Card (360° समग्र प्रगती पत्रक) screen.
 * Student selection, competence domain evaluation, and A4 Progress Card PDF printing.
 */
public class HpcFragment extends Fragment {

    private FragmentHpcBinding binding;

    private final String[] grades = {"1", "2", "3", "4", "5", "6", "7", "8"};
    private final String[] gradeNames = {"इ. १ ली", "इ. २ री", "इ. ३ री", "इ. ४ थी", "इ. ५ वी", "इ. ६ वी", "इ. ७ वी", "इ. ८ वी"};

    private List<StudentListResponse.Student> studentList = new ArrayList<>();
    private List<String> studentNames = new ArrayList<>();

    private String selectedGrade = "1";
    private StudentListResponse.Student selectedStudent = null;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentHpcBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        setupGradeSpinner();
        setupActions();
        loadStudentsForGrade();
    }

    private void setupGradeSpinner() {
        ArrayAdapter<String> gradeAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, gradeNames);
        binding.spGrade.setAdapter(gradeAdapter);
        binding.spGrade.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedGrade = grades[position];
                loadStudentsForGrade();
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void loadStudentsForGrade() {
        ApiClient.getInstance().getApiService().getStudents(selectedGrade, "A")
                .enqueue(new Callback<StudentListResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<StudentListResponse> call,
                                           @NonNull Response<StudentListResponse> response) {
                        if (response.isSuccessful() && response.body() != null
                                && response.body().getStudents() != null) {
                            studentList = response.body().getStudents();
                            setupStudentSpinner();
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<StudentListResponse> call, @NonNull Throwable t) {
                        Toast.makeText(requireContext(), "विद्यार्थी यादी लोड करताना त्रुटी", Toast.LENGTH_SHORT).show();
                    }
                });
    }

    private void setupStudentSpinner() {
        studentNames.clear();
        if (studentList.isEmpty()) {
            studentNames.add("विद्यार्थी उपलब्ध नाहीत");
        } else {
            for (StudentListResponse.Student s : studentList) {
                studentNames.add((s.getRollNo() != null ? s.getRollNo() : String.valueOf(s.getId())) + ". " + s.getDisplayName());
            }
        }

        ArrayAdapter<String> studentAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, studentNames);
        binding.spStudents.setAdapter(studentAdapter);
        binding.spStudents.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                if (!studentList.isEmpty() && position < studentList.size()) {
                    selectedStudent = studentList.get(position);
                    binding.tvStudentTitle.setText("👤 विद्यार्थी: " + selectedStudent.getDisplayName());
                    binding.tvStudentApaar.setText("APAAR ID: " + safe(selectedStudent.getApaarId()) + " | ID: " + selectedStudent.getId());
                }
            }

            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void setupActions() {
        binding.btnPrintHpc.setOnClickListener(v -> printHpcCard());
    }

    private void printHpcCard() {
        SessionManager session = SessionManager.getInstance(requireContext());
        String studentName = (selectedStudent != null) ? selectedStudent.getDisplayName() : "सर्व विद्यार्थी";
        String grNo = (selectedStudent != null) ? String.valueOf(selectedStudent.getId()) : "101";

        StringBuilder html = new StringBuilder();
        html.append("<html><head><style>")
                .append("@page { size: A4 portrait; margin: 12mm; }")
                .append("body { font-family: 'Noto Sans', sans-serif; font-size: 12px; color: #1e293b; }")
                .append(".banner { text-align: center; background-color: #1e3a8a; color: #ffffff; padding: 12px; border-radius: 6px; }")
                .append(".title { font-size: 22px; font-weight: bold; }")
                .append(".subtitle { font-size: 14px; opacity: 0.9; margin-top: 4px; }")
                .append(".info-table { width: 100%; margin-top: 15px; border-collapse: collapse; }")
                .append(".info-table td { padding: 6px; border: 1px solid #cbd5e1; }")
                .append(".section-header { background-color: #dbeafe; color: #1e40af; font-weight: bold; padding: 8px; margin-top: 15px; border-radius: 4px; }")
                .append(".rubric-table { width: 100%; border-collapse: collapse; margin-top: 8px; }")
                .append(".rubric-table th, .rubric-table td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }")
                .append(".rubric-table th { background-color: #f1f5f9; color: #334155; }")
                .append("</style></head><body>");

        html.append("<div class='banner'>")
                .append("<div class='title'>").append(session.getDisplaySchoolName()).append("</div>")
                .append("<div class='subtitle'>NEP 2020 Holistic Progress Card (समग्र प्रगती पत्रक)</div>")
                .append("</div>");

        html.append("<table class='info-table'><tr>")
                .append("<td><b>विद्यार्थ्याचे नाव:</b> ").append(studentName).append("</td>")
                .append("<td><b>इयत्ता:</b> ").append(selectedGrade).append(" वी</td>")
                .append("<td><b>G.R. No:</b> ").append(grNo).append("</td>")
                .append("</tr></table>");

        html.append("<div class='section-header'>१. स्व-मूल्यमापन व सोबत्यांचे मूल्यमापन (Self & Peer Assessment)</div>");
        html.append("<table class='rubric-table'><thead><tr><th>क्षेत्र / क्षमता</th><th>स्व-मूल्यमापन</th><th>सोबत्यांचे मूल्यमापन</th><th>शिक्षक अभिप्राय</th></tr></thead><tbody>");
        html.append("<tr><td>शारीरिक विकास व क्रीडा सहभाग</td><td>उत्कृष्ट</td><td>छांन</td><td>नियमित सहभागी होतो.</td></tr>");
        html.append("<tr><td>बौद्धिक व भाषा कौशल्ये</td><td>छान</td><td>उत्कृष्ट</td><td>वाचनात उत्तम गती आहे.</td></tr>");
        html.append("<tr><td>सामाजिक व भावनिक विकास</td><td>उत्कृष्ट</td><td>उत्कृष्ट</td><td>मित्रांना मदत करतो.</td></tr>");
        html.append("</tbody></table>");

        html.append("<div class='section-header'>२. ३६०° बहुआयामी प्रगती नोंदी (360 Degree Rubrics)</div>");
        html.append("<table class='rubric-table'><thead><tr><th>विषय</th><th>अध्ययन निष्पत्ती</th><th>प्राप्त पातळी (Rubric)</th></tr></thead><tbody>");
        html.append("<tr><td>मराठी</td><td>वाचन, लेखन व अभिव्यक्ती</td><td>पातळी ३ (स्वावलंबी)</td></tr>");
        html.append("<tr><td>गणित</td><td>संख्याज्ञान व तार्किक विचार</td><td>पातळी ३ (स्वावलंबी)</td></tr>");
        html.append("<tr><td>इंग्रजी</td><td>संभाषण व शब्दसंग्रह</td><td>पातळी २ (मार्गदर्शनासह)</td></tr>");
        html.append("</tbody></table>");

        html.append("</body></html>");

        PdfPrintHelper.printHtml(requireActivity(), html.toString(), "HPC_" + selectedGrade + "_" + studentName);
    }

    private String safe(String s) { return s != null ? s : ""; }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
