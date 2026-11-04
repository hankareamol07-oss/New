package com.techguruji.smartschoolhub.ui.tachan;

import android.content.Intent;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.widget.GridLayout;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.annotation.Nullable;
import androidx.core.content.ContextCompat;

import com.google.android.material.chip.ChipGroup;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;
import com.techguruji.smartschoolhub.utils.ReportPrinter;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/**
 * Mirrors modules/tachan/annual.php (वार्षिक नियोजन), timetable.php (माझे / वर्ग वेळापत्रक),
 * holidays.php (सुट्ट्या व दिनविशेष) and class_pref.php (टाचण सेटिंग्ज).
 */
public class TachanSetupActivity extends NativePageActivity {

    private String page;
    private int classId;
    private int month = -1;
    private String mode = "";
    private String medium = "";
    private String ym = "";
    private List<JsonObject> classes = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        page = strExtra(TachanActivity.EXTRA_PAGE, "settings");
        switch (page) {
            case "annual":
                setTitle("वार्षिक नियोजन", "महिनानिहाय शासकीय अभ्यासक्रम घटक");
                break;
            case "timetable":
                setTitle("वेळापत्रक", "माझे (शिक्षक) / वर्ग तासिका नियोजन");
                break;
            case "holidays":
                setTitle("सुट्ट्या व दिनविशेष", "शासकीय व स्थानिक सुट्ट्यांचे कॅलेंडर");
                break;
            default:
                setTitle("टाचण सेटिंग्ज", "माझा वर्ग, माध्यम व जोडवर्ग निवड");
        }
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.P p;
        switch (page) {
            case "annual":
                p = Native.P.of("annual");
                if (classId > 0) p.put("class_id", classId);
                if (month >= 0) p.put("month", month);
                break;
            case "timetable":
                p = Native.P.of("timetable").put("medium", medium);
                if (!mode.isEmpty()) p.put("mode", mode);
                if (classId > 0) p.put("class_id", classId);
                break;
            case "holidays":
                p = Native.P.of("holidays").put("ym", ym);
                break;
            default:
                p = Native.P.of("prefs_get");
        }
        Native.get(Native.TACHAN, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                switch (page) {
                    case "annual":
                        renderAnnual(d);
                        break;
                    case "timetable":
                        renderTimetable(d);
                        break;
                    case "holidays":
                        renderHolidays(d);
                        break;
                    default:
                        renderPrefs(d);
                }
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    // ───────────────────────── annual.php ─────────────────────────

    private void renderAnnual(JsonObject d) {
        clearContent();
        clearBottom();
        classes = J.list(d, "classes");
        classId = J.i(J.o(d, "class"), "id");
        month = J.i(d, "month");
        List<JsonObject> months = J.list(d, "months");
        List<String> ml = new ArrayList<>();
        ml.add("संपूर्ण वर्ष");
        int mSel = 0;
        for (JsonObject m : months) {
            ml.add(J.s(m, "name"));
            if (J.i(m, "month") == month) mSel = ml.size() - 1;
        }
        LinearLayout f = Form.cardBody(content, "निवड");
        f.addView(Form.dropdown(this, "वर्ग", classLabels(), indexOfClass(classId), i -> {
            classId = J.i(classes.get(i), "id");
            load();
        }));
        f.addView(Form.dropdown(this, "महिना", ml, mSel, i -> {
            month = i == 0 ? 0 : J.i(months.get(i - 1), "month");
            load();
        }));

        List<JsonObject> subjects = J.list(d, "subjects");
        boolean monthView = "month".equals(J.s(d, "view"));
        if (subjects.isEmpty()) {
            LinearLayout e = Form.cardBody(content, "नियोजन उपलब्ध नाही");
            e.addView(Form.body(this, "या वर्गासाठी वार्षिक नियोजन नाही. शासकीय नियोजन आयात करा किंवा विषय जोडा."));
        }
        Map<String, List<TextInputLayout>> weekInputs = new HashMap<>();
        for (JsonObject s : subjects) {
            LinearLayout b = Form.cardBody(content, J.s(s, "display", J.s(s, "subject")));
            for (JsonObject m : J.list(s, "months")) {
                JsonArray weeks = J.a(m, "weeks");
                if (monthView) {
                    List<TextInputLayout> ins = new ArrayList<>();
                    for (int w = 0; w < 4; w++) {
                        TextInputLayout t = Form.input(this, "आठवडा " + (w + 1), weeks.size() > w ? weeks.get(w).getAsString() : "", true, false);
                        ins.add(t);
                        b.addView(t);
                    }
                    weekInputs.put(J.s(s, "subject"), ins);
                } else {
                    String topics = J.s(m, "topics");
                    b.addView(Form.label(this, J.s(m, "name")));
                    b.addView(Form.body(this, topics.isEmpty() ? "—" : topics));
                }
            }
        }
        if (monthView) {
            bottomButton("आठवडी नियोजन जतन", true, v -> {
                JsonObject w = new JsonObject();
                for (Map.Entry<String, List<TextInputLayout>> en : weekInputs.entrySet()) {
                    JsonArray arr = new JsonArray();
                    for (TextInputLayout t : en.getValue()) arr.add(Form.val(t));
                    w.add(en.getKey(), arr);
                }
                showLoading(true);
                Native.post(Native.TACHAN, Native.P.of("annual_save_weeks").put("class_id", classId).put("month", month).put("w", w), saved(true));
            });
            bottomButton("+ विषय", false, v -> addSubject(J.strings(d, "suggested_subjects")));
        }
        bottomButton("शासकीय आयात", false, v -> confirm("शासकीय वार्षिक नियोजन आयात करायचे?", "सर्व वर्गांसाठी शासकीय अभ्यासक्रम घटक भरले जातील.", () -> {
            showLoading(true);
            Native.post(Native.TACHAN, Native.P.of("annual_import"), saved(true));
        }));
        bottomButton("प्रिंट", false, v -> ReportPrinter.print(this, J.o(d, "print"), "Annual-plan"));
    }

    private void addSubject(List<String> suggested) {
        LinearLayout box = Form.vertical(this);
        int p = Form.dp(this, 20);
        box.setPadding(p, 0, p, 0);
        TextInputLayout name = Form.input(this, "विषयाचे नाव", "", false, false);
        box.addView(name);
        if (!suggested.isEmpty()) {
            box.addView(Form.muted(this, "सुचवलेले विषय:"));
            box.addView(Form.choiceChips(this, suggested, -1, i -> Form.setVal(name, suggested.get(i))));
        }
        new MaterialAlertDialogBuilder(this).setTitle("विषय जोडा").setView(box)
                .setPositiveButton("जोडा", (dlg, w) -> {
                    showLoading(true);
                    Native.post(Native.TACHAN, Native.P.of("annual_add_subject").put("class_id", classId).put("month", month).put("subject", Form.val(name)), saved(true));
                }).setNegativeButton("रद्द", null).show();
    }

    // ───────────────────────── timetable.php ─────────────────────────

    private void renderTimetable(JsonObject d) {
        clearContent();
        clearBottom();
        mode = J.s(d, "mode", "teacher");
        medium = J.s(d, "medium", "marathi");
        boolean teacher = mode.equals("teacher");
        classes = J.list(d, "classes");
        if (!teacher) classId = J.i(J.o(d, "class"), "id");

        LinearLayout f = Form.cardBody(content, "निवड");
        f.addView(Form.choiceChips(this, Arrays.asList("माझे वेळापत्रक (शिक्षक)", "वर्ग वेळापत्रक"), teacher ? 0 : 1, i -> {
            mode = i == 0 ? "teacher" : "class";
            load();
        }));
        if (!teacher) f.addView(Form.dropdown(this, "वर्ग", classLabels(), indexOfClass(classId), i -> {
            classId = J.i(classes.get(i), "id");
            load();
        }));
        JsonObject meds = J.o(d, "mediums");
        List<String> mk = new ArrayList<>(meds.keySet());
        f.addView(Form.choiceChips(this, J.mapValues(meds), mk.indexOf(medium), i -> {
            medium = mk.get(i);
            load();
        }));
        if (J.b(d, "is_template")) f.addView(Form.badge(this, "हे शासकीय साचा वेळापत्रक आहे — जतन केल्यावर तुमचे होईल", "#D97706"));

        List<String> subjects = J.strings(d, "subjects");
        List<JsonObject> choices = J.list(d, "class_choices");
        List<String> choiceLabels = new ArrayList<>();
        List<String> choiceKeys = new ArrayList<>();
        for (JsonObject c : choices) {
            choiceLabels.add(J.s(c, "label"));
            choiceKeys.add(J.s(c, "key"));
        }
        Map<String, JsonObject> cellMap = new HashMap<>();
        for (JsonObject c : J.list(d, "cells")) cellMap.put(J.i(c, "day") + "-" + J.i(c, "period"), c);
        Map<String, TextInputLayout> subIn = new HashMap<>();
        Map<String, TextInputLayout> clsIn = new HashMap<>();

        for (JsonObject day : J.list(d, "days")) {
            int dn = J.i(day, "day");
            LinearLayout b = Form.cardBody(content, J.s(day, "name"));
            for (JsonObject per : J.list(day, "periods")) {
                int pn = J.i(per, "period");
                String key = dn + "-" + pn;
                JsonObject cell = cellMap.get(key);
                b.addView(Form.label(this, "तासिका " + pn + "  ·  " + J.s(per, "time")));
                String sub = cell == null ? "" : J.s(cell, "subject");
                TextInputLayout s = Form.dropdown(this, "विषय", withBlank(subjects, sub), Math.max(0, withBlank(subjects, sub).indexOf(sub)), null);
                Form.setDropdown(s, sub);
                subIn.put(key, s);
                b.addView(s);
                if (teacher) {
                    String ci = cell == null ? "" : J.s(cell, "class_info");
                    int sel = choiceKeys.indexOf(ci);
                    TextInputLayout c = Form.dropdown(this, "वर्ग", choiceLabels, sel, null);
                    clsIn.put(key, c);
                    b.addView(c);
                }
            }
        }

        bottomButton("जतन करा", true, v -> {
            JsonArray cells = new JsonArray();
            for (Map.Entry<String, TextInputLayout> en : subIn.entrySet()) {
                String[] dp = en.getKey().split("-");
                JsonObject c = new JsonObject();
                c.addProperty("day", Integer.parseInt(dp[0]));
                c.addProperty("period", Integer.parseInt(dp[1]));
                c.addProperty("subject", Form.val(en.getValue()));
                if (teacher) {
                    TextInputLayout ct = clsIn.get(en.getKey());
                    int idx = ct == null ? -1 : Form.dropdownIndex(ct, choiceLabels);
                    c.addProperty("class_info", idx >= 0 ? choiceKeys.get(idx) : "");
                }
                cells.add(c);
            }
            showLoading(true);
            Native.P p = teacher ? Native.P.of("timetable_teacher_save").put("medium", medium)
                    : Native.P.of("timetable_class_save").put("class_id", classId);
            Native.post(Native.TACHAN, p.put("cells", cells), saved(true));
        });
        if (teacher) {
            List<JsonObject> tpls = J.list(d, "templates");
            bottomButton("साचा लागू करा", false, v -> {
                List<String> names = new ArrayList<>();
                for (JsonObject t : tpls) names.add(J.s(t, "name"));
                new MaterialAlertDialogBuilder(this).setTitle("शिक्षक वेळापत्रक साचा").setItems(names.toArray(new String[0]), (dlg, w) -> {
                    showLoading(true);
                    Native.post(Native.TACHAN, Native.P.of("timetable_teacher_template").put("medium", medium).put("template_key", J.s(tpls.get(w), "key")), saved(true));
                }).show();
            });
        } else {
            bottomButton("शासकीय साचा", false, v -> confirm("शासकीय साचा लागू करायचा?", "या वर्गाचे सध्याचे वेळापत्रक बदलून शासकीय तयार साचा लागू होईल.", () -> {
                showLoading(true);
                Native.post(Native.TACHAN, Native.P.of("timetable_class_template").put("class_id", classId), saved(true));
            }));
            bottomButton("सर्व वर्ग आयात", false, v -> confirm("सर्व वर्गांचे साचे तयार करायचे?", "वेळापत्रक नसलेल्या सर्व वर्गांना शासकीय साचे लागू होतील.", () -> {
                showLoading(true);
                Native.post(Native.TACHAN, Native.P.of("timetable_class_import"), saved(true));
            }));
        }
        bottomButton("प्रिंट", false, v -> ReportPrinter.print(this, J.o(d, "print"), "Timetable"));
    }

    private static List<String> withBlank(List<String> subjects, String current) {
        List<String> l = new ArrayList<>();
        l.add("");
        l.addAll(subjects);
        if (!current.isEmpty() && !l.contains(current)) l.add(current);
        return l;
    }

    // ───────────────────────── holidays.php ─────────────────────────

    private void renderHolidays(JsonObject d) {
        clearContent();
        clearBottom();
        ym = J.s(d, "ym", ym);
        boolean manage = J.b(d, "can_manage");
        LinearLayout head = Form.cardBody(content, J.s(d, "month_name") + " " + J.i(d, "year"));
        LinearLayout nav = Form.horizontal(this);
        nav.addView(Form.weight(Form.tonal(this, "‹ मागील", v -> {
            ym = J.s(d, "prev");
            load();
        }), 1f));
        nav.addView(Form.weight(Form.tonal(this, "पुढील ›", v -> {
            ym = J.s(d, "next");
            load();
        }), 1f));
        head.addView(nav);

        Map<String, JsonObject> hol = new HashMap<>();
        for (JsonObject h : J.list(d, "holidays")) hol.put(J.s(h, "date"), h);
        Map<String, List<JsonObject>> ev = new HashMap<>();
        for (JsonObject e : J.list(d, "events")) {
            String k = J.s(e, "date").length() >= 10 ? J.s(e, "date").substring(0, 10) : J.s(e, "date");
            if (!ev.containsKey(k)) ev.put(k, new ArrayList<>());
            ev.get(k).add(e);
        }
        List<String> picked = new ArrayList<>();
        for (JsonObject h : J.list(d, "holidays")) if ("user".equals(J.s(h, "kind"))) picked.add(J.s(h, "date"));

        GridLayout grid = new GridLayout(this);
        grid.setColumnCount(7);
        grid.setLayoutParams(Form.lp(this, 8));
        for (String dn : J.strings(d, "day_names")) grid.addView(dayCell(dn, "#475569", true, null));
        int start = J.i(d, "start_dow");
        for (int i = 0; i < start; i++) grid.addView(dayCell("", null, false, null));
        int dim = J.i(d, "days_in_month");
        for (int day = 1; day <= dim; day++) {
            String ds = String.format(java.util.Locale.US, "%s-%02d", ym, day);
            JsonObject h = hol.get(ds);
            boolean isEv = ev.containsKey(ds);
            int dow = (start + day - 1) % 7;
            String hex = h != null ? ("user".equals(J.s(h, "kind")) ? "#D97706" : "#DC2626") : (dow == 0 ? "#FEE2E2" : (isEv ? "#E0F2FE" : null));
            TextView tv = dayCell(String.valueOf(day), hex, h != null, v -> toggle(picked, ds, (TextView) v, hol.containsKey(ds) && !"user".equals(J.s(hol.get(ds), "kind"))));
            grid.addView(tv);
        }
        head.addView(grid);
        head.addView(Form.muted(this, "लाल = शासकीय सुट्टी · केशरी = माझी सुट्टी · निळा = दिनविशेष. दिवसावर टॅप करून माझी सुट्टी निवडा/काढा."));
        head.addView(Form.button(this, "माझ्या सुट्ट्या जतन", true, v -> {
            showLoading(true);
            Native.post(Native.TACHAN, Native.P.of("holidays_save_cal").put("ym", ym).put("d", J.arr(picked)), saved(true));
        }));

        LinearLayout hl = Form.cardBody(content, "या महिन्यातील सुट्ट्या");
        if (hol.isEmpty()) hl.addView(Form.muted(this, "सुट्ट्या नाहीत"));
        for (JsonObject h : J.list(d, "holidays")) {
            LinearLayout row = Form.horizontal(this);
            row.addView(Form.weight(Form.body(this, J.s(h, "date") + "  " + J.s(h, "name")), 1f));
            row.addView(Form.badge(this, J.s(h, "kind"), "user".equals(J.s(h, "kind")) ? "#D97706" : "#DC2626"));
            if (manage) row.addView(Form.tonal(this, "हटवा", v -> confirm("सुट्टी वगळायची?", J.s(h, "name"), () ->
                    Native.post(Native.TACHAN, Native.P.of("holiday_delete").put("id", J.i(h, "id")), saved(true)))));
            hl.addView(row);
        }
        LinearLayout el = Form.cardBody(content, "दिनविशेष / उपक्रम");
        if (ev.isEmpty()) el.addView(Form.muted(this, "दिनविशेष नाहीत"));
        for (JsonObject e : J.list(d, "events")) {
            LinearLayout row = Form.horizontal(this);
            row.addView(Form.weight(Form.body(this, J.s(e, "date") + "  " + J.s(e, "title")), 1f));
            row.addView(Form.badge(this, J.s(e, "event_type", "उपक्रम"), "#0284C7"));
            if (manage) row.addView(Form.tonal(this, "हटवा", v -> confirm("दिनविशेष वगळायचा?", J.s(e, "title"), () ->
                    Native.post(Native.TACHAN, Native.P.of("event_delete").put("id", J.i(e, "id")), saved(true)))));
            el.addView(row);
        }

        if (manage) {
            bottomButton("+ शाळा सुट्टी", true, v -> addDated("शाळा सुट्टी जोडा", "सुट्टीचे नाव", "holiday_add", "name"));
            bottomButton("+ दिनविशेष", false, v -> addDated("दिनविशेष / उपक्रम जोडा", "शीर्षक", "event_add", "title"));
            bottomButton("शासकीय आयात", false, v -> confirm("शासकीय सुट्ट्या व दिनविशेष आयात करायचे?", "शासकीय सुट्ट्या आणि दिनविशेष/उपक्रम या शाळेत भरले जातील.", () -> {
                showLoading(true);
                Native.post(Native.TACHAN, Native.P.of("holidays_import"), new Native.Cb() {
                    @Override
                    public void ok(JsonObject data) {
                        snack(Native.msg(data, "आयात झाले."));
                        Native.post(Native.TACHAN, Native.P.of("upakram_import"), saved(true));
                    }

                    @Override
                    public void fail(String message) {
                        showLoading(false);
                        snack(message);
                    }
                });
            }));
        }
    }

    private TextView dayCell(String s, @Nullable String hex, boolean bold, @Nullable View.OnClickListener click) {
        TextView tv = Form.text(this, s, 13, bold, hex != null && (hex.equals("#DC2626") || hex.equals("#D97706") || hex.equals("#475569"))
                ? 0xFFFFFFFF : ContextCompat.getColor(this, R.color.text_primary));
        tv.setGravity(Gravity.CENTER);
        int p = Form.dp(this, 8);
        tv.setPadding(p, p, p, p);
        GridLayout.LayoutParams lp = new GridLayout.LayoutParams(GridLayout.spec(GridLayout.UNDEFINED, 1f), GridLayout.spec(GridLayout.UNDEFINED, 1f));
        lp.width = 0;
        lp.setMargins(2, 2, 2, 2);
        tv.setLayoutParams(lp);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setCornerRadius(Form.dp(this, 6));
        bg.setColor(hex != null ? Form.parse(hex, 0xFFF1F5F9) : 0xFFF8FAFC);
        tv.setBackground(bg);
        tv.setTag(hex);
        if (click != null) tv.setOnClickListener(click);
        return tv;
    }

    private void toggle(List<String> picked, String ds, TextView tv, boolean govt) {
        if (govt) {
            snack("शासकीय सुट्टी बदलता येत नाही.");
            return;
        }
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setCornerRadius(Form.dp(this, 6));
        if (picked.remove(ds)) {
            bg.setColor(0xFFF8FAFC);
            tv.setTextColor(ContextCompat.getColor(this, R.color.text_primary));
        } else {
            picked.add(ds);
            bg.setColor(Form.parse("#D97706", 0));
            tv.setTextColor(0xFFFFFFFF);
        }
        tv.setBackground(bg);
    }

    private void addDated(String title, String hint, String action, String field) {
        LinearLayout box = Form.vertical(this);
        int p = Form.dp(this, 20);
        box.setPadding(p, 0, p, 0);
        TextInputLayout date = Form.input(this, "दिनांक (YYYY-MM-DD)", ym + "-01", false, false);
        TextInputLayout name = Form.input(this, hint, "", false, false);
        box.addView(date);
        box.addView(name);
        new MaterialAlertDialogBuilder(this).setTitle(title).setView(box)
                .setPositiveButton("जोडा", (dlg, w) -> {
                    showLoading(true);
                    Native.post(Native.TACHAN, Native.P.of(action).put("date", Form.val(date)).put(field, Form.val(name)), saved(true));
                }).setNegativeButton("रद्द", null).show();
    }

    // ───────────────────────── class_pref.php ─────────────────────────

    private void renderPrefs(JsonObject d) {
        clearContent();
        clearBottom();
        classes = J.list(d, "classes");
        JsonObject pr = J.o(d, "prefs");
        List<JsonObject> types = J.list(d, "types");
        List<String> typeKeys = new ArrayList<>();
        List<String> typeLabels = new ArrayList<>();
        for (JsonObject t : types) {
            typeKeys.add(J.s(t, "key"));
            typeLabels.add(J.s(t, "title"));
        }
        String[] tt = {J.s(pr, "teach_type", "single")};

        LinearLayout b = Form.cardBody(content, "मी कसे शिकवतो?");
        TextView desc = Form.muted(this, "");
        ChipGroup tg = Form.choiceChips(this, typeLabels, Math.max(0, typeKeys.indexOf(tt[0])), i -> {
            tt[0] = typeKeys.get(i);
            desc.setText(J.s(types.get(i), "desc"));
        });
        int ti = Math.max(0, typeKeys.indexOf(tt[0]));
        if (!types.isEmpty()) desc.setText(J.s(types.get(ti), "desc"));
        b.addView(tg);
        b.addView(desc);

        LinearLayout c = Form.cardBody(content, "वर्ग व माध्यम");
        List<String> cl = classLabels();
        TextInputLayout cls = Form.dropdown(this, "माझा वर्ग", cl, indexOfClass(J.i(pr, "class_id")), null);
        c.addView(cls);
        List<String> jl = new ArrayList<>();
        jl.add("— नाही —");
        jl.addAll(cl);
        int ji = J.i(pr, "jod_class_id") > 0 ? indexOfClass(J.i(pr, "jod_class_id")) + 1 : 0;
        TextInputLayout jod = Form.dropdown(this, "जोडवर्ग (जोडवर्ग प्रकारासाठी)", jl, ji, null);
        c.addView(jod);
        JsonObject meds = J.o(d, "mediums");
        List<String> mk = new ArrayList<>(meds.keySet());
        String[] med = {J.s(pr, "medium", "marathi")};
        c.addView(Form.label(this, "माध्यम"));
        c.addView(Form.choiceChips(this, J.mapValues(meds), Math.max(0, mk.indexOf(med[0])), i -> med[0] = mk.get(i)));

        LinearLayout l = Form.cardBody(content, "दिवसाची लांबी");
        JsonObject lens = J.o(d, "lengths");
        List<String> lk = new ArrayList<>(lens.keySet());
        String[] dl = {J.s(pr, "day_length", "full")};
        l.addView(Form.choiceChips(this, J.mapValues(lens), Math.max(0, lk.indexOf(dl[0])), i -> dl[0] = lk.get(i)));
        List<String> pl = new ArrayList<>();
        for (JsonObject p : J.list(d, "periods")) pl.add("तासिका " + J.i(p, "period") + "  " + J.s(p, "time"));
        int upto = J.i(pr, "upto_period", 8);
        TextInputLayout up = Form.dropdown(this, "'…पर्यंत' निवडल्यास तासिका", pl, Math.max(0, Math.min(pl.size() - 1, upto - 1)), null);
        l.addView(up);

        LinearLayout a = Form.cardBody(content, null);
        boolean[] auto = {true};
        a.addView(Form.choiceChips(this, Arrays.asList("वेळापत्रक आपोआप तयार करा", "फक्त प्राधान्ये जतन करा"), 0, i -> auto[0] = i == 0));
        a.addView(Form.muted(this, "जोडवर्ग / तासवार प्रकारासाठी शासकीय साचा वेळापत्रक आपोआप लागू होते."));

        bottomButton("जतन करा", true, v -> {
            int ci = Form.dropdownIndex(cls, cl);
            int jidx = Form.dropdownIndex(jod, jl);
            Native.P p = Native.P.of("prefs_save").put("teach_type", tt[0]).put("medium", med[0]).put("day_length", dl[0])
                    .put("upto_period", Form.dropdownIndex(up, pl) + 1).put("auto_tt", auto[0]);
            if (ci >= 0) p.put("class_id", J.i(classes.get(ci), "id"));
            if (jidx > 0) p.put("jod_class_id", J.i(classes.get(jidx - 1), "id"));
            showLoading(true);
            Native.post(Native.TACHAN, p, new Native.Cb() {
                @Override
                public void ok(JsonObject data) {
                    showLoading(false);
                    snack(Native.msg(data, "जतन झाले."));
                    String next = J.s(data, "next", "day");
                    Intent i = next.equals("timetable")
                            ? new Intent(TachanSetupActivity.this, TachanSetupActivity.class).putExtra(TachanActivity.EXTRA_PAGE, "timetable")
                            : new Intent(TachanSetupActivity.this, TachanPlannerActivity.class).putExtra(TachanActivity.EXTRA_PAGE, "day");
                    startActivity(i);
                    finish();
                }

                @Override
                public void fail(String message) {
                    showLoading(false);
                    snack(message);
                }
            });
        });
    }

    // ───────────────────────── helpers ─────────────────────────

    private List<String> classLabels() {
        List<String> l = new ArrayList<>();
        for (JsonObject c : classes) l.add(J.s(c, "name") + " (" + J.s(c, "label") + ")");
        return l;
    }

    private int indexOfClass(int id) {
        for (int i = 0; i < classes.size(); i++) if (J.i(classes.get(i), "id") == id) return i;
        return -1;
    }
}
