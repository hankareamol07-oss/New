package com.techguruji.smartschoolhub.ui.cce;

import android.os.Bundle;
import android.view.View;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.checkbox.MaterialCheckBox;
import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * CCE "व्यवस्थापन" pages: class_scope.php, settings.php, attendance.php,
 * subjects.php, teachers.php, marks_config.php — one activity, page chosen by extra.
 */
public class CceManageActivity extends NativePageActivity {

    private String page;
    private int std;
    private TextInputLayout stdPicker;
    private final List<Integer> stds = new ArrayList<>();
    private final List<String> stdLabels = new ArrayList<>();
    private String division = "";

    @Override
    protected void onReady(@Nullable Bundle state) {
        page = strExtra(CceHubActivity.EXTRA_PAGE, "settings");
        std = CceHubActivity.std(this);
        String[] t = titleFor(page);
        setTitle(t[0], t[1]);
    }

    private static String[] titleFor(String p) {
        switch (p) {
            case "class_scope":
                return new String[]{"वर्ग/तुकडी निवडा", "CCE मध्ये कोणत्या तुकड्या दिसाव्यात"};
            case "attendance":
                return new String[]{"मासिक उपस्थिती", "कामाचे दिवस व उपस्थित दिवस"};
            case "subjects":
                return new String[]{"विषय व्यवस्थापन", "इयत्तानिहाय विषय व क्रम"};
            case "teachers":
                return new String[]{"विषय शिक्षक", "विषयनिहाय शिक्षक नेमणूक"};
            case "marks_config":
                return new String[]{"भारांश (कमाल गुण) सेटअप", "आकारिक ८ व संकलित ३ साधने"};
            default:
                return new String[]{"शैक्षणिक वर्ष सेटिंग्ज", "मुख्याध्यापक, निकाल दिनांक, कामाचे दिवस"};
        }
    }

    private boolean needsStd() {
        return !page.equals("settings") && !page.equals("class_scope");
    }

    @Override
    protected void load() {
        showLoading(true);
        clearBottom();
        String action;
        switch (page) {
            case "class_scope":
                action = "class_scope";
                break;
            case "attendance":
                action = "attendance_get";
                break;
            case "subjects":
                action = "subjects_get";
                break;
            case "teachers":
                action = "teachers_get";
                break;
            case "marks_config":
                action = "marks_config_get";
                break;
            default:
                action = "settings_get";
        }
        Native.P p = Native.P.of(action).put("std", std).put("division", division);
        Native.get(Native.CCE, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                clearContent();
                if (needsStd()) addStdPicker();
                switch (page) {
                    case "class_scope":
                        renderScope(d);
                        break;
                    case "attendance":
                        renderAttendance(d);
                        break;
                    case "subjects":
                        renderSubjects(d);
                        break;
                    case "teachers":
                        renderTeachers(d);
                        break;
                    case "marks_config":
                        renderMarksConfig(d);
                        break;
                    default:
                        renderSettings(d);
                }
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    private void addStdPicker() {
        if (stds.isEmpty()) {
            for (int i = 1; i <= 8; i++) {
                stds.add(i);
                stdLabels.add(CceHubActivity.stdLabel(this, i));
            }
        }
        LinearLayout body = Form.cardBody(content, null);
        stdPicker = Form.dropdown(this, "इयत्ता", stdLabels, stds.indexOf(std), i -> {
            std = stds.get(i);
            CceHubActivity.setStd(this, std);
            division = "";
            load();
        });
        body.addView(stdPicker);
    }

    // ───────────────────────── class_scope.php ─────────────────────────
    private void renderScope(JsonObject d) {
        LinearLayout body = Form.cardBody(content, "शाळेतील वर्ग व तुकड्या — " + J.s(d, "academic_year"));
        body.addView(Form.muted(this, "GR रजिस्टर मधील वर्ग / तुकड्या येथे दिसतात. CCE सर्व नोंदी या इयत्तांसाठी होतात."));
        List<String> heads = new ArrayList<>();
        heads.add("इयत्ता");
        heads.add("तुकडी");
        heads.add("माध्यम");
        heads.add("वर्गशिक्षक");
        heads.add("विद्यार्थी");
        List<List<String>> rows = new ArrayList<>();
        for (JsonObject c : J.list(d, "classes")) {
            List<String> r = new ArrayList<>();
            r.add(CceHubActivity.stdLabel(this, J.i(c, "std")));
            r.add(J.s(c, "section", "-"));
            r.add(J.s(c, "medium", "-"));
            r.add(J.s(c, "teacher", "-"));
            r.add(J.s(c, "students"));
            rows.add(r);
        }
        if (rows.isEmpty()) body.addView(Form.body(this, "एकही वर्ग सापडला नाही. आधी 'वर्ग' मॉड्यूलमधून वर्ग तयार करा."));
        else body.addView(Form.table(this, heads, rows));
    }

    // ───────────────────────── settings.php ─────────────────────────
    private void renderSettings(JsonObject d) {
        LinearLayout body = Form.cardBody(content, "शैक्षणिक वर्ष " + J.s(d, "academic_year"));
        TextInputLayout hm1 = Form.input(this, "प्रथम सत्र — मुख्याध्यापक नाव", J.s(d, "sem1_headmaster_name"), false, false);
        TextInputLayout hm2 = Form.input(this, "द्वितीय सत्र — मुख्याध्यापक नाव", J.s(d, "sem2_headmaster_name"), false, false);
        TextInputLayout res = Form.input(this, "निकाल दिनांक (YYYY-MM-DD)", J.s(d, "result_date"), false, false);
        TextInputLayout notice = Form.input(this, "पुढील वर्ष सूचना", J.s(d, "next_year_notice"), true, false);
        body.addView(hm1);
        body.addView(hm2);
        body.addView(res);
        body.addView(notice);

        LinearLayout wd = Form.cardBody(content, "महिन्यानुसार कामाचे दिवस");
        Map<Integer, TextInputLayout> days = new HashMap<>();
        LinearLayout row = null;
        int i = 0;
        for (JsonObject m : J.list(d, "months")) {
            if (i % 3 == 0) {
                row = Form.horizontal(this);
                wd.addView(row);
            }
            TextInputLayout f = Form.input(this, J.s(m, "label"), J.s(m, "working_days"), false, true);
            days.put(J.i(m, "month"), Form.weight(f, 1));
            row.addView(f);
            i++;
        }
        bottomButton("जतन करा", true, v -> {
            JsonObject working = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout> e : days.entrySet()) working.addProperty(String.valueOf(e.getKey()), Form.val(e.getValue()));
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("settings_save")
                    .put("sem1_headmaster_name", Form.val(hm1)).put("sem2_headmaster_name", Form.val(hm2))
                    .put("result_date", Form.val(res)).put("next_year_notice", Form.val(notice)).put("working", working), saved(true));
        });
    }

    // ───────────────────────── attendance.php ─────────────────────────
    private void renderAttendance(JsonObject d) {
        List<JsonObject> months = J.list(d, "months");
        List<JsonObject> students = J.list(d, "students");
        LinearLayout wd = Form.cardBody(content, "कामाचे दिवस (शाळा)");
        Map<Integer, TextInputLayout> working = new HashMap<>();
        LinearLayout row = null;
        int i = 0;
        for (JsonObject m : months) {
            if (i % 3 == 0) {
                row = Form.horizontal(this);
                wd.addView(row);
            }
            TextInputLayout f = Form.input(this, J.s(m, "label"), J.s(m, "working_days"), false, true);
            working.put(J.i(m, "month"), Form.weight(f, 1));
            row.addView(f);
            i++;
        }
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        LinearLayout body = Form.cardBody(content, "विद्यार्थीनिहाय उपस्थित दिवस (" + students.size() + ")");
        body.addView(Form.muted(this, "प्रत्येक महिन्याचे उपस्थित दिवस भरा. रिकामे ठेवलेले महिने बदलणार नाहीत."));
        Map<Integer, Map<Integer, TextInputLayout>> cells = new HashMap<>();
        for (JsonObject s : students) {
            int sid = J.i(s, "id");
            body.addView(Form.label(this, J.s(s, "roll_no") + ". " + J.s(s, "name_mr")));
            HorizontalScrollView sv = new HorizontalScrollView(this);
            LinearLayout r = Form.horizontal(this);
            JsonObject att = J.o(s, "attendance");
            Map<Integer, TextInputLayout> mrow = new HashMap<>();
            for (JsonObject m : months) {
                int mn = J.i(m, "month");
                JsonObject a = J.o(att, String.valueOf(mn));
                String v = a.size() == 0 ? "" : J.s(a, "days_present");
                TextInputLayout f = Form.numCell(this, J.s(m, "label"), v, 84);
                mrow.put(mn, f);
                r.addView(f);
            }
            cells.put(sid, mrow);
            sv.addView(r);
            body.addView(sv);
        }
        bottomButton("उपस्थिती जतन करा", true, v -> {
            JsonObject w = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout> e : working.entrySet()) w.addProperty(String.valueOf(e.getKey()), Form.val(e.getValue()));
            JsonObject p = new JsonObject();
            for (Map.Entry<Integer, Map<Integer, TextInputLayout>> e : cells.entrySet()) {
                JsonObject mo = new JsonObject();
                for (Map.Entry<Integer, TextInputLayout> c : e.getValue().entrySet()) mo.addProperty(String.valueOf(c.getKey()), Form.val(c.getValue()));
                p.add(String.valueOf(e.getKey()), mo);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("attendance_save").put("std", std).put("working", w).put("p", p), saved(true));
        });
    }

    // ───────────────────────── subjects.php ─────────────────────────
    private void renderSubjects(JsonObject d) {
        List<JsonObject> subjects = J.list(d, "subjects");
        if (subjects.isEmpty()) {
            LinearLayout b = Form.cardBody(content, "विषय नाहीत");
            b.addView(Form.body(this, "या इयत्तेसाठी अजून विषय तयार केलेले नाहीत. शासकीय डिफॉल्ट विषय एका क्लिकने तयार करा."));
            b.addView(Form.button(this, "डिफॉल्ट विषय तयार करा", true, v -> {
                showLoading(true);
                Native.post(Native.CCE, Native.P.of("subjects_seed").put("std", std), saved(true));
            }));
        }
        Map<Integer, TextInputLayout[]> fields = new HashMap<>();
        Map<Integer, MaterialCheckBox> active = new HashMap<>();
        for (JsonObject s : subjects) {
            int id = J.i(s, "id");
            LinearLayout b = Form.cardBody(content, null);
            LinearLayout head = Form.horizontal(this);
            head.addView(Form.weight(Form.title(this, J.s(s, "name_mr")), 1));
            head.addView(Form.badge(this, J.s(s, "subject_code", "—"), "#475569"));
            b.addView(head);
            TextInputLayout name = Form.input(this, "विषय नाव (मराठी)", J.s(s, "name_mr"), false, false);
            TextInputLayout shortName = Form.input(this, "संक्षिप्त नाव", J.s(s, "short_name"), false, false);
            b.addView(name);
            b.addView(shortName);
            LinearLayout r = Form.horizontal(this);
            TextInputLayout fe = Form.weight(Form.input(this, "आकारिक कमाल", J.s(s, "fe_max"), false, true), 1);
            TextInputLayout se = Form.weight(Form.input(this, "संकलित कमाल", J.s(s, "se_max"), false, true), 1);
            TextInputLayout order = Form.weight(Form.input(this, "क्रम", J.s(s, "sort_order"), false, true), 1);
            r.addView(fe);
            r.addView(se);
            r.addView(order);
            b.addView(r);
            LinearLayout foot = Form.horizontal(this);
            MaterialCheckBox cb = new MaterialCheckBox(this);
            cb.setText("सक्रिय");
            cb.setChecked(J.i(s, "is_active", 1) == 1);
            foot.addView(Form.weight(cb, 1));
            foot.addView(Form.tonal(this, "हटवा", v -> confirm("विषय हटवायचा?", J.s(s, "name_mr") + " — या विषयाचे सर्व गुण देखील प्रभावित होतील.", () -> {
                showLoading(true);
                Native.post(Native.CCE, Native.P.of("subject_delete").put("id", id), saved(true));
            })));
            b.addView(foot);
            fields.put(id, new TextInputLayout[]{name, shortName, fe, se, order});
            active.put(id, cb);
        }
        LinearLayout add = Form.cardBody(content, "नवीन विषय जोडा");
        TextInputLayout nName = Form.input(this, "विषय नाव (मराठी) *", "", false, false);
        TextInputLayout nCode = Form.input(this, "विषय कोड", "", false, false);
        TextInputLayout nShort = Form.input(this, "संक्षिप्त नाव", "", false, false);
        LinearLayout r2 = Form.horizontal(this);
        TextInputLayout nFe = Form.weight(Form.input(this, "आकारिक कमाल", "40", false, true), 1);
        TextInputLayout nSe = Form.weight(Form.input(this, "संकलित कमाल", "60", false, true), 1);
        TextInputLayout nOrder = Form.weight(Form.input(this, "क्रम", String.valueOf(subjects.size() + 1), false, true), 1);
        r2.addView(nFe);
        r2.addView(nSe);
        r2.addView(nOrder);
        add.addView(nName);
        add.addView(nCode);
        add.addView(nShort);
        add.addView(r2);
        add.addView(Form.button(this, "विषय जोडा", false, v -> {
            if (Form.val(nName).isEmpty()) {
                nName.setError("आवश्यक");
                return;
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("subject_add").put("std", std).put("name_mr", Form.val(nName)).put("subject_code", Form.val(nCode))
                    .put("short_name", Form.val(nShort)).put("fe_max", Form.val(nFe)).put("se_max", Form.val(nSe)).put("sort_order", Form.val(nOrder)), saved(true));
        }));
        if (!subjects.isEmpty()) {
            bottomButton("सर्व विषय जतन करा", true, v -> {
                JsonObject subj = new JsonObject();
                for (Map.Entry<Integer, TextInputLayout[]> e : fields.entrySet()) {
                    TextInputLayout[] f = e.getValue();
                    JsonObject o = new JsonObject();
                    o.addProperty("name_mr", Form.val(f[0]));
                    o.addProperty("short_name", Form.val(f[1]));
                    o.addProperty("fe_max", Form.val(f[2]));
                    o.addProperty("se_max", Form.val(f[3]));
                    o.addProperty("sort_order", Form.val(f[4]));
                    o.addProperty("is_active", active.get(e.getKey()).isChecked() ? 1 : 0);
                    subj.add(String.valueOf(e.getKey()), o);
                }
                showLoading(true);
                Native.post(Native.CCE, Native.P.of("subjects_update").put("std", std).put("subject", subj), saved(true));
            });
        }
    }

    // ───────────────────────── teachers.php ─────────────────────────
    private void renderTeachers(JsonObject d) {
        List<String> divs = J.strings(d, "divisions");
        if (!divs.isEmpty()) {
            List<String> labels = new ArrayList<>();
            for (String s : divs) labels.add(s.isEmpty() ? "(तुकडी नाही)" : "तुकडी " + s);
            int sel = Math.max(0, divs.indexOf(division));
            division = divs.get(sel);
            LinearLayout b = Form.cardBody(content, null);
            b.addView(Form.dropdown(this, "तुकडी", labels, sel, i -> {
                division = divs.get(i);
                load();
            }));
        }
        List<JsonObject> teachers = J.list(d, "teachers");
        List<String> tNames = new ArrayList<>();
        List<Integer> tIds = new ArrayList<>();
        tNames.add("— नेमणूक नाही —");
        tIds.add(0);
        for (JsonObject t : teachers) {
            tIds.add(J.i(t, "id"));
            tNames.add(J.s(t, "name") + (J.s(t, "teacher_code").isEmpty() ? "" : " (" + J.s(t, "teacher_code") + ")"));
        }
        JsonObject mapping = J.o(d, "mapping");
        List<JsonObject> subjects = J.list(d, "subjects");
        if (subjects.isEmpty()) {
            empty("आधी 'विषय व्यवस्थापन' मधून विषय तयार करा.");
            return;
        }
        LinearLayout body = Form.cardBody(content, "विषयनिहाय शिक्षक — " + J.s(d, "academic_year"));
        Map<Integer, TextInputLayout> picks = new HashMap<>();
        for (JsonObject s : subjects) {
            int sid = J.i(s, "id");
            int cur = J.i(mapping, String.valueOf(sid));
            picks.put(sid, Form.dropdown(this, J.s(s, "name_mr"), tNames, Math.max(0, tIds.indexOf(cur)), null));
            body.addView(picks.get(sid));
        }
        bottomButton("नेमणूक जतन करा", true, v -> {
            JsonObject teacher = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout> e : picks.entrySet()) {
                int idx = Form.dropdownIndex(e.getValue(), tNames);
                teacher.addProperty(String.valueOf(e.getKey()), idx < 0 ? 0 : tIds.get(idx));
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("teachers_save").put("std", std).put("division", division).put("teacher", teacher), saved(true));
        });
    }

    // ───────────────────────── marks_config.php ─────────────────────────
    private void renderMarksConfig(JsonObject d) {
        List<JsonObject> subjects = J.list(d, "subjects");
        JsonObject labels = J.o(d, "technique_labels");
        List<String> feLabels = J.strings(labels, "fe");
        List<String> seLabels = J.strings(labels, "se");
        if (feLabels.isEmpty()) feLabels = J.mapValues(J.o(labels, "fe"));
        if (seLabels.isEmpty()) seLabels = J.mapValues(J.o(labels, "se"));
        if (subjects.isEmpty()) {
            empty("आधी 'विषय व्यवस्थापन' मधून विषय तयार करा.");
            return;
        }
        Map<Integer, TextInputLayout[]> fields = new HashMap<>();
        for (JsonObject s : subjects) {
            int id = J.i(s, "id");
            LinearLayout b = Form.cardBody(content, J.s(s, "name_mr"));
            b.addView(Form.label(this, "आकारिक मूल्यमापन (८ साधने)"));
            TextInputLayout[] f = new TextInputLayout[11];
            HorizontalScrollView sv = new HorizontalScrollView(this);
            LinearLayout r = Form.horizontal(this);
            for (int i = 1; i <= 8; i++) {
                String hint = i + ". " + (i - 1 < feLabels.size() ? feLabels.get(i - 1) : "साधन " + i);
                f[i - 1] = Form.numCell(this, hint, J.s(s, "fe" + i + "_max"), 120);
                r.addView(f[i - 1]);
            }
            sv.addView(r);
            b.addView(sv);
            b.addView(Form.label(this, "संकलित मूल्यमापन (३ साधने)"));
            LinearLayout r2 = Form.horizontal(this);
            for (int i = 1; i <= 3; i++) {
                String hint = i + ". " + (i - 1 < seLabels.size() ? seLabels.get(i - 1) : "साधन " + i);
                f[7 + i] = Form.weight(Form.input(this, hint, J.s(s, "se" + i + "_max"), false, true), 1);
                r2.addView(f[7 + i]);
            }
            b.addView(r2);
            b.addView(Form.muted(this, "सध्या: आकारिक " + J.s(s, "fe_max") + " + संकलित " + J.s(s, "se_max") + " = " + (J.i(s, "fe_max") + J.i(s, "se_max"))));
            fields.put(id, f);
        }
        bottomButton("भारांश जतन करा", true, v -> {
            JsonObject subj = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout[]> e : fields.entrySet()) {
                JsonObject o = new JsonObject();
                TextInputLayout[] f = e.getValue();
                for (int i = 1; i <= 8; i++) o.addProperty("fe" + i, Form.val(f[i - 1]));
                for (int i = 1; i <= 3; i++) o.addProperty("se" + i, Form.val(f[7 + i]));
                subj.add(String.valueOf(e.getKey()), o);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("marks_config_save").put("std", std).put("subject", subj), saved(true));
        });
    }

    @SuppressWarnings("unused")
    private void hide(View v) {
        v.setVisibility(View.GONE);
    }
}
