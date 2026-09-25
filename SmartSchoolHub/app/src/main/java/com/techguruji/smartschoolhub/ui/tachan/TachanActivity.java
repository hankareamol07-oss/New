package com.techguruji.smartschoolhub.ui.tachan;

import android.app.DatePickerDialog;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.widget.AdapterView;
import android.widget.ArrayAdapter;
import android.widget.EditText;
import android.widget.Spinner;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.adapter.TachanAdapter;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.model.TachanModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityTachanBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * TachanActivity — Native Daily Lesson Diary (दैनंदिन टाचण वही) screen.
 * Date selector, grade filter, lesson plan list, add plan dialog, and PDF export.
 */
public class TachanActivity extends AppCompatActivity {

    private ActivityTachanBinding binding;
    private TachanAdapter adapter;
    private final Calendar selectedCalendar = Calendar.getInstance();
    private final SimpleDateFormat dateFormat = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault());
    private final String[] grades = {"1", "2", "3", "4", "5", "6", "7", "8"};
    private final String[] gradeNames = {"इ. १ ली", "इ. २ री", "इ. ३ री", "इ. ४ थी", "इ. ५ वी", "इ. ६ वी", "इ. ७ वी", "इ. ८ वी"};
    private String selectedGrade = "1";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityTachanBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupToolbar();
        setupFilters();
        setupRecyclerView();
        setupActions();

        loadTachan();
    }

    private void setupToolbar() {
        setSupportActionBar(binding.toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("दैनंदिन टाचण वही (Lesson Plan)");
        }
        binding.toolbar.setNavigationOnClickListener(v -> finish());
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.menu_print, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(@NonNull MenuItem item) {
        if (item.getItemId() == R.id.btnPrint) {
            exportTachanPdf();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    private void setupFilters() {
        binding.btnDate.setText(dateFormat.format(selectedCalendar.getTime()));
        binding.btnDate.setOnClickListener(v -> showDatePicker());

        ArrayAdapter<String> gradeAdapter = new ArrayAdapter<>(this,
                android.R.layout.simple_spinner_dropdown_item, gradeNames);
        binding.spGrade.setAdapter(gradeAdapter);
        binding.spGrade.setOnItemSelectedListener(new AdapterView.OnItemSelectedListener() {
            @Override
            public void onItemSelected(AdapterView<?> parent, View view, int position, long id) {
                selectedGrade = grades[position];
                loadTachan();
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
                    binding.btnDate.setText(dateFormat.format(selectedCalendar.getTime()));
                    loadTachan();
                },
                selectedCalendar.get(Calendar.YEAR),
                selectedCalendar.get(Calendar.MONTH),
                selectedCalendar.get(Calendar.DAY_OF_MONTH)
        ).show();
    }

    private void setupRecyclerView() {
        adapter = new TachanAdapter();
        binding.rvTachan.setLayoutManager(new LinearLayoutManager(this));
        binding.rvTachan.setAdapter(adapter);

        binding.swipeRefresh.setOnRefreshListener(this::loadTachan);
    }

    private void setupActions() {
        binding.fabAddTachan.setOnClickListener(v -> showAddTachanDialog());
    }

    private void loadTachan() {
        binding.progressBar.setVisibility(View.VISIBLE);
        binding.layoutEmpty.setVisibility(View.GONE);

        String dateStr = dateFormat.format(selectedCalendar.getTime());
        ApiClient.getInstance().getApiService().getTachan(dateStr, selectedGrade)
                .enqueue(new Callback<TachanModel.TachanResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<TachanModel.TachanResponse> call,
                                           @NonNull Response<TachanModel.TachanResponse> response) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);

                        if (response.isSuccessful() && response.body() != null
                                && response.body().plans != null) {
                            adapter.setItems(response.body().plans);
                            if (response.body().plans.isEmpty()) {
                                binding.layoutEmpty.setVisibility(View.VISIBLE);
                            }
                        } else {
                            binding.layoutEmpty.setVisibility(View.VISIBLE);
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<TachanModel.TachanResponse> call, @NonNull Throwable t) {
                        binding.progressBar.setVisibility(View.GONE);
                        binding.swipeRefresh.setRefreshing(false);
                        Snackbar.make(binding.getRoot(), "नेटवर्क अडचण: " + t.getMessage(), Snackbar.LENGTH_SHORT).show();
                    }
                });
    }

    private void showAddTachanDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        View dialogView = LayoutInflater.from(this).inflate(R.layout.dialog_add_tachan, null);

        EditText etPeriod = dialogView.findViewById(R.id.etPeriod);
        EditText etSubject = dialogView.findViewById(R.id.etSubject);
        EditText etTopic = dialogView.findViewById(R.id.etTopic);
        EditText etOutcome = dialogView.findViewById(R.id.etOutcome);
        EditText etMaterials = dialogView.findViewById(R.id.etMaterials);
        EditText etHomework = dialogView.findViewById(R.id.etHomework);

        builder.setView(dialogView)
                .setTitle("➕ नवीन टाचण नोंदवा")
                .setPositiveButton("जतन करा", (dialog, which) -> {
                    String periodStr = etPeriod.getText().toString().trim();
                    int period = periodStr.isEmpty() ? 1 : Integer.parseInt(periodStr);
                    String subject = etSubject.getText().toString().trim();
                    String topic = etTopic.getText().toString().trim();
                    String outcome = etOutcome.getText().toString().trim();
                    String materials = etMaterials.getText().toString().trim();
                    String homework = etHomework.getText().toString().trim();

                    if (topic.isEmpty()) {
                        Toast.makeText(this, "कृपया घटक टाका", Toast.LENGTH_SHORT).show();
                        return;
                    }

                    saveTachan(period, subject.isEmpty() ? "मराठी" : subject, topic, outcome, materials, homework);
                })
                .setNegativeButton("रद्द करा", null)
                .show();
    }

    private void saveTachan(int period, String subject, String topic, String outcome, String materials, String homework) {
        String dateStr = dateFormat.format(selectedCalendar.getTime());
        TachanModel.AddTachanRequest req = new TachanModel.AddTachanRequest(
                dateStr, selectedGrade, period, subject, topic, outcome, materials, "दैनंदिन निरीक्षण", homework, ""
        );

        ApiClient.getInstance().getApiService().addTachan(req)
                .enqueue(new Callback<ApiResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<ApiResponse> call, @NonNull Response<ApiResponse> response) {
                        if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                            Toast.makeText(TachanActivity.this, "✅ टाचण यशस्वीरित्या जतन झाले!", Toast.LENGTH_SHORT).show();
                            loadTachan();
                        } else {
                            Toast.makeText(TachanActivity.this, "जतन करणे अयशस्वी.", Toast.LENGTH_SHORT).show();
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                        Toast.makeText(TachanActivity.this, "नेटवर्क त्रुटी: " + t.getMessage(), Toast.LENGTH_SHORT).show();
                    }
                });
    }

    private void exportTachanPdf() {
        List<TachanModel.TachanItem> plans = adapter.getItems();
        if (plans.isEmpty()) {
            Toast.makeText(this, "प्रिंट करण्यासाठी टाचण नोंद उपलब्ध नाही.", Toast.LENGTH_SHORT).show();
            return;
        }

        SessionManager session = SessionManager.getInstance(this);
        String dateStr = dateFormat.format(selectedCalendar.getTime());

        StringBuilder html = new StringBuilder();
        html.append("<html><head><style>")
                .append("@page { size: A4 portrait; margin: 15mm; }")
                .append("body { font-family: 'Noto Sans', sans-serif; font-size: 13px; color: #1e293b; }")
                .append(".header { text-align: center; border-bottom: 2px solid #ea580c; padding-bottom: 10px; margin-bottom: 15px; }")
                .append(".school-name { font-size: 20px; font-weight: bold; color: #c2410c; }")
                .append("table { width: 100%; border-collapse: collapse; margin-top: 10px; }")
                .append("th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }")
                .append("th { background-color: #ffedd5; color: #c2410c; }")
                .append("</style></head><body>");

        html.append("<div class='header'>")
                .append("<div class='school-name'>").append(session.getDisplaySchoolName()).append("</div>")
                .append("<div>दैनंदिन पाठ टाचण वही | इयत्ता: ").append(selectedGrade).append(" | दिनांक: ").append(dateStr).append("</div>")
                .append("</div>");

        html.append("<table><thead><tr>")
                .append("<th>तासिका</th><th>विषय</th><th>घटक</th><th>अध्ययन निष्पत्ती</th><th>साधने</th><th>गृहपाठ</th>")
                .append("</tr></thead><tbody>");

        for (TachanModel.TachanItem p : plans) {
            html.append("<tr>")
                    .append("<td>तासिका ").append(p.period).append("</td>")
                    .append("<td><b>").append(p.subject != null ? p.subject : "").append("</b></td>")
                    .append("<td>").append(p.topic != null ? p.topic : "").append("</td>")
                    .append("<td>").append(p.learningOutcome != null ? p.learningOutcome : "").append("</td>")
                    .append("<td>").append(p.materials != null ? p.materials : "").append("</td>")
                    .append("<td>").append(p.homework != null ? p.homework : "").append("</td>")
                    .append("</tr>");
        }

        html.append("</tbody></table></body></html>");

        PdfPrintHelper.printHtml(this, html.toString(), "Tachan_" + selectedGrade + "_" + dateStr);
    }
}
