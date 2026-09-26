package com.techguruji.smartschoolhub.ui.tachan;

import android.app.DatePickerDialog;
import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;

/** Mirrors modules/tachan/add.php and edit.php — a single manual टाचण नोंद. */
public class TachanPlanEditActivity extends NativePageActivity {

    public static final String EXTRA_ID = "id";
    public static final String EXTRA_DATE = "date";
    public static final String EXTRA_PERIOD = "period";
    public static final String EXTRA_CLASS_ID = "class_id";
    public static final String EXTRA_SUBJECT = "subject";

    private static final String[] FIELDS = {"subject", "topic", "objectives", "activities", "eval_tool", "materials", "learning_outcome", "homework", "notes"};
    private static final String[] LABELS = {"विषय", "पाठ / घटक (topic)", "उद्दिष्टे", "अध्ययन-अध्यापन कृती", "मूल्यमापन साधन", "शैक्षणिक साधने", "अध्ययन निष्पत्ती", "गृहपाठ / स्वाध्याय", "शेरा / टीप"};

    private int id;
    private String date;
    private TextInputLayout dateIn;
    private TextInputLayout periodIn;
    private TextInputLayout classIn;
    private final List<TextInputLayout> inputs = new ArrayList<>();
    private List<JsonObject> classes = new ArrayList<>();
    private List<String> classLabels = new ArrayList<>();
    private List<String> periodLabels = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        id = intExtra(EXTRA_ID, 0);
        date = strExtra(EXTRA_DATE, today());
        setTitle(id > 0 ? "टाचण नोंद संपादन" : "नवीन टाचण नोंद", null);
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.P p = Native.P.of("plan_get").put("id", id);
        if (id == 0) p = Native.P.of("prefs_get");
        Native.get(Native.TACHAN, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                render(d);
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    private void render(JsonObject d) {
        clearContent();
        clearBottom();
        inputs.clear();
        classes = J.list(d, "classes");
        classLabels = new ArrayList<>();
        for (JsonObject c : classes) classLabels.add(J.s(c, "name") + " (" + J.s(c, "label") + ")");
        periodLabels = new ArrayList<>();
        for (JsonObject p : J.list(d, "periods")) periodLabels.add("तासिका " + J.i(p, "period") + "  " + J.s(p, "time"));

        JsonObject plan = id > 0 ? J.o(d, "plan") : new JsonObject();
        if (id > 0) date = J.s(plan, "date", date);
        int period = id > 0 ? J.i(plan, "period", 1) : intExtra(EXTRA_PERIOD, 1);
        int classId = id > 0 ? J.i(plan, "class_id") : intExtra(EXTRA_CLASS_ID, J.i(J.o(d, "prefs"), "class_id"));
        int cIdx = 0;
        for (int i = 0; i < classes.size(); i++) if (J.i(classes.get(i), "id") == classId) cIdx = i;

        LinearLayout b = Form.cardBody(content, "नोंदीची माहिती");
        dateIn = Form.input(this, "दिनांक (YYYY-MM-DD)", date, false, false);
        dateIn.getEditText().setFocusable(false);
        dateIn.getEditText().setOnClickListener(v -> pickDate());
        b.addView(dateIn);
        periodIn = Form.dropdown(this, "तासिका", periodLabels, Math.max(0, Math.min(periodLabels.size() - 1, period - 1)), null);
        b.addView(periodIn);
        classIn = Form.dropdown(this, "वर्ग", classLabels, cIdx, null);
        b.addView(classIn);

        LinearLayout f = Form.cardBody(content, "पाठ नियोजन");
        for (int i = 0; i < FIELDS.length; i++) {
            String v = id > 0 ? J.s(plan, FIELDS[i]) : (i == 0 ? strExtra(EXTRA_SUBJECT, "") : "");
            TextInputLayout t = Form.input(this, LABELS[i], v, i > 0, false);
            inputs.add(t);
            f.addView(t);
        }
        if (id > 0 && !J.s(plan, "src").isEmpty()) f.addView(Form.muted(this, "स्रोत: " + J.s(plan, "src")));

        bottomButton("जतन करा", true, v -> save());
        if (id > 0) bottomButton("हटवा", false, v -> confirm("नोंद हटवायची?", "ही टाचण नोंद कायमची हटवली जाईल.", () ->
                Native.post(Native.TACHAN, Native.P.of("plan_delete").put("id", id), new Native.Cb() {
                    @Override
                    public void ok(JsonObject data) {
                        snack(Native.msg(data, "हटवली."));
                        setResult(RESULT_OK);
                        finish();
                    }

                    @Override
                    public void fail(String message) {
                        snack(message);
                    }
                })));
    }

    private void save() {
        int pIdx = Form.dropdownIndex(periodIn, periodLabels);
        int cIdx = Form.dropdownIndex(classIn, classLabels);
        if (cIdx < 0 && !classes.isEmpty()) cIdx = 0;
        Native.P p = Native.P.of("plan_save").put("id", id).put("date", Form.val(dateIn)).put("period", pIdx + 1);
        if (cIdx >= 0) p.put("class_id", J.i(classes.get(cIdx), "id")).put("class_name", J.s(classes.get(cIdx), "name"));
        for (int i = 0; i < FIELDS.length; i++) p.put(FIELDS[i], Form.val(inputs.get(i)));
        showLoading(true);
        Native.post(Native.TACHAN, p, new Native.Cb() {
            @Override
            public void ok(JsonObject data) {
                showLoading(false);
                snack(Native.msg(data, "जतन झाले."));
                setResult(RESULT_OK);
                finish();
            }

            @Override
            public void fail(String message) {
                showLoading(false);
                snack(message);
            }
        });
    }

    private void pickDate() {
        Calendar c = Calendar.getInstance();
        try {
            String[] parts = Form.val(dateIn).split("-");
            c.set(Integer.parseInt(parts[0]), Integer.parseInt(parts[1]) - 1, Integer.parseInt(parts[2]));
        } catch (RuntimeException ignored) {
        }
        new DatePickerDialog(this, (view, y, m, day) ->
                Form.setVal(dateIn, String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, day)),
                c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH)).show();
    }

    static String today() {
        Calendar c = Calendar.getInstance();
        return String.format(Locale.US, "%04d-%02d-%02d", c.get(Calendar.YEAR), c.get(Calendar.MONTH) + 1, c.get(Calendar.DAY_OF_MONTH));
    }
}
