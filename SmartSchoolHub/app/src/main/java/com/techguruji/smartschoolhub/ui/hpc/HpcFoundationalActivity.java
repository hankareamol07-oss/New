package com.techguruji.smartschoolhub.ui.hpc;

import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.chip.ChipGroup;
import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
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
 * पायाभूत स्तर (इ. १–२) wizard, mirroring step1_rubric.php → step2_abhipray.php → step3_kamgiri.php → create.php:
 * tabs: विद्यार्थी माहिती · रुब्रिक (क्षमता कोड) · अभिप्राय · कामगिरी · उपस्थिती · वार्षिक सारांश.
 */
public class HpcFoundationalActivity extends NativePageActivity {

    private int studentId, tab = 0, semester = 1, domainIdx = 0;
    private JsonObject data;

    @Override
    protected void onReady(@Nullable Bundle state) {
        studentId = intExtra("student_id", 0);
        setTitle(strExtra("name", "HPC"), "पायाभूत स्तर — इ. १–२");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.HPC, Native.P.of("foundational_get").put("student_id", studentId), new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                data = d;
                render();
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    private void render() {
        clearContent();
        clearBottom();
        JsonObject s = J.o(data, "student");
        JsonObject card = J.o(data, "card");
        setTitle(J.s(s, "name_mr"), "इयत्ता " + J.s(s, "std") + " · हजेरी क्र. " + J.s(s, "roll_no") + " · " + (J.s(card, "status").equals("completed") ? "पूर्ण" : "मसुदा"));
        JsonObject prog = J.o(data, "progress");
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.stat(this, J.s(prog, "1", "0") + "%", "सत्र १ प्रगती", "#0284C7"));
        row.addView(Form.stat(this, J.s(prog, "2", "0") + "%", "सत्र २ प्रगती", "#059669"));
        content.addView(row);
        content.addView(Form.choiceChips(this, Arrays.asList("माहिती", "१ रुब्रिक", "२ अभिप्राय", "३ कामगिरी", "उपस्थिती", "सारांश"), tab, i -> {
            tab = i;
            render();
        }));
        switch (tab) {
            case 1:
                renderRubric();
                break;
            case 2:
                renderAbhipray();
                break;
            case 3:
                renderKamgiri();
                break;
            case 4:
                renderAttendance();
                break;
            case 5:
                renderSummary();
                break;
            default:
                renderInfo();
        }
    }

    private void domainPicker(LinearLayout b, boolean withSem) {
        List<JsonObject> doms = J.list(data, "domains");
        List<String> names = new ArrayList<>();
        for (JsonObject d : doms) names.add(J.s(d, "id") + ". " + J.s(d, "name_mr"));
        b.addView(Form.dropdown(this, "विकास क्षेत्र", names, domainIdx, i -> {
            domainIdx = i;
            render();
        }));
        if (withSem) b.addView(Form.choiceChips(this, Arrays.asList("सत्र १", "सत्र २"), semester - 1, i -> {
            semester = i + 1;
            render();
        }));
    }

    private JsonObject domain() {
        List<JsonObject> doms = J.list(data, "domains");
        if (domainIdx >= doms.size()) domainIdx = 0;
        return doms.isEmpty() ? new JsonObject() : doms.get(domainIdx);
    }

    private JsonObject sem(JsonObject dom) {
        for (JsonObject s : J.list(dom, "semesters")) if (J.i(s, "semester") == semester) return s;
        return new JsonObject();
    }

    // ───── विद्यार्थी माहिती (create.php header)
    private void renderInfo() {
        JsonObject s = J.o(data, "student");
        LinearLayout b = Form.cardBody(content, "विद्यार्थी माहिती");
        String[][] rows = {{"नाव", J.s(s, "name_mr")}, {"इयत्ता / तुकडी", J.s(s, "std") + (J.s(s, "section").isEmpty() ? "" : " / " + J.s(s, "section"))},
                {"हजेरी क्र.", J.s(s, "roll_no")}, {"लिंग", J.s(s, "gender")}, {"जन्मतारीख", J.s(s, "dob")}, {"आईचे नाव", J.s(s, "mother_name")}, {"वडिलांचे नाव", J.s(s, "father_name")}, {"शैक्षणिक वर्ष", J.s(data, "academic_year")}};
        for (String[] r : rows) {
            LinearLayout h = Form.horizontal(this);
            h.addView(Form.weight(Form.muted(this, r[0]), 1));
            h.addView(Form.weight(Form.body(this, r[1].isEmpty() ? "—" : r[1]), 2));
            b.addView(h);
        }
        LinearLayout l = Form.cardBody(content, "कामगिरी स्तर");
        for (JsonObject lv : J.list(data, "levels")) l.addView(Form.body(this, "• " + J.s(lv, "name_mr") + (J.s(lv, "name_en").isEmpty() ? "" : " (" + J.s(lv, "name_en") + ")")));
        LinearLayout d = Form.cardBody(content, "६ विकास क्षेत्रे");
        for (JsonObject dom : J.list(data, "domains")) d.addView(Form.body(this, J.s(dom, "id") + ". " + J.s(dom, "name_mr") + " — " + J.list(dom, "competencies").size() + " क्षमता"));
        printButtons();
    }

    private void printButtons() {
        JsonObject card = J.o(data, "card");
        int id = J.i(card, "id");
        Map<String, String> p = new HashMap<>();
        p.put("id", String.valueOf(id));
        p.put("format", "official19");
        bottomButton("१९-पानी कार्ड प्रिंट / PDF", true, v -> ReportPrinter.print(this, "cards/print_hpc_19.php", p, "HPC कार्ड"));
        bottomButton(card.get("status") != null && J.s(card, "status").equals("completed") ? "मसुदा करा" : "पूर्ण ✓", false, v -> {
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("foundational_card_save").put("student_id", studentId).put("status", J.s(card, "status").equals("completed") ? "draft" : "completed"), saved(true));
        });
    }

    // ───── Step 1: रुब्रिक — one क्षमता code per domain per semester
    private void renderRubric() {
        LinearLayout hb = Form.cardBody(content, "स्टेप १ — क्षमता निवडा (रुब्रिक)");
        domainPicker(hb, true);
        JsonObject dom = domain();
        JsonObject sm = sem(dom);
        List<JsonObject> goals = J.list(dom, "goals");
        if (!goals.isEmpty()) {
            LinearLayout g = Form.cardBody(content, "अभ्यासक्रम ध्येये");
            for (JsonObject go : goals) g.addView(Form.muted(this, J.s(go, "code") + " : " + J.s(go, "text")));
        }
        LinearLayout b = Form.cardBody(content, "क्षमता (एक निवडा)");
        List<JsonObject> comps = J.list(dom, "competencies");
        List<String> labels = new ArrayList<>();
        int sel = -1;
        String cur = J.s(sm, "code");
        for (int i = 0; i < comps.size(); i++) {
            labels.add(J.s(comps.get(i), "code") + " : " + J.s(comps.get(i), "text"));
            if (J.s(comps.get(i), "code").equals(cur)) sel = i;
        }
        ChipGroup grp = Form.choiceChips(this, labels, sel, null);
        b.addView(grp);
        bottomButton("क्षमता जतन करा", true, v -> {
            int i = Form.checkedIndex(grp);
            if (i < 0) {
                snack("क्षमता निवडा");
                return;
            }
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("save_code").put("student_id", studentId).put("domain_id", J.i(dom, "id")).put("semester", semester)
                    .put("capacity_code", J.s(comps.get(i), "code")), saved(true));
        });
        bottomButton("पुढील → अभिप्राय", false, v -> {
            tab = 2;
            render();
        });
    }

    // ───── Step 2: अभिप्राय — multi-select teacher feedback statements for the selected क्षमता
    private void renderAbhipray() {
        LinearLayout hb = Form.cardBody(content, "स्टेप २ — शिक्षक अभिप्राय");
        domainPicker(hb, true);
        JsonObject dom = domain();
        JsonObject sm = sem(dom);
        if (J.s(sm, "code").isEmpty()) {
            LinearLayout b = Form.cardBody(content, null);
            b.addView(Form.body(this, "या क्षेत्रासाठी / सत्रासाठी प्रथम स्टेप १ मध्ये क्षमता निवडा."));
            return;
        }
        LinearLayout b = Form.cardBody(content, J.s(sm, "code") + " : " + J.s(sm, "code_text"));
        if (J.b(data, "is_girl")) b.addView(Form.muted(this, "मुलगी — विधाने स्त्रीलिंगी रूपात दाखवली आहेत."));
        List<JsonObject> opts = J.list(sm, "abhipray_options");
        List<String> labels = new ArrayList<>();
        List<Integer> selected = new ArrayList<>();
        for (int i = 0; i < opts.size(); i++) {
            labels.add(J.s(opts.get(i), "text"));
            if (J.b(opts.get(i), "selected")) selected.add(i);
        }
        if (opts.isEmpty()) b.addView(Form.body(this, "या क्षमतेसाठी अभिप्राय विधाने उपलब्ध नाहीत."));
        ChipGroup grp = Form.multiChipsSel(this, labels, selected);
        b.addView(grp);
        if (!J.s(sm, "teacher_feedback").isEmpty()) {
            LinearLayout f = Form.cardBody(content, "जतन केलेला अभिप्राय");
            f.addView(Form.body(this, J.s(sm, "teacher_feedback")));
        }
        bottomButton("अभिप्राय जतन करा", true, v -> {
            JsonArray ids = new JsonArray();
            for (int i : Form.checkedIndexes(grp)) ids.add(J.i(opts.get(i), "id"));
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("save_abhipray").put("student_id", studentId).put("domain_id", J.i(dom, "id")).put("semester", semester).put("ids", ids), saved(true));
        });
        bottomButton("पुढील → कामगिरी", false, v -> {
            tab = 3;
            render();
        });
    }

    // ───── Step 3: कामगिरी — strengths / improvements per domain (annual)
    private void renderKamgiri() {
        LinearLayout hb = Form.cardBody(content, "स्टेप ३ — कामगिरी (बलस्थाने व सुधारणा)");
        domainPicker(hb, false);
        JsonObject dom = domain();
        LinearLayout s = Form.cardBody(content, "बलस्थाने");
        List<JsonObject> so = J.list(dom, "strengths");
        List<String> sl = new ArrayList<>();
        List<Integer> ss = new ArrayList<>();
        for (int i = 0; i < so.size(); i++) {
            sl.add(J.s(so.get(i), "text"));
            if (J.b(so.get(i), "selected")) ss.add(i);
        }
        ChipGroup sg = Form.multiChipsSel(this, sl, ss);
        s.addView(sg);
        LinearLayout im = Form.cardBody(content, "सुधारणेची क्षेत्रे");
        List<JsonObject> io = J.list(dom, "improvements");
        List<String> il = new ArrayList<>();
        List<Integer> is = new ArrayList<>();
        for (int i = 0; i < io.size(); i++) {
            il.add(J.s(io.get(i), "text"));
            if (J.b(io.get(i), "selected")) is.add(i);
        }
        ChipGroup ig = Form.multiChipsSel(this, il, is);
        im.addView(ig);
        bottomButton("कामगिरी जतन करा", true, v -> {
            JsonArray a = new JsonArray(), c = new JsonArray();
            for (int i : Form.checkedIndexes(sg)) a.add(J.i(so.get(i), "id"));
            for (int i : Form.checkedIndexes(ig)) c.add(J.i(io.get(i), "id"));
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("save_kamgiri").put("student_id", studentId).put("domain_id", J.i(dom, "id")).put("strengths", a).put("improvements", c), saved(true));
        });
        bottomButton("पुढील → उपस्थिती", false, v -> {
            tab = 4;
            render();
        });
    }

    // ───── उपस्थिती (create.php attendance grid)
    private void renderAttendance() {
        LinearLayout b = Form.cardBody(content, "मासिक उपस्थिती — " + J.s(data, "academic_year"));
        List<JsonObject> att = J.list(data, "attendance");
        List<TextInputLayout[]> cells = new ArrayList<>();
        for (JsonObject m : att) {
            LinearLayout r = Form.horizontal(this);
            r.addView(Form.weight(Form.body(this, J.s(m, "label")), 1));
            TextInputLayout w = Form.weight(Form.input(this, "कामाचे दिवस", J.i(m, "working_days") == 0 ? "" : J.s(m, "working_days"), false, true), 1);
            TextInputLayout p = Form.weight(Form.input(this, "उपस्थित", J.i(m, "days_present") == 0 ? "" : J.s(m, "days_present"), false, true), 1);
            r.addView(w);
            r.addView(p);
            b.addView(r);
            cells.add(new TextInputLayout[]{w, p});
        }
        bottomButton("उपस्थिती जतन करा", true, v -> {
            JsonArray arr = new JsonArray();
            for (int i = 0; i < att.size(); i++) {
                JsonObject o = new JsonObject();
                o.addProperty("month", J.i(att.get(i), "month"));
                o.addProperty("working_days", J.toInt(Form.val(cells.get(i)[0])));
                o.addProperty("days_present", J.toInt(Form.val(cells.get(i)[1])));
                arr.add(o);
            }
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("foundational_card_save").put("student_id", studentId).put("attendance", arr), saved(true));
        });
    }

    // ───── वार्षिक सारांश (create.php summary + teacher code + final feedback)
    private void renderSummary() {
        JsonObject card = J.o(data, "card");
        LinearLayout b = Form.cardBody(content, "वार्षिक सारांश");
        TextInputLayout code = Form.input(this, "शिक्षक कोड", J.s(card, "teacher_code"), false, false);
        b.addView(code);
        Map<String, TextInputLayout> f = new HashMap<>();
        for (JsonObject dom : J.list(data, "domains")) {
            TextInputLayout t = Form.input(this, dom.get("name_mr").getAsString() + " — सारांश", J.s(dom, "summary"), true, false);
            b.addView(t);
            f.put("summary_domain_" + J.s(dom, "id"), t);
        }
        LinearLayout ab = Form.cardBody(content, "क्षमता स्तर सारांश");
        String[][] abil = {{"summary_awareness", "जागरूकता"}, {"summary_sensitivity", "संवेदनशीलता"}, {"summary_creativity", "सर्जनशीलता"}};
        for (String[] a : abil) {
            TextInputLayout t = Form.input(this, a[1], J.s(card, a[0]), true, false);
            ab.addView(t);
            f.put(a[0], t);
        }
        LinearLayout fb = Form.cardBody(content, "अंतिम वार्षिक अभिप्राय");
        TextInputLayout fin = Form.input(this, "अभिप्राय", J.s(card, "final_annual_feedback"), true, false);
        fb.addView(fin);
        bottomButton("सारांश जतन करा", true, v -> {
            Native.P p = Native.P.of("foundational_card_save").put("student_id", studentId).put("teacher_code", Form.val(code)).put("final_annual_feedback", Form.val(fin));
            for (Map.Entry<String, TextInputLayout> e : f.entrySet()) p.put(e.getKey(), Form.val(e.getValue()));
            showLoading(true);
            Native.post(Native.HPC, p, saved(true));
        });
        bottomButton("पूर्ण ✓ व प्रिंट", false, v -> {
            Native.P p = Native.P.of("foundational_card_save").put("student_id", studentId).put("status", "completed").put("teacher_code", Form.val(code)).put("final_annual_feedback", Form.val(fin));
            for (Map.Entry<String, TextInputLayout> e : f.entrySet()) p.put(e.getKey(), Form.val(e.getValue()));
            showLoading(true);
            Native.post(Native.HPC, p, new Native.Cb() {
                @Override
                public void ok(JsonObject d) {
                    Map<String, String> pp = new HashMap<>();
                    pp.put("id", J.s(d, "card_id"));
                    pp.put("format", "official19");
                    ReportPrinter.print(HpcFoundationalActivity.this, "cards/print_hpc_19.php", pp, "HPC कार्ड");
                    load();
                }

                @Override
                public void fail(String m) {
                    showLoading(false);
                    snack(m);
                }
            });
        });
    }
}
