package com.techguruji.smartschoolhub.ui.cce;

import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.Nullable;

import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * CCE "गुण नोंदणी" pages: marks_entry.php (८ आकारिक + ३ संकलित per student, live total/%/grade),
 * coscholastic.php (A+…D per area), extra.php (उंची/वजन, जात, धर्म, SARAL, PEN, निकाल).
 */
public class CceMarksActivity extends NativePageActivity {

    private static final String[] FE = {"fe1", "fe2", "fe3", "fe4", "fe5", "fe6", "fe7", "fe8"};
    private static final String[] SE = {"se1", "se2", "se3"};

    private String page;
    private int std, semester, subjectId;
    private final List<Integer> stds = new ArrayList<>();
    private final List<String> stdLabels = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        page = strExtra(CceHubActivity.EXTRA_PAGE, "marks_entry");
        std = CceHubActivity.std(this);
        semester = CceHubActivity.semester(this);
        for (int i = 1; i <= 8; i++) {
            stds.add(i);
            stdLabels.add(CceHubActivity.stdLabel(this, i));
        }
        switch (page) {
            case "coscholastic":
                setTitle("सहशालेय मूल्यमापन", "शा.शि., कार्यानुभव, कला, अभिवृत्ती");
                break;
            case "extra":
                setTitle("अतिरिक्त माहिती", "उंची/वजन, जात, धर्म, SARAL, PEN, निकाल");
                break;
            default:
                setTitle("गुण नोंदणी", "आकारिक / संकलित साधननिहाय गुण");
        }
    }

    @Override
    protected void load() {
        showLoading(true);
        clearBottom();
        String action = page.equals("coscholastic") ? "coscholastic_get" : page.equals("extra") ? "extra_get" : "marks_get";
        Native.P p = Native.P.of(action).put("std", std).put("semester", semester);
        if (subjectId > 0) p.put("subject_id", subjectId);
        Native.get(Native.CCE, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                clearContent();
                LinearLayout hb = header(!page.equals("extra"));
                if (page.equals("coscholastic")) renderCoscholastic(d);
                else if (page.equals("extra")) renderExtra(d);
                else renderMarks(d, hb);
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    private LinearLayout header(boolean withSemester) {
        LinearLayout b = Form.cardBody(content, null);
        b.addView(Form.dropdown(this, "इयत्ता", stdLabels, stds.indexOf(std), i -> {
            std = stds.get(i);
            subjectId = 0;
            CceHubActivity.setStd(this, std);
            load();
        }));
        if (withSemester) {
            b.addView(Form.choiceChips(this, Arrays.asList("प्रथम सत्र", "द्वितीय सत्र"), semester - 1, i -> {
                semester = i + 1;
                CceHubActivity.setSemester(this, semester);
                load();
            }));
        }
        return b;
    }

    // ───────────────────────── marks_entry.php ─────────────────────────
    private void renderMarks(JsonObject d, LinearLayout sb) {
        List<JsonObject> subjects = J.list(d, "subjects");
        List<JsonObject> students = J.list(d, "students");
        JsonObject marks = J.o(d, "marks");
        subjectId = J.i(d, "subject_id");
        if (subjects.isEmpty()) {
            empty("आधी 'विषय व्यवस्थापन' मधून विषय तयार करा.");
            return;
        }
        List<String> names = new ArrayList<>();
        int sel = 0;
        JsonObject subject = subjects.get(0);
        for (int i = 0; i < subjects.size(); i++) {
            names.add(J.s(subjects.get(i), "name_mr"));
            if (J.i(subjects.get(i), "id") == subjectId) {
                sel = i;
                subject = subjects.get(i);
            }
        }
        sb.addView(Form.dropdown(this, "विषय", names, sel, i -> {
            subjectId = J.i(subjects.get(i), "id");
            load();
        }));
        JsonObject labels = J.o(d, "technique_labels");
        List<String> feL = J.strings(labels, "fe");
        List<String> seL = J.strings(labels, "se");
        if (feL.isEmpty()) feL = J.mapValues(J.o(labels, "fe"));
        if (seL.isEmpty()) seL = J.mapValues(J.o(labels, "se"));
        int[] feMax = new int[8];
        int[] seMax = new int[3];
        for (int i = 0; i < 8; i++) feMax[i] = J.i(subject, "fe" + (i + 1) + "_max");
        for (int i = 0; i < 3; i++) seMax[i] = J.i(subject, "se" + (i + 1) + "_max");
        final int feTot = J.i(subject, "fe_max"), seTot = J.i(subject, "se_max");
        sb.addView(Form.muted(this, "आकारिक कमाल " + feTot + " · संकलित कमाल " + seTot + " · एकूण " + (feTot + seTot)));
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        Map<Integer, TextInputLayout[]> fields = new HashMap<>();
        for (JsonObject s : students) {
            int sid = J.i(s, "id");
            JsonObject m = J.o(marks, String.valueOf(sid));
            LinearLayout b = Form.cardBody(content, null);
            LinearLayout head = Form.horizontal(this);
            head.addView(Form.weight(Form.title(this, J.s(s, "roll_no") + ". " + J.s(s, "name_mr")), 1));
            TextView gradeBadge = Form.badge(this, J.s(m, "grade", "—"), "#1D4ED8");
            head.addView(gradeBadge);
            b.addView(head);
            TextView totals = Form.muted(this, "");
            TextInputLayout[] f = new TextInputLayout[11];
            b.addView(Form.label(this, "आकारिक"));
            HorizontalScrollView sv = new HorizontalScrollView(this);
            LinearLayout r = Form.horizontal(this);
            for (int i = 0; i < 8; i++) {
                if (feMax[i] <= 0) continue;
                String hint = (i < feL.size() ? feL.get(i) : "साधन " + (i + 1)) + " /" + feMax[i];
                f[i] = Form.numCell(this, hint, J.s(m, FE[i]), 118);
                r.addView(f[i]);
            }
            sv.addView(r);
            b.addView(sv);
            b.addView(Form.label(this, "संकलित"));
            LinearLayout r2 = Form.horizontal(this);
            for (int i = 0; i < 3; i++) {
                if (seMax[i] <= 0) continue;
                String hint = (i < seL.size() ? seL.get(i) : "साधन " + (i + 1)) + " /" + seMax[i];
                f[8 + i] = Form.weight(Form.input(this, hint, J.s(m, SE[i]), false, true), 1);
                r2.addView(f[8 + i]);
            }
            b.addView(r2);
            b.addView(totals);
            fields.put(sid, f);
            Runnable recompute = () -> {
                double fe = 0, se = 0;
                for (int i = 0; i < 8; i++) fe += num(f[i], feMax[i]);
                for (int i = 0; i < 3; i++) se += num(f[8 + i], seMax[i]);
                double total = fe + se;
                double pct = (feTot + seTot) > 0 ? total * 100.0 / (feTot + seTot) : 0;
                totals.setText("आकारिक " + J.fmt(fe) + "/" + feTot + " · संकलित " + J.fmt(se) + "/" + seTot + " · एकूण " + J.fmt(total) + " · " + J.fmt(pct) + "%");
                gradeBadge.setText(grade(pct));
            };
            for (TextInputLayout t : f) {
                if (t == null || t.getEditText() == null) continue;
                t.getEditText().addTextChangedListener(new TextWatcher() {
                    public void beforeTextChanged(CharSequence s1, int a, int b1, int c) {}

                    public void onTextChanged(CharSequence s1, int a, int b1, int c) {}

                    public void afterTextChanged(Editable e) {
                        recompute.run();
                    }
                });
            }
            recompute.run();
            b.addView(Form.tonal(this, "फक्त हा विद्यार्थी जतन करा", v -> {
                Native.P p = Native.P.of("marks_save").put("std", std).put("semester", semester).put("subject_id", subjectId).put("student_id", sid);
                for (int i = 0; i < 8; i++) if (f[i] != null) p.put(FE[i], Form.val(f[i]));
                for (int i = 0; i < 3; i++) if (f[8 + i] != null) p.put(SE[i], Form.val(f[8 + i]));
                showLoading(true);
                Native.post(Native.CCE, p, saved(false));
            }));
        }
        bottomButton("सर्व विद्यार्थ्यांचे गुण जतन करा", true, v -> {
            JsonObject all = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout[]> e : fields.entrySet()) {
                JsonObject o = new JsonObject();
                TextInputLayout[] f = e.getValue();
                for (int i = 0; i < 8; i++) o.addProperty(FE[i], f[i] == null ? "" : Form.val(f[i]));
                for (int i = 0; i < 3; i++) o.addProperty(SE[i], f[8 + i] == null ? "" : Form.val(f[8 + i]));
                all.add(String.valueOf(e.getKey()), o);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("marks_save").put("std", std).put("semester", semester).put("subject_id", subjectId).put("marks", all), saved(true));
        });
    }

    private static double num(TextInputLayout t, int max) {
        if (t == null) return 0;
        try {
            double v = Double.parseDouble(Form.val(t));
            if (max > 0 && v > max) {
                t.setError("कमाल " + max);
                return max;
            }
            t.setError(null);
            return Math.max(0, v);
        } catch (Exception e) {
            t.setError(null);
            return 0;
        }
    }

    /** Same thresholds as cce_grade() in includes/cce_functions.php. */
    public static String grade(double p) {
        if (p >= 91) return "अ-१";
        if (p >= 81) return "अ-२";
        if (p >= 71) return "ब-१";
        if (p >= 61) return "ब-२";
        if (p >= 51) return "क-१";
        if (p >= 41) return "क-२";
        if (p >= 33) return "ड";
        if (p >= 21) return "इ-१";
        return "इ-२";
    }

    // ───────────────────────── coscholastic.php ─────────────────────────
    private void renderCoscholastic(JsonObject d) {
        List<JsonObject> students = J.list(d, "students");
        List<JsonObject> areas = J.list(d, "areas");
        List<String> grades = J.strings(d, "valid_grades");
        JsonObject existing = J.o(d, "grades");
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        List<String> opts = new ArrayList<>();
        opts.add("—");
        opts.addAll(grades);
        Map<Integer, Map<String, TextInputLayout>> picks = new HashMap<>();
        for (JsonObject s : students) {
            int sid = J.i(s, "id");
            JsonObject cur = J.o(existing, String.valueOf(sid));
            LinearLayout b = Form.cardBody(content, J.s(s, "roll_no") + ". " + J.s(s, "name_mr"));
            Map<String, TextInputLayout> row = new HashMap<>();
            LinearLayout r = null;
            int i = 0;
            for (JsonObject a : areas) {
                if (i % 2 == 0) {
                    r = Form.horizontal(this);
                    b.addView(r);
                }
                String key = J.s(a, "key");
                int sel = Math.max(0, opts.indexOf(J.s(cur, key)));
                TextInputLayout dd = Form.weight(Form.dropdown(this, J.s(a, "label"), opts, sel, null), 1);
                row.put(key, dd);
                r.addView(dd);
                i++;
            }
            picks.put(sid, row);
        }
        bottomButton("सहशालेय गुण जतन करा", true, v -> {
            JsonObject cs = new JsonObject();
            for (Map.Entry<Integer, Map<String, TextInputLayout>> e : picks.entrySet()) {
                JsonObject o = new JsonObject();
                for (Map.Entry<String, TextInputLayout> a : e.getValue().entrySet()) {
                    String g = Form.val(a.getValue());
                    o.addProperty(a.getKey(), g.equals("—") ? "" : g);
                }
                cs.add(String.valueOf(e.getKey()), o);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("coscholastic_save").put("std", std).put("semester", semester).put("cs", cs), saved(true));
        });
    }

    // ───────────────────────── extra.php ─────────────────────────
    private void renderExtra(JsonObject d) {
        List<JsonObject> students = J.list(d, "students");
        JsonObject extra = J.o(d, "extra");
        List<String> results = new ArrayList<>();
        results.add("—");
        results.addAll(J.strings(d, "result_options"));
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        String[] keys = {"height_1", "weight_1", "height_2", "weight_2", "cast_cat", "religion", "saral_id", "pen", "remark", "next_std"};
        String[] hints = {"उंची सत्र १ (सेमी)", "वजन सत्र १ (किलो)", "उंची सत्र २ (सेमी)", "वजन सत्र २ (किलो)", "जात प्रवर्ग", "धर्म", "SARAL ID", "PEN", "शेरा", "पुढील इयत्ता"};
        Map<Integer, TextInputLayout[]> fields = new HashMap<>();
        Map<Integer, TextInputLayout> resultPick = new HashMap<>();
        for (JsonObject s : students) {
            int sid = J.i(s, "id");
            JsonObject cur = J.o(extra, String.valueOf(sid));
            LinearLayout b = Form.cardBody(content, J.s(s, "roll_no") + ". " + J.s(s, "name_mr") + "  (GR " + J.s(s, "gr_no", "-") + ")");
            TextInputLayout[] f = new TextInputLayout[keys.length];
            LinearLayout r = null;
            for (int i = 0; i < keys.length; i++) {
                boolean numeric = i < 4;
                if (i < 8) {
                    if (i % 2 == 0) {
                        r = Form.horizontal(this);
                        b.addView(r);
                    }
                    f[i] = Form.weight(Form.input(this, hints[i], J.s(cur, keys[i]), false, numeric), 1);
                    r.addView(f[i]);
                } else {
                    f[i] = Form.input(this, hints[i], J.s(cur, keys[i]), i == 8, false);
                    b.addView(f[i]);
                }
            }
            TextInputLayout rp = Form.dropdown(this, "निकाल", results, Math.max(0, results.indexOf(J.s(cur, "result_status"))), null);
            b.addView(rp);
            fields.put(sid, f);
            resultPick.put(sid, rp);
        }
        bottomButton("अतिरिक्त माहिती जतन करा", true, v -> {
            JsonObject ex = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout[]> e : fields.entrySet()) {
                JsonObject o = new JsonObject();
                for (int i = 0; i < keys.length; i++) o.addProperty(keys[i], Form.val(e.getValue()[i]));
                String rs = Form.val(resultPick.get(e.getKey()));
                o.addProperty("result_status", rs.equals("—") ? "" : rs);
                ex.add(String.valueOf(e.getKey()), o);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("extra_save").put("std", std).put("ex", ex), saved(true));
        });
    }
}
