package com.techguruji.smartschoolhub.ui.fee;

import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.recyclerview.widget.LinearLayoutManager;

import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.FeeModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityFeeBinding;
import com.techguruji.smartschoolhub.databinding.DialogCollectFeeBinding;
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
 * FeeActivity — native fee dashboard: per-grade balances, search, one-tap collection with receipt.
 */
public class FeeActivity extends AppCompatActivity {

    public static final String EXTRA_GRADE = "grade";

    private static final String[] FEE_TYPES = {"शैक्षणिक फी", "परीक्षा फी", "संगणक फी", "वाहतूक फी", "इतर"};
    private static final String[] MODES = {"रोख (Cash)", "UPI", "बँक ट्रान्सफर", "चेक"};
    private static final String[] MODE_CODES = {"cash", "upi", "bank", "cheque"};

    private ActivityFeeBinding binding;
    private FeeStudentAdapter adapter;
    private String grade = "";
    private List<FeeModel.FeeStudent> students = new ArrayList<>();
    private FeeModel.FeeSummary summary;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityFeeBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        String[] gradeLabels = new String[UiUtils.GRADE_LABELS.length + 1];
        gradeLabels[0] = getString(R.string.all_grades);
        System.arraycopy(UiUtils.GRADE_LABELS, 0, gradeLabels, 1, UiUtils.GRADE_LABELS.length);
        String preGrade = getIntent().getStringExtra(EXTRA_GRADE);
        int preIdx = 0;
        if (preGrade != null) {
            for (int i = 0; i < UiUtils.GRADES.length; i++) if (UiUtils.GRADES[i].equals(preGrade)) preIdx = i + 1;
            if (preIdx > 0) grade = preGrade;
        }
        UiUtils.bindDropdown(binding.ddGrade, gradeLabels, preIdx);
        binding.ddGrade.setOnItemClickListener((p, v, pos, id) -> {
            grade = pos == 0 ? "" : UiUtils.GRADES[pos - 1];
            load();
        });

        adapter = new FeeStudentAdapter(this::showCollectDialog);
        binding.rvStudents.setLayoutManager(new LinearLayoutManager(this));
        binding.rvStudents.setAdapter(adapter);

        binding.etSearch.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) {}
            @Override public void onTextChanged(CharSequence s, int a, int b, int c) {}
            @Override public void afterTextChanged(Editable s) {
                adapter.filter(s.toString());
                updateCount();
            }
        });

        binding.emptyState.tvEmptyIcon.setText("💰");
        binding.emptyState.tvEmptyTitle.setText("विद्यार्थी सापडले नाहीत");
        binding.emptyState.tvEmptyHint.setText("इयत्ता बदला किंवा शोध रिकामा करा.");

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
        load();
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.menu_print, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(@NonNull MenuItem item) {
        if (item.getItemId() == R.id.btnPrint) {
            printReport();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getFees(grade.isEmpty() ? null : grade)
                .enqueue(new Callback<FeeModel.FeeResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<FeeModel.FeeResponse> call,
                                           @NonNull Response<FeeModel.FeeResponse> response) {
                        binding.swipeRefresh.setRefreshing(false);
                        if (response.isSuccessful() && response.body() != null && response.body().success) {
                            students = response.body().students != null ? response.body().students : new ArrayList<>();
                            summary = response.body().summary;
                            adapter.submit(students);
                            bindSummary();
                            updateCount();
                        } else {
                            UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<FeeModel.FeeResponse> call, @NonNull Throwable t) {
                        binding.swipeRefresh.setRefreshing(false);
                        UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
                    }
                });
    }

    private void bindSummary() {
        double expected = 0, collected = 0, pending = 0;
        if (summary != null) {
            expected = summary.totalExpected;
            collected = summary.totalCollected;
            pending = summary.totalPending;
        } else {
            for (FeeModel.FeeStudent s : students) {
                expected += s.totalFee;
                collected += s.paidAmount;
                pending += s.pendingAmount;
            }
        }
        binding.tvExpected.setText(UiUtils.rupees(expected));
        binding.tvCollected.setText(UiUtils.rupees(collected));
        binding.tvPending.setText(UiUtils.rupees(pending));
    }

    private void updateCount() {
        int n = adapter.shownCount();
        binding.tvCount.setText(n + " विद्यार्थी");
        binding.emptyState.layoutEmpty.setVisibility(n == 0 ? View.VISIBLE : View.GONE);
    }

    private void showCollectDialog(FeeModel.FeeStudent s) {
        DialogCollectFeeBinding d = DialogCollectFeeBinding.inflate(getLayoutInflater());
        d.tvStudentInfo.setText(s.getDisplayName() + "\n" + UiUtils.gradeLabel(s.grade) + " · "
                + UiUtils.sectionLabel(s.section) + "\nएकूण फी: " + UiUtils.rupees(s.totalFee)
                + " · जमा: " + UiUtils.rupees(s.paidAmount) + " · बाकी: " + UiUtils.rupees(s.pendingAmount));
        if (s.pendingAmount > 0) d.etAmount.setText(String.valueOf((long) s.pendingAmount));
        UiUtils.bindDropdown(d.ddType, FEE_TYPES, 0);
        UiUtils.bindDropdown(d.ddMode, MODES, 0);

        AlertDialog dialog = new MaterialAlertDialogBuilder(this)
                .setTitle(R.string.fee_collect)
                .setView(d.getRoot())
                .setPositiveButton(R.string.fee_collect, null)
                .setNegativeButton(R.string.cancel, null)
                .create();
        dialog.setOnShowListener(x -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(v -> {
            String amtStr = d.etAmount.getText() == null ? "" : d.etAmount.getText().toString().trim();
            double amount;
            try {
                amount = Double.parseDouble(amtStr);
            } catch (NumberFormatException e) {
                amount = 0;
            }
            if (amount <= 0) {
                d.tilAmount.setError("वैध रक्कम टाका");
                return;
            }
            d.tilAmount.setError(null);
            int modeIdx = indexOf(MODES, d.ddMode.getText().toString());
            String mode = MODE_CODES[Math.max(0, modeIdx)];
            String remarks = d.etRemarks.getText() == null ? "" : d.etRemarks.getText().toString().trim();
            dialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(false);
            collect(s, amount, mode, d.ddType.getText().toString(), remarks, dialog);
        }));
        dialog.show();
    }

    private void collect(FeeModel.FeeStudent s, double amount, String mode, String type,
                         String remarks, AlertDialog dialog) {
        FeeModel.FeeCollectRequest req = new FeeModel.FeeCollectRequest(
                s.id, amount, mode, type, UiUtils.apiDate(Calendar.getInstance()), remarks);
        ApiClient.getInstance().getApiService().collectFee(req).enqueue(new Callback<FeeModel.FeeCollectResponse>() {
            @Override
            public void onResponse(@NonNull Call<FeeModel.FeeCollectResponse> call,
                                   @NonNull Response<FeeModel.FeeCollectResponse> response) {
                dialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(true);
                if (response.isSuccessful() && response.body() != null && response.body().success) {
                    dialog.dismiss();
                    showReceipt(s, response.body(), type, mode);
                    load();
                } else {
                    UiUtils.snack(binding.getRoot(), response.body() != null && response.body().message != null
                            ? response.body().message : getString(R.string.error_generic));
                }
            }

            @Override
            public void onFailure(@NonNull Call<FeeModel.FeeCollectResponse> call, @NonNull Throwable t) {
                dialog.getButton(AlertDialog.BUTTON_POSITIVE).setEnabled(true);
                UiUtils.snack(binding.getRoot(), getString(R.string.network_error));
            }
        });
    }

    private void showReceipt(FeeModel.FeeStudent s, FeeModel.FeeCollectResponse r, String type, String mode) {
        String receiptNo = r.receiptNo != null ? r.receiptNo : "—";
        new MaterialAlertDialogBuilder(this)
                .setTitle("✓ फी जमा झाली")
                .setMessage("पावती क्र.: " + receiptNo + "\nविद्यार्थी: " + s.getDisplayName()
                        + "\nरक्कम: " + UiUtils.rupees(r.amount) + "\nप्रकार: " + type
                        + "\nपद्धत: " + mode.toUpperCase(Locale.ROOT) + "\nदिनांक: " + UiUtils.orDash(r.paidOn))
                .setPositiveButton(R.string.fee_receipt, (dlg, w) -> printReceipt(s, r, type, mode))
                .setNegativeButton(R.string.ok, null)
                .show();
    }

    private void printReceipt(FeeModel.FeeStudent s, FeeModel.FeeCollectResponse r, String type, String mode) {
        SessionManager session = SessionManager.getInstance(this);
        String html = "<html><head><meta charset='utf-8'><style>" + UiUtils.printCss("#00897B")
                + ".box{border:1.5px solid #00897B;border-radius:10px;padding:18px;max-width:520px;margin:0 auto}"
                + ".amt{font-size:24px;font-weight:700;color:#00897B;text-align:center;margin:14px 0}"
                + "</style></head><body>"
                + UiUtils.printHeader(session, "फी पावती / Fee Receipt")
                + "<div class='box'><div class='meta'><span>पावती क्र.: <b>" + UiUtils.escapeHtml(UiUtils.orDash(r.receiptNo))
                + "</b></span><span>दिनांक: " + UiUtils.escapeHtml(UiUtils.orDash(r.paidOn)) + "</span></div>"
                + "<table><tr><th>विद्यार्थी</th><td>" + UiUtils.escapeHtml(s.getDisplayName()) + "</td></tr>"
                + "<tr><th>इयत्ता / तुकडी</th><td>" + UiUtils.gradeLabel(s.grade) + " / " + UiUtils.sectionLabel(s.section) + "</td></tr>"
                + "<tr><th>फी प्रकार</th><td>" + UiUtils.escapeHtml(type) + "</td></tr>"
                + "<tr><th>पेमेंट पद्धत</th><td>" + UiUtils.escapeHtml(mode.toUpperCase(Locale.ROOT)) + "</td></tr></table>"
                + "<div class='amt'>" + UiUtils.rupees(r.amount) + "</div>"
                + "<div class='sig'><div>पालकांची सही</div><div>मुख्याध्यापक / लेखापाल</div></div></div>"
                + "</body></html>";
        PdfPrintHelper.printHtml(this, html, "Receipt_" + UiUtils.orDash(r.receiptNo));
    }

    private void printReport() {
        SessionManager session = SessionManager.getInstance(this);
        StringBuilder html = new StringBuilder("<html><head><meta charset='utf-8'><style>")
                .append(UiUtils.printCss("#00897B")).append("</style></head><body>")
                .append(UiUtils.printHeader(session, "फी शिल्लक अहवाल — "
                        + (grade.isEmpty() ? getString(R.string.all_grades) : UiUtils.gradeLabel(grade))))
                .append("<div class='meta'><span>अपेक्षित: ").append(binding.tvExpected.getText())
                .append("</span><span>जमा: ").append(binding.tvCollected.getText())
                .append("</span><span>बाकी: ").append(binding.tvPending.getText()).append("</span></div>")
                .append("<table><thead><tr><th>#</th><th>विद्यार्थी</th><th>इयत्ता</th><th class='right'>एकूण</th><th class='right'>जमा</th><th class='right'>बाकी</th></tr></thead><tbody>");
        int i = 1;
        for (FeeModel.FeeStudent s : students) {
            html.append("<tr><td>").append(i++).append("</td><td>").append(UiUtils.escapeHtml(s.getDisplayName()))
                    .append("</td><td>").append(UiUtils.gradeLabel(s.grade)).append(" ").append(UiUtils.safe(s.section))
                    .append("</td><td class='right'>").append(UiUtils.rupees(s.totalFee))
                    .append("</td><td class='right'>").append(UiUtils.rupees(s.paidAmount))
                    .append("</td><td class='right'>").append(UiUtils.rupees(s.pendingAmount)).append("</td></tr>");
        }
        html.append("</tbody></table><div class='sig'><div>लेखापाल</div><div>मुख्याध्यापक</div></div></body></html>");
        PdfPrintHelper.printHtml(this, html.toString(), "Fee_Report");
    }

    private static int indexOf(String[] arr, String v) {
        for (int i = 0; i < arr.length; i++) if (arr[i].equals(v)) return i;
        return -1;
    }
}
