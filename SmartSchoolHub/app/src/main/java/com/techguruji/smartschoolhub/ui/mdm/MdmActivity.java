package com.techguruji.smartschoolhub.ui.mdm;

import android.os.Bundle;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.datepicker.MaterialDatePicker;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.ApiResponse;
import com.techguruji.smartschoolhub.data.model.MdmModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityMdmBinding;
import com.techguruji.smartschoolhub.databinding.ViewMdmGroupBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;
import java.util.TimeZone;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * MdmActivity — native Mid-Day Meal (पोषण आहार) daily register.
 * Two class groups (1–5, 6–8) with beneficiaries, menu, cooked/holiday flags and remarks.
 */
public class MdmActivity extends AppCompatActivity {

    private static final double RICE_1_5 = 0.100, DAL_1_5 = 0.020;
    private static final double RICE_6_8 = 0.150, DAL_6_8 = 0.030;

    private ActivityMdmBinding binding;
    private final Calendar selected = Calendar.getInstance();
    private List<MdmModel.MenuItem> menus = new ArrayList<>();
    private MdmModel.MdmResponse lastResponse;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityMdmBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        if (getSupportActionBar() != null) getSupportActionBar().setDisplayShowTitleEnabled(true);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        binding.group15.tvGroupTitle.setText(R.string.mdm_group_1_5);
        binding.group68.tvGroupTitle.setText(R.string.mdm_group_6_8);
        setupGroup(binding.group15, RICE_1_5, DAL_1_5);
        setupGroup(binding.group68, RICE_6_8, DAL_6_8);

        binding.btnDate.setOnClickListener(v -> showDatePicker());
        binding.btnToday.setOnClickListener(v -> {
            selected.setTimeInMillis(System.currentTimeMillis());
            load();
        });
        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
        binding.fabSave.setOnClickListener(v -> save());

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
            printRegister();
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    private void setupGroup(ViewMdmGroupBinding g, double ricePer, double dalPer) {
        g.swHoliday.setOnCheckedChangeListener((btn, checked) -> {
            g.layoutFields.setVisibility(checked ? View.GONE : View.VISIBLE);
            g.layoutFields.setAlpha(checked ? 0.4f : 1f);
            recalc();
        });
        g.etBeneficiaries.addTextChangedListener(new SimpleWatcher(() -> {
            int b = parseInt(g.etBeneficiaries.getText() == null ? "" : g.etBeneficiaries.getText().toString());
            g.tvRice.setText(String.format(Locale.US, "🍚 तांदूळ: %.2f किलो", b * ricePer));
            g.tvDal.setText(String.format(Locale.US, "🥣 डाळ: %.2f किलो", b * dalPer));
            recalc();
        }));
    }

    private void recalc() {
        int b1 = binding.group15.swHoliday.isChecked() ? 0 : parseInt(text(binding.group15.etBeneficiaries));
        int b2 = binding.group68.swHoliday.isChecked() ? 0 : parseInt(text(binding.group68.etBeneficiaries));
        binding.tvTotalBeneficiaries.setText(String.valueOf(b1 + b2));
        binding.tvTotalRice.setText(String.format(Locale.US, "%.2f", b1 * RICE_1_5 + b2 * RICE_6_8));
        binding.tvTotalDal.setText(String.format(Locale.US, "%.2f", b1 * DAL_1_5 + b2 * DAL_6_8));
    }

    private void showDatePicker() {
        MaterialDatePicker<Long> picker = MaterialDatePicker.Builder.datePicker()
                .setTitleText(R.string.date)
                .setSelection(selected.getTimeInMillis())
                .build();
        picker.addOnPositiveButtonClickListener(millis -> {
            Calendar utc = Calendar.getInstance(TimeZone.getTimeZone("UTC"));
            utc.setTimeInMillis(millis);
            selected.set(utc.get(Calendar.YEAR), utc.get(Calendar.MONTH), utc.get(Calendar.DAY_OF_MONTH));
            load();
        });
        picker.show(getSupportFragmentManager(), "mdm_date");
    }

    private void load() {
        binding.btnDate.setText(UiUtils.displayDate(selected));
        binding.tvDateLong.setText(UiUtils.longDate(selected));
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getMdm(UiUtils.apiDate(selected))
                .enqueue(new Callback<MdmModel.MdmResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<MdmModel.MdmResponse> call,
                                           @NonNull Response<MdmModel.MdmResponse> response) {
                        binding.swipeRefresh.setRefreshing(false);
                        if (response.isSuccessful() && response.body() != null && response.body().success) {
                            lastResponse = response.body();
                            bind(lastResponse);
                        } else {
                            UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<MdmModel.MdmResponse> call, @NonNull Throwable t) {
                        binding.swipeRefresh.setRefreshing(false);
                        UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
                    }
                });
    }

    private void bind(MdmModel.MdmResponse data) {
        menus = data.menus != null ? data.menus : new ArrayList<>();
        String[] names = new String[menus.size()];
        for (int i = 0; i < menus.size(); i++) names[i] = menus.get(i).name;

        int weekdayIndex = Math.max(0, Math.min(names.length - 1, selected.get(Calendar.DAY_OF_WEEK) - 2));

        bindGroup(binding.group15, data.entries != null ? data.entries.get("1-5") : null, names, weekdayIndex);
        bindGroup(binding.group68, data.entries != null ? data.entries.get("6-8") : null, names, weekdayIndex);
        recalc();
    }

    private void bindGroup(ViewMdmGroupBinding g, MdmModel.MdmEntry e, String[] menuNames, int defaultMenu) {
        if (e == null) {
            g.tvGroupStatus.setText("नोंद नाही");
            g.tvGroupStatus.setTextColor(getColor(R.color.text_secondary));
            g.swHoliday.setChecked(false);
            g.etBeneficiaries.setText("");
            g.swCooked.setChecked(true);
            g.etRemarks.setText("");
            UiUtils.bindDropdown(g.ddMenu, menuNames, menuNames.length > 0 ? defaultMenu : -1);
            return;
        }
        g.tvGroupStatus.setText("✓ नोंद पूर्ण");
        g.tvGroupStatus.setTextColor(getColor(R.color.success));
        g.swHoliday.setChecked(e.holiday);
        g.etBeneficiaries.setText(e.beneficiaries > 0 ? String.valueOf(e.beneficiaries) : "");
        g.swCooked.setChecked(e.cooked);
        g.etRemarks.setText(UiUtils.safe(e.remarks));
        int idx = -1;
        for (int i = 0; i < menus.size(); i++) if (menus.get(i).id == e.menuId) idx = i;
        UiUtils.bindDropdown(g.ddMenu, menuNames, idx >= 0 ? idx : defaultMenu);
        if (idx < 0 && e.menuName != null && !e.menuName.isEmpty()) g.ddMenu.setText(e.menuName, false);
    }

    private MdmModel.MdmGroupSaveItem collect(ViewMdmGroupBinding g) {
        boolean holiday = g.swHoliday.isChecked();
        String menuName = g.ddMenu.getText().toString();
        int menuId = 0;
        for (MdmModel.MenuItem m : menus) if (m.name.equals(menuName)) menuId = m.id;
        return new MdmModel.MdmGroupSaveItem(
                holiday ? 0 : parseInt(text(g.etBeneficiaries)),
                menuId, menuName,
                !holiday && g.swCooked.isChecked(),
                holiday,
                text(g.etRemarks));
    }

    private void save() {
        if (!binding.group15.swHoliday.isChecked() && parseInt(text(binding.group15.etBeneficiaries)) == 0
                && !binding.group68.swHoliday.isChecked() && parseInt(text(binding.group68.etBeneficiaries)) == 0) {
            binding.group15.tilBeneficiaries.setError(getString(R.string.required_field));
            return;
        }
        binding.group15.tilBeneficiaries.setError(null);
        UiUtils.hideKeyboard(this);
        binding.fabSave.setEnabled(false);
        binding.fabSave.setText(R.string.saving);

        MdmModel.MdmSaveRequest req = new MdmModel.MdmSaveRequest(
                UiUtils.apiDate(selected), collect(binding.group15), collect(binding.group68));
        ApiClient.getInstance().getApiService().saveMdm(req).enqueue(new Callback<ApiResponse>() {
            @Override
            public void onResponse(@NonNull Call<ApiResponse> call, @NonNull Response<ApiResponse> response) {
                binding.fabSave.setEnabled(true);
                binding.fabSave.setText(R.string.save);
                if (response.isSuccessful() && response.body() != null && response.body().isSuccess()) {
                    UiUtils.snack(binding.getRoot(), response.body().getMessage() != null
                            ? response.body().getMessage() : getString(R.string.saved_success));
                    load();
                } else {
                    UiUtils.snack(binding.getRoot(), getString(R.string.error_generic));
                }
            }

            @Override
            public void onFailure(@NonNull Call<ApiResponse> call, @NonNull Throwable t) {
                binding.fabSave.setEnabled(true);
                binding.fabSave.setText(R.string.save);
                UiUtils.snack(binding.getRoot(), getString(R.string.network_error));
            }
        });
    }

    private void printRegister() {
        SessionManager session = SessionManager.getInstance(this);
        StringBuilder html = new StringBuilder("<html><head><meta charset='utf-8'><style>")
                .append(UiUtils.printCss("#558B2F")).append("</style></head><body>")
                .append(UiUtils.printHeader(session, "शालेय पोषण आहार दैनिक नोंदवही"))
                .append("<div class='meta'><span>दिनांक: ").append(UiUtils.displayDate(selected))
                .append("</span><span>").append(UiUtils.escapeHtml(UiUtils.longDate(selected))).append("</span></div>")
                .append("<table><thead><tr><th>वर्ग गट</th><th>लाभार्थी</th><th>मेनू</th><th>तांदूळ (किलो)</th><th>डाळ (किलो)</th><th>स्थिती</th><th>शेरा</th></tr></thead><tbody>");
        appendRow(html, "इ. १ ली – ५ वी", binding.group15, RICE_1_5, DAL_1_5);
        appendRow(html, "इ. ६ वी – ८ वी", binding.group68, RICE_6_8, DAL_6_8);
        html.append("<tr><th>एकूण</th><th>").append(binding.tvTotalBeneficiaries.getText())
                .append("</th><th></th><th>").append(binding.tvTotalRice.getText())
                .append("</th><th>").append(binding.tvTotalDal.getText()).append("</th><th></th><th></th></tr>")
                .append("</tbody></table>")
                .append("<div class='sig'><div>स्वयंपाकी / मदतनीस</div><div>मुख्याध्यापक</div></div>")
                .append("</body></html>");
        PdfPrintHelper.printHtml(this, html.toString(), "MDM_" + UiUtils.apiDate(selected));
    }

    private void appendRow(StringBuilder html, String label, ViewMdmGroupBinding g, double ricePer, double dalPer) {
        boolean holiday = g.swHoliday.isChecked();
        int b = holiday ? 0 : parseInt(text(g.etBeneficiaries));
        html.append("<tr><td>").append(label).append("</td><td class='center'>").append(b)
                .append("</td><td>").append(holiday ? "—" : UiUtils.escapeHtml(g.ddMenu.getText().toString()))
                .append("</td><td class='center'>").append(String.format(Locale.US, "%.2f", b * ricePer))
                .append("</td><td class='center'>").append(String.format(Locale.US, "%.2f", b * dalPer))
                .append("</td><td>").append(holiday ? "सुट्टी" : (g.swCooked.isChecked() ? "शिजवला" : "शिजवला नाही"))
                .append("</td><td>").append(UiUtils.escapeHtml(text(g.etRemarks))).append("</td></tr>");
    }

    private static String text(com.google.android.material.textfield.TextInputEditText et) {
        return et.getText() == null ? "" : et.getText().toString().trim();
    }

    private static int parseInt(String s) {
        try {
            return s.isEmpty() ? 0 : Integer.parseInt(s.trim());
        } catch (NumberFormatException e) {
            return 0;
        }
    }

    /** TextWatcher that only cares about afterTextChanged. */
    private static class SimpleWatcher implements android.text.TextWatcher {
        private final Runnable onChange;
        SimpleWatcher(Runnable onChange) { this.onChange = onChange; }
        @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) {}
        @Override public void onTextChanged(CharSequence s, int a, int b, int c) {}
        @Override public void afterTextChanged(android.text.Editable s) { onChange.run(); }
    }
}
