package com.techguruji.smartschoolhub.ui.cce;

import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.chip.ChipGroup;
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
 * CCE "वर्णनात्मक नोंदी" pages: outcomes.php (अध्ययन निष्पत्ती स्तर १–४),
 * remarks_entry.php (१३ घटक + सूचना बँक, विद्यार्थी पुढे/मागे), bank.php (SCERT dataset).
 */
public class CceNotesActivity extends NativePageActivity {

    private String page;
    private int std, semester, studentId;
    private String sub = "", src = "sanch", q = "";
    private final List<Integer> stds = new ArrayList<>();
    private final List<String> stdLabels = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        page = strExtra(CceHubActivity.EXTRA_PAGE, "remarks_entry");
        std = CceHubActivity.std(this);
        semester = CceHubActivity.semester(this);
        for (int i = 1; i <= 8; i++) {
            stds.add(i);
            stdLabels.add(CceHubActivity.stdLabel(this, i));
        }
        switch (page) {
            case "outcomes":
                setTitle("अध्ययन निष्पत्ती", "स्तर १–४ नोंद");
                break;
            case "bank":
                setTitle("वर्णनात्मक नोंदी बँक", "SCERT डेटासेट");
                break;
            default:
                setTitle("वर्णनात्मक नोंदी", "१३ घटकांच्या नोंदी");
        }
    }

    @Override
    protected void load() {
        showLoading(true);
        clearBottom();
        Native.P p;
        if (page.equals("outcomes")) p = Native.P.of("outcomes_get").put("std", std).put("semester", semester).put("student_id", studentId).put("sub", sub);
        else if (page.equals("bank")) p = Native.P.of("bank").put("std", std).put("src", src).put("q", q);
        else p = Native.P.of("notes_get").put("std", std).put("semester", semester).put("student_id", studentId);
        Native.get(Native.CCE, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                clearContent();
                if (page.equals("outcomes")) renderOutcomes(d);
                else if (page.equals("bank")) renderBank(d);
                else renderNotes(d);
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
            studentId = 0;
            sub = "";
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

    /** Student dropdown + पुढील/मागील navigation; returns index of current student. */
    private int studentNav(LinearLayout b, List<JsonObject> students) {
        List<String> names = new ArrayList<>();
        int sel = 0;
        for (int i = 0; i < students.size(); i++) {
            names.add(J.s(students.get(i), "roll_no") + ". " + J.s(students.get(i), "name_mr"));
            if (J.i(students.get(i), "id") == studentId) sel = i;
        }
        b.addView(Form.dropdown(this, "विद्यार्थी", names, sel, i -> {
            studentId = J.i(students.get(i), "id");
            load();
        }));
        final int cur = sel;
        LinearLayout nav = Form.horizontal(this);
        nav.addView(Form.weight(Form.tonal(this, "‹ मागील", v -> {
            if (cur > 0) {
                studentId = J.i(students.get(cur - 1), "id");
                load();
            }
        }), 1));
        nav.addView(Form.weight(Form.tonal(this, "पुढील ›", v -> {
            if (cur < students.size() - 1) {
                studentId = J.i(students.get(cur + 1), "id");
                load();
            }
        }), 1));
        b.addView(nav);
        return sel;
    }

    // ───────────────────────── outcomes.php ─────────────────────────
    private void renderOutcomes(JsonObject d) {
        LinearLayout hb = header(true);
        List<JsonObject> students = J.list(d, "students");
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        studentId = J.i(d, "student_id");
        studentNav(hb, students);
        List<String> subs = J.strings(d, "subjects");
        if (!subs.isEmpty()) {
            List<String> opts = new ArrayList<>();
            opts.add("सर्व विषय");
            opts.addAll(subs);
            hb.addView(Form.dropdown(this, "विषय फिल्टर", opts, Math.max(0, opts.indexOf(sub)), i -> {
                sub = i == 0 ? "" : opts.get(i);
                load();
            }));
        }
        JsonObject levels = J.o(d, "levels");
        List<String> levelLabels = new ArrayList<>();
        levelLabels.add("—");
        for (int i = 1; i <= 4; i++) levelLabels.add(J.s(levels, String.valueOf(i), "स्तर " + i));
        JsonObject selected = J.o(d, "selected");
        List<JsonObject> outcomes = J.list(d, "outcomes");
        LinearLayout body = Form.cardBody(content, "अध्ययन निष्पत्ती (" + outcomes.size() + ")");
        body.addView(Form.muted(this, "स्तर निवडा. '—' ठेवल्यास ती निष्पत्ती या विद्यार्थ्यासाठी काढली जाते."));
        Map<Integer, ChipGroup> picks = new HashMap<>();
        List<Integer> visible = new ArrayList<>();
        for (JsonObject o : outcomes) {
            int oid = J.i(o, "id");
            visible.add(oid);
            LinearLayout item = Form.vertical(this);
            item.setLayoutParams(Form.lp(this, 10));
            item.addView(Form.body(this, (J.s(o, "sub").isEmpty() ? "" : "[" + J.s(o, "sub") + "] ") + J.s(o, "text")));
            int lvl = J.i(selected, String.valueOf(oid));
            ChipGroup g = Form.choiceChips(this, Arrays.asList("—", "१", "२", "३", "४"), lvl, null);
            item.addView(g);
            item.addView(Form.divider(this));
            body.addView(item);
            picks.put(oid, g);
        }
        LinearLayout legend = Form.cardBody(content, "स्तर");
        for (int i = 1; i <= 4; i++) legend.addView(Form.muted(this, levelLabels.get(i)));
        bottomButton("निष्पत्ती जतन करा", true, v -> {
            JsonObject level = new JsonObject();
            for (Map.Entry<Integer, ChipGroup> e : picks.entrySet()) {
                int idx = Form.checkedIndex(e.getValue());
                if (idx > 0) level.addProperty(String.valueOf(e.getKey()), idx);
            }
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("outcomes_save").put("std", std).put("semester", semester).put("student_id", studentId)
                    .put("visible_ids", J.arrInt(visible)).put("level", level), saved(false));
        });
    }

    // ───────────────────────── remarks_entry.php ─────────────────────────
    private void renderNotes(JsonObject d) {
        LinearLayout hb = header(true);
        List<JsonObject> students = J.list(d, "students");
        if (students.isEmpty()) {
            empty("या इयत्तेत विद्यार्थी नाहीत.");
            return;
        }
        studentId = J.i(d, "student_id");
        int cur = studentNav(hb, students);
        if (J.b(d, "is_girl")) hb.addView(Form.muted(this, "मुलगी — जतन करताना नोंदी स्त्रीलिंगी रूपात आपोआप बदलतील."));
        Map<Integer, TextInputLayout> fields = new HashMap<>();
        for (JsonObject a : J.list(d, "areas")) {
            int slot = J.i(a, "slot");
            LinearLayout b = Form.cardBody(content, slot + ". " + J.s(a, "label"));
            String saved = J.s(a, "saved");
            TextInputLayout f = Form.input(this, "नोंद", saved.isEmpty() ? J.s(a, "default") : saved, true, false);
            b.addView(f);
            fields.put(slot, f);
            List<String> sugs = J.strings(a, "suggestions");
            if (!sugs.isEmpty()) {
                b.addView(Form.label(this, "सूचना बँक (टॅप करून भरा)"));
                LinearLayout sl = Form.vertical(this);
                int shown = 0;
                for (String s : sugs) {
                    if (shown++ >= 6) break;
                    com.google.android.material.chip.Chip ch = new com.google.android.material.chip.Chip(this);
                    ch.setText(s.length() > 90 ? s.substring(0, 90) + "…" : s);
                    ch.setEnsureMinTouchTargetSize(false);
                    ch.setOnClickListener(v -> Form.setVal(f, s));
                    sl.addView(ch);
                }
                b.addView(sl);
            }
        }
        Runnable save = () -> {
            JsonObject note = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout> e : fields.entrySet()) note.addProperty(String.valueOf(e.getKey()), Form.val(e.getValue()));
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("notes_save").put("std", std).put("semester", semester).put("student_id", studentId).put("note", note), saved(false));
        };
        bottomButton("जतन", true, v -> save.run());
        bottomButton("जतन व पुढील ›", false, v -> {
            JsonObject note = new JsonObject();
            for (Map.Entry<Integer, TextInputLayout> e : fields.entrySet()) note.addProperty(String.valueOf(e.getKey()), Form.val(e.getValue()));
            showLoading(true);
            Native.post(Native.CCE, Native.P.of("notes_save").put("std", std).put("semester", semester).put("student_id", studentId).put("note", note), new Native.Cb() {
                @Override
                public void ok(JsonObject data) {
                    snack(Native.msg(data, "जतन झाले."));
                    if (cur < students.size() - 1) studentId = J.i(students.get(cur + 1), "id");
                    load();
                }

                @Override
                public void fail(String message) {
                    showLoading(false);
                    snack(message);
                }
            });
        });
    }

    // ───────────────────────── bank.php ─────────────────────────
    private void renderBank(JsonObject d) {
        LinearLayout hb = header(false);
        List<JsonObject> sources = J.list(d, "sources");
        List<String> labels = new ArrayList<>();
        int sel = 0;
        for (int i = 0; i < sources.size(); i++) {
            labels.add(J.s(sources.get(i), "label"));
            if (J.s(sources.get(i), "key").equals(J.s(d, "src"))) sel = i;
        }
        hb.addView(Form.dropdown(this, "बँक", labels, sel, i -> {
            src = J.s(sources.get(i), "key");
            load();
        }));
        TextInputLayout search = Form.input(this, "शोधा", q, false, false);
        hb.addView(search);
        hb.addView(Form.tonal(this, "शोधा", v -> {
            q = Form.val(search);
            load();
        }));
        List<JsonObject> rows = J.list(d, "rows");
        LinearLayout body = Form.cardBody(content, rows.size() + " नोंदी");
        if (rows.isEmpty()) body.addView(Form.body(this, "काही सापडले नाही."));
        for (JsonObject r : rows) {
            LinearLayout item = Form.vertical(this);
            item.setLayoutParams(Form.lp(this, 8));
            LinearLayout tags = Form.horizontal(this);
            if (!J.s(r, "std").isEmpty()) tags.addView(Form.badge(this, J.s(r, "std"), "#475569"));
            if (!J.s(r, "gat").isEmpty()) tags.addView(Form.badge(this, J.s(r, "gat"), "#0F766E"));
            if (!J.s(r, "kod").isEmpty()) tags.addView(Form.badge(this, J.s(r, "kod"), "#7C3AED"));
            item.addView(tags);
            String[][] lv = {{"nirzar1", "निर्झर"}, {"nirzar2", "निर्झर २"}, {"nirzar3", "निर्झर ३"}, {"parvat1", "पर्वत"}, {"parvat2", "पर्वत २"}, {"parvat3", "पर्वत ३"}, {"akash1", "आकाश"}, {"akash2", "आकाश २"}, {"akash3", "आकाश ३"}};
            boolean sanch = src.startsWith("sanch");
            for (String[] k : lv) {
                String v = J.s(r, k[0]);
                if (v.isEmpty()) continue;
                item.addView(Form.body(this, sanch ? k[1] + ": " + v : v));
            }
            item.addView(Form.divider(this));
            body.addView(item);
        }
    }
}
