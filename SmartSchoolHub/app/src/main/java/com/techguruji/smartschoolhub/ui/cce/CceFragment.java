package com.techguruji.smartschoolhub.ui.cce;

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
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.adapter.CceAdapter;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.model.CceModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.FragmentCceBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * CceFragment — Native Continuous and Comprehensive Evaluation (सातत्यपूर्ण सर्वंकष मूल्यमापन) screen.
 * Select Grade, Subject, Term, and Evaluation Tool to fill student marks and export A4 CCE Sheets.
 */
public class CceFragment extends Fragment {

    private FragmentCceBinding binding;
    private CceAdapter adapter;

    private final String[] grades = {"1", "2", "3", "4", "5", "6", "7", "8"};
    private final String[] gradeNames = {"इ. १ ली", "इ. २ री", "इ. ३ री", "इ. ४ थी", "इ. ५ वी", "इ. ६ वी", "इ. ७ वी", "इ. ८ वी"};

    private final String[] subjects = {"मराठी", "गणित", "इंग्रजी", "परिसर अभ्यास", "हिंदी", "कला", "कार्यान्वय", "शारीरिक शिक्षण"};
    private final String[] terms = {"सत्र १", "सत्र २"};

    private final String[] tools = {
            "आकारिक_१ (दैनंदिन निरीक्षण)",
            "आकारिक_२ (तोंडी काम)",
            "आकारिक_३ (प्रात्यक्षिक)",
            "आकारिक_४ (उपक्रम)",
            "आकारिक_५ (चाचणी)",
            "संकलित (सत्र परीक्षा)"
    };

    private String selectedGrade = "1";
    private String selectedSubject = "मराठी";
    private int selectedTerm = 1;
    private String selectedTool = "आकारिक_१";

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentCceBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        setupSpinners();
        setupRecyclerView();
        setupActions();

        loadCceMarks();
    }

    private void setupSpinners() {
        ArrayAdapter<String> gradeAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, gradeNames);
        binding.spGrade.setAdapter(gradeAdapter);
        binding.spGrade.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedGrade = grades[position];
                loadCceMarks();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });

        ArrayAdapter<String> subjectAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, subjects);
        binding.spSubject.setAdapter(subjectAdapter);
        binding.spSubject.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedSubject = subjects[position];
                loadCceMarks();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });

        ArrayAdapter<String> termAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, terms);
        binding.spTerm.setAdapter(termAdapter);
        binding.spTerm.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedTerm = position + 1;
                loadCceMarks();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });

        ArrayAdapter<String> toolAdapter = new ArrayAdapter<>(requireContext(),
                android.R.layout.simple_spinner_dropdown_item, tools);
        binding.spTool.setAdapter(toolAdapter);
        binding.spTool.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedTool = tools[position].split(" ")[0];
                loadCceMarks();
            }
            @Override
            public void onNothingSelected(AdapterView<?> parent) {}
        });
    }

    private void setupRecyclerView() {
        adapter = new CceAdapter();
        binding.rvCceStudents.setLayoutManager(new LinearLayoutManager(requireContext()));
        binding.rvCceStudents.setAdapter(adapter);

        binding.swipeRefresh.setOnRefreshListener(this::loadCceMarks);
    }

    private void setupActions() {
        binding.btnSaveMarks.setOnClickListener(v -> saveCceMarks());
        binding.btnPrintPdf.setOnClickListener(v -> printCceReport());
    }

    private void loadCceMarks() {
        binding.progressBar.setVisibility(View.VISIBLE);
        binding.layoutEmpty.setVisibility(View.GONE);

        ApiClient.getInstance().getApiService()
                .getCceMarks(selectedGrade, selectedSubject, selectedTerm, selectedTool)
                .enqueue(new Callback<CceModel.CceResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<CceModel.CceResponse> call,
                                           @NonNull Response<CceModel.CceResponse> response) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);

                        if (response.isSuccessful() && response.body() != null
                                && response.body().students != null) {
                            adapter.setStudents(response.body().students);
                            if (response.body().students.isEmpty()) {
                                binding.layoutEmpty.setVisibility(View.VISIBLE);
                            }
                        } else {
                            binding.layoutEmpty.setVisibility(View.VISIBLE);
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<CceModel.CceResponse> call, @NonNull Throwable t) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);
                        Snackbar.make(binding.getRoot(), "नेटवर्क अडचण: " + t.getMessage(), Snackbar.LENGTH_SHORT).show();
                    }
                });
    }

    private void saveCceMarks() {
        List<CceModel.CceStudent> list = adapter.getStudents();
        if (list.isEmpty()) {
            Toast.makeText(requireContext(), "गुण जतन करण्यासाठी विद्यार्थी उपलब्ध नाहीत", Toast.LENGTH_SHORT).show();
            return;
        }

        binding.btnSaveMarks.setEnabled(false);
        binding.btnSaveMarks.setText("जतन होत आहे...");

        List<CceModel.StudentMarkItem> markItems = new ArrayList<>();
        for (CceModel.CceStudent s : list) {
            markItems.add(new CceModel.StudentMarkItem(s.id, s.marks));
        }

        CceModel.CceSaveRequest req = new CceModel.CceSaveRequest(
                selectedGrade, selectedSubject, selectedTerm, selectedTool, 20.0, markItems
        );

        ApiClient.getInstance().getApiService().saveCceMarks(req)
                .enqueue(new Callback<ApiResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<ApiResponse> call, @NonNull Response<ApiResponse> response) {
                        binding.btnSaveMarks.setEnabled(true);
                        binding.btnSaveMarks.setText("गुण जतन करा (Save) 💾");

                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            Toast.makeText(requireContext(), "✅ " + response.body().getMessage(), Toast.LENGTH_LONG).show();
                        } else {
                            Toast.makeText(requireContext(), "जतन करणे अयशस्वी.", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                        binding.btnSaveMarks.setEnabled(true);
                        binding.btnSaveMarks.setText("गुण जतन करा (Save) 💾");
                        Snackbar.make(binding.getRoot(), "नेटवर्क त्रुटी: " + t.getMessage(), Snackbar.LENGTH_SHORT).show();
                    }
                });
    }

    private void printCceReport() {
        List<CceModel.CceStudent> list = adapter.getStudents();
        if (list.isEmpty()) {
            Toast.makeText(requireContext(), "प्रिंट करण्यासाठी विद्यार्थी उपलब्ध नाहीत", Toast.LENGTH_SHORT).show();
            return;
        }

        SessionManager session = SessionManager.getInstance(requireContext());
        StringBuilder html = new StringBuilder();
        html.append("<html><head><style>")
                .append("@page { size: A4 portrait; margin: 15mm; }")
                .append("body { font-family: 'Noto Sans', sans-serif; font-size: 13px; color: #1e293b; }")
                .append(".header { text-align: center; border-bottom: 2px solid #0284c7; padding-bottom: 10px; margin-bottom: 15px; }")
                .append(".school-name { font-size: 20px; font-weight: bold; color: #0369a1; }")
                .append("table { width: 100%; border-collapse: collapse; margin-top: 10px; }")
                .append("th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: center; }")
                .append("th { background-color: #e0f2fe; color: #0369a1; }")
                .append("</style></head><body>");

        html.append("<div class='header'>")
                .append("<div class='school-name'>").append(session.getDisplaySchoolName()).append("</div>")
                .append("<div>सातत्यपूर्ण सर्वंकष मूल्यमापन (CCE) नोंद तक्ता</div>")
                .append("<div>इयत्ता: ").append(selectedGrade).append(" | विषय: ").append(selectedSubject)
                .append(" | ").append(terms[selectedTerm - 1]).append(" | साधन: ").append(selectedTool).append("</div>")
                .append("</div>");

        html.append("<table><thead><tr>")
                .append("<th>हजेरी क्र.</th><th style='text-align:left;'>विद्यार्थ्याचे नाव</th><th>प्राप्त गुण (२० पैकी)</th><th>श्रेणी (Grade)</th>")
                .append("</tr></thead><tbody>");

        for (CceModel.CceStudent s : list) {
            double pct = (s.marks / Math.max(1, s.maxMarks > 0 ? s.maxMarks : 20.0)) * 100.0;
            String grade = "E";
            if (s.marks > 0) {
                if (pct >= 91) grade = "A1";
                else if (pct >= 81) grade = "A2";
                else if (pct >= 71) grade = "B1";
                else if (pct >= 61) grade = "B2";
                else if (pct >= 51) grade = "C1";
                else if (pct >= 41) grade = "C2";
                else if (pct >= 33) grade = "D";
            } else {
                grade = "—";
            }

            html.append("<tr>")
                    .append("<td>").append(s.rollNo != null ? s.rollNo : s.id).append("</td>")
                    .append("<td style='text-align:left;'><b>").append(s.getDisplayName()).append("</b></td>")
                    .append("<td>").append(s.marks > 0 ? s.marks : "—").append("</td>")
                    .append("<td><b>").append(grade).append("</b></td>")
                    .append("</tr>");
        }

        html.append("</tbody></table></body></html>");

        PdfPrintHelper.printHtml(requireActivity(), html.toString(), "CCE_" + selectedGrade + "_" + selectedSubject);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
