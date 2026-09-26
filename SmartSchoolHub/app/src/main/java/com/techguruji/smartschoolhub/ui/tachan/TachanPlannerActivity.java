package com.techguruji.smartschoolhub.ui.tachan;

import android.app.DatePickerDialog;
import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;
import com.techguruji.smartschoolhub.utils.ReportPrinter;

import java.util.ArrayList;
import java.util.Arrays;
import java.util.Calendar;
import java.util.List;
import java.util.Locale;

/**
 * Mirrors modules/tachan/day.php (दैनिक टाचण), weekly.php (साप्ताहिक) and list.php (जुने टाचण व शोध).
 * Every row is the same lesson_plans record the PHP pages render; tapping a row opens edit.php natively.
 */
public class TachanPlannerActivity extends NativePageActivity {

    private String page;
    private String date;
    private String mode = "";
    private int classId;
    private int jod;
    private String medium = "";
    private String dayLength = "";
    private int upto;
    private int listMonth;
    private int listYear;
    private String query = "";
    private List<JsonObject> classes = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        page = strExtra(TachanActivity.EXTRA_PAGE, "day");
        date = strExtra(TachanPlanEditActivity.EXTRA_DATE, TachanPlanEditActivity.today());
        classId = intExtra(TachanPlanEditActivity.EXTRA_CLASS_ID, 0);
        switch (page) {
            case "weekly":
                setTitle("साप्ताहिक टाचण", "संपूर्ण आठवड्याचे एकत्रित नियोजन");
                break;
            case "list":
                setTitle("जुने टाचण व शोध", "मागील सर्व नोंदींचा शोध व अहवाल");
                break;
            default:
                setTitle("दैनिक टाचण", "तासिकानिहाय पाठ नियोजन");
        }
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.P p;
        switch (page) {
            case "weekly":
                p = Native.P.of("weekly").put("date", date).put("medium", medium);
                if (classId > 0) p.put("class_id", classId);
                break;
            case "list":
                p = Native.P.of("list").put("month", listMonth).put("year", listYear).put("q", query);
                if (classId > 0) p.put("class_id", classId);
                break;
            default:
                p = Native.P.of("day").put("date", date).put("medium", medium).put("dl", dayLength);
                if (!mode.isEmpty()) p.put("mode", mode);
                if (classId > 0) p.put("class_id", classId);
                if (jod >= 0 && !mode.equals("teacher") && classId > 0) p.put("jod", jod);
                if (upto > 0) p.put("upto", upto);
        }
        Native.get(Native.TACHAN, p, new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                switch (page) {
                    case "weekly":
                        renderWeekly(d);
                        break;
                    case "list":
                        renderList(d);
                        break;
                    default:
                        renderDay(d);
                }
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }

    // ───────────────────────── day.php ─────────────────────────

    private void renderDay(JsonObject d) {
        clearContent();
        clearBottom();
        classes = J.list(d, "classes");
        mode = J.s(d, "mode", "class");
        classId = J.i(d, "class_id");
        jod = J.i(d, "jod");
        medium = J.s(d, "medium", "marathi");
        dayLength = J.s(d, "day_length", "full");
        upto = J.i(d, "upto", 8);
        date = J.s(d, "date", date);
        String flash = J.s(d, "message_flash");
        if (!flash.isEmpty()) snack(flash);

        LinearLayout head = Form.cardBody(content, null);
        head.addView(Form.title(this, J.s(d, "day_name") + ", " + J.s(d, "date_label")));
        head.addView(Form.muted(this, J.s(d, "school_name") + " · " + J.s(d, "teacher_name")));
        LinearLayout nav = Form.horizontal(this);
        nav.addView(Form.weight(Form.tonal(this, "‹ " + "आदल्या दिवशी", v -> go(J.s(d, "prev"))), 1f));
        nav.addView(Form.weight(Form.tonal(this, "दिनांक निवडा", v -> pickDate()), 1f));
        nav.addView(Form.weight(Form.tonal(this, "पुढील दिवस ›", v -> go(J.s(d, "next"))), 1f));
        head.addView(nav);
        List<String> tabL = new ArrayList<>();
        List<String> tabD = new ArrayList<>();
        int tabSel = -1;
        for (JsonObject t : J.list(d, "tabs")) {
            tabL.add(J.s(t, "label"));
            tabD.add(J.s(t, "date"));
            if (J.s(t, "date").equals(date)) tabSel = tabL.size() - 1;
        }
        head.addView(Form.choiceChips(this, tabL, tabSel, i -> go(tabD.get(i))));

        LinearLayout f = Form.cardBody(content, "निवड");
        if (J.b(d, "has_teacher_timetable") || mode.equals("teacher")) {
            f.addView(Form.label(this, "प्रकार"));
            f.addView(Form.choiceChips(this, Arrays.asList("माझे वेळापत्रक (शिक्षक)", "वर्गानुसार"), mode.equals("teacher") ? 0 : 1, i -> {
                mode = i == 0 ? "teacher" : "class";
                load();
            }));
        }
        if (!mode.equals("teacher")) {
            List<String> cl = classLabels();
            f.addView(Form.dropdown(this, "वर्ग", cl, indexOfClass(classId), i -> {
                classId = J.i(classes.get(i), "id");
                jod = 0;
                load();
            }));
            List<String> jl = new ArrayList<>();
            jl.add("— जोडवर्ग नाही —");
            jl.addAll(cl);
            int jSel = jod > 0 ? indexOfClass(jod) + 1 : 0;
            f.addView(Form.dropdown(this, "जोडवर्ग (ऐच्छिक)", jl, jSel, i -> {
                jod = i == 0 ? 0 : J.i(classes.get(i - 1), "id");
                load();
            }));
        }
        JsonObject meds = J.o(d, "mediums");
        List<String> mk = new ArrayList<>(meds.keySet());
        f.addView(Form.label(this, "माध्यम"));
        f.addView(Form.choiceChips(this, J.mapValues(meds), mk.indexOf(medium), i -> {
            medium = mk.get(i);
            load();
        }));
        JsonObject lens = J.o(d, "lengths");
        List<String> lk = new ArrayList<>(lens.keySet());
        f.addView(Form.label(this, "दिवसाची लांबी"));
        f.addView(Form.choiceChips(this, J.mapValues(lens), lk.indexOf(dayLength), i -> {
            dayLength = lk.get(i);
            if (dayLength.equals("upto")) askUpto();
            else load();
        }));

        LinearLayout info = Form.cardBody(content, null);
        String sv = J.s(d, "suvichar");
        if (!sv.isEmpty()) {
            info.addView(Form.label(this, "आजचा सुविचार"));
            info.addView(Form.body(this, sv));
        }
        String up = J.s(d, "upakram");
        if (!up.isEmpty()) {
            info.addView(Form.label(this, "दिनविशेष / उपक्रम"));
            info.addView(Form.body(this, up));
        }
        info.addView(Form.muted(this, "परिपाठ: " + J.s(d, "paripath_time")));

        if (J.b(d, "closed")) {
            LinearLayout c = Form.cardBody(content, "शाळा बंद");
            c.addView(Form.body(this, J.s(d, "holiday_name", "सुट्टी")));
            c.addView(Form.muted(this, "या दिवशी टाचण नोंदी घेतल्या जात नाहीत."));
        } else {
            for (JsonObject per : J.list(d, "periods")) periodCard(per);
        }

        bottomButton("प्रिंट / PDF", false, v -> ReportPrinter.print(this, J.o(d, "print"), "Tachan-" + date));
        bottomButton("पुन्हा तयार करा", false, v -> confirm("टाचण पुन्हा तयार करायचे?",
                mode.equals("teacher") ? "आजचे वेळापत्रकानुसार टाचण अधिकृत बँकेतून पुन्हा तयार होईल." : "या महिन्याचे वर्ग टाचण पुन्हा तयार होईल (हस्तलिखित नोंदी कायम राहतील).",
                () -> {
                    showLoading(true);
                    Native.P p = Native.P.of("day").put("date", date).put("mode", mode).put("class_id", classId).put("jod", jod)
                            .put("medium", medium).put("dl", dayLength).put("upto", upto).put("regen", true).put("regen_medium", medium);
                    Native.post(Native.TACHAN, p, saved(true));
                }));
        bottomButton("नवीन नोंद", true, v -> openEdit(0, 1, ""));
    }

    private void periodCard(JsonObject per) {
        int period = J.i(per, "period");
        JsonObject plan = J.o(per, "plan");
        boolean has = plan.has("id") && J.i(plan, "id") > 0;
        LinearLayout b = Form.cardBody(content, null);
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.weight(Form.title(this, "तासिका " + period + "  ·  " + J.s(per, "time")), 1f));
        String ttSub = J.s(per, "timetable_subject");
        if (!ttSub.isEmpty()) row.addView(Form.badge(this, ttSub, "#0284C7"));
        b.addView(row);
        String ttCls = J.s(per, "timetable_class");
        if (!ttCls.isEmpty()) b.addView(Form.muted(this, "वर्ग: " + ttCls));
        if (has) {
            planBody(b, plan);
            b.addView(Form.tonal(this, "संपादन", v -> openEdit(J.i(plan, "id"), period, "")));
        } else {
            b.addView(Form.muted(this, "या तासिकेसाठी टाचण नाही."));
            b.addView(Form.tonal(this, "+ नोंद जोडा", v -> openEdit(0, period, ttSub)));
        }
        JsonObject jp = J.o(per, "jod_plan");
        if (jp.has("id")) {
            b.addView(Form.divider(this));
            b.addView(Form.label(this, "जोडवर्ग — " + J.s(per, "jod_timetable_subject")));
            planBody(b, jp);
            b.addView(Form.tonal(this, "जोडवर्ग नोंद संपादन", v -> openEdit(J.i(jp, "id"), period, "")));
        }
    }

    private void planBody(LinearLayout b, JsonObject plan) {
        b.addView(Form.body(this, J.s(plan, "subject") + " — " + J.s(plan, "topic")));
        String[][] rows = {{"उद्दिष्टे", "objectives"}, {"कृती", "activities"}, {"मूल्यमापन", "eval_tool"}, {"साधने", "materials"},
                {"अध्ययन निष्पत्ती", "learning_outcome"}, {"गृहपाठ", "homework"}, {"शेरा", "notes"}};
        for (String[] r : rows) {
            String v = J.s(plan, r[1]);
            if (!v.isEmpty()) b.addView(Form.muted(this, r[0] + ": " + v));
        }
    }

    private void askUpto() {
        List<String> opts = new ArrayList<>();
        for (int i = 1; i <= 9; i++) opts.add("तासिका " + i + " पर्यंत");
        new com.google.android.material.dialog.MaterialAlertDialogBuilder(this).setTitle("कितव्या तासिकेपर्यंत?")
                .setItems(opts.toArray(new String[0]), (dlg, w) -> {
                    upto = w + 1;
                    load();
                }).show();
    }

    // ───────────────────────── weekly.php ─────────────────────────

    private void renderWeekly(JsonObject d) {
        clearContent();
        clearBottom();
        classes = J.list(d, "classes");
        classId = J.i(J.o(d, "class"), "id");
        medium = J.s(d, "medium", "marathi");
        date = J.s(d, "monday", date);
        LinearLayout head = Form.cardBody(content, "आठवडा: " + date + " पासून");
        head.addView(Form.muted(this, J.s(d, "school_name")));
        LinearLayout nav = Form.horizontal(this);
        nav.addView(Form.weight(Form.tonal(this, "‹ मागील आठवडा", v -> go(J.s(d, "prev"))), 1f));
        nav.addView(Form.weight(Form.tonal(this, "हा आठवडा", v -> go(J.s(d, "this_week"))), 1f));
        nav.addView(Form.weight(Form.tonal(this, "पुढील ›", v -> go(J.s(d, "next"))), 1f));
        head.addView(nav);
        head.addView(Form.dropdown(this, "वर्ग", classLabels(), indexOfClass(classId), i -> {
            classId = J.i(classes.get(i), "id");
            load();
        }));
        JsonObject meds = J.o(d, "mediums");
        List<String> mk = new ArrayList<>(meds.keySet());
        head.addView(Form.choiceChips(this, J.mapValues(meds), mk.indexOf(medium), i -> {
            medium = mk.get(i);
            load();
        }));
        for (JsonObject day : J.list(d, "days")) {
            LinearLayout b = Form.cardBody(content, J.s(day, "day_name") + " · " + J.s(day, "date_label"));
            String hol = J.s(day, "holiday");
            if (!hol.isEmpty()) {
                b.addView(Form.badge(this, "सुट्टी: " + hol, "#DC2626"));
                continue;
            }
            List<JsonObject> rows = J.list(day, "rows");
            if (rows.isEmpty()) b.addView(Form.muted(this, "नोंदी नाहीत"));
            List<List<String>> tr = new ArrayList<>();
            for (JsonObject r : rows) tr.add(Arrays.asList(String.valueOf(J.i(r, "period")), J.s(r, "subject"), J.s(r, "topic"), J.s(r, "learning_outcome")));
            if (!rows.isEmpty()) b.addView(Form.table(this, Arrays.asList("तासिका", "विषय", "घटक", "अध्ययन निष्पत्ती"), tr));
            b.addView(Form.tonal(this, "या दिवसाचे दैनिक टाचण", v -> startActivity(new Intent(this, TachanPlannerActivity.class)
                    .putExtra(TachanActivity.EXTRA_PAGE, "day").putExtra(TachanPlanEditActivity.EXTRA_DATE, J.s(day, "date"))
                    .putExtra(TachanPlanEditActivity.EXTRA_CLASS_ID, classId))));
        }
        bottomButton("प्रिंट / PDF", true, v -> ReportPrinter.print(this, J.o(d, "print"), "Tachan-week-" + date));
    }

    // ───────────────────────── list.php ─────────────────────────

    private void renderList(JsonObject d) {
        clearContent();
        clearBottom();
        classes = J.list(d, "classes");
        if (listYear == 0) listYear = J.i(d, "year", Calendar.getInstance().get(Calendar.YEAR));
        List<JsonObject> months = J.list(d, "months");
        List<String> ml = new ArrayList<>();
        ml.add("— सर्व महिने —");
        int mSel = 0;
        for (JsonObject m : months) {
            ml.add(J.s(m, "name"));
            if (J.i(m, "month") == listMonth) mSel = ml.size() - 1;
        }
        List<String> yl = new ArrayList<>();
        int y0 = Calendar.getInstance().get(Calendar.YEAR);
        for (int y = y0 + 1; y >= y0 - 5; y--) yl.add(String.valueOf(y));
        List<String> cl = new ArrayList<>();
        cl.add("— सर्व वर्ग —");
        cl.addAll(classLabels());

        LinearLayout f = Form.cardBody(content, "शोध व फिल्टर");
        TextInputLayout q = Form.input(this, "शोधा (घटक / विषय / निष्पत्ती)", query, false, false);
        f.addView(q);
        f.addView(Form.dropdown(this, "महिना", ml, mSel, i -> listMonth = i == 0 ? 0 : J.i(months.get(i - 1), "month")));
        f.addView(Form.dropdown(this, "वर्ष", yl, yl.indexOf(String.valueOf(listYear)), i -> listYear = Integer.parseInt(yl.get(i))));
        f.addView(Form.dropdown(this, "वर्ग", cl, classId > 0 ? indexOfClass(classId) + 1 : 0, i -> classId = i == 0 ? 0 : J.i(classes.get(i - 1), "id")));
        f.addView(Form.button(this, "शोधा", true, v -> {
            query = Form.val(q);
            load();
        }));
        f.addView(Form.tonal(this, "महिन्याचे संपूर्ण टाचण तयार करा", v -> {
            if (listMonth == 0) {
                snack("आधी महिना निवडा.");
                return;
            }
            confirm("संपूर्ण महिन्याचे टाचण तयार करायचे?", "सर्व वर्गांसाठी " + monthName(months, listMonth) + " " + listYear + " चे टाचण अधिकृत बँकेतून तयार होईल.", () -> {
                showLoading(true);
                Native.post(Native.TACHAN, Native.P.of("list").put("gen", true).put("month", listMonth).put("year", listYear), saved(true));
            });
        }));

        List<JsonObject> rows = J.list(d, "rows");
        LinearLayout b = Form.cardBody(content, "नोंदी (" + rows.size() + ")");
        if (rows.isEmpty()) b.addView(Form.muted(this, "नोंदी सापडल्या नाहीत."));
        for (JsonObject r : rows) {
            LinearLayout row = Form.horizontal(this);
            LinearLayout col = Form.vertical(this);
            col.addView(Form.body(this, J.s(r, "date") + "  ·  तासिका " + J.i(r, "period") + "  ·  " + J.s(r, "class_name")));
            col.addView(Form.muted(this, J.s(r, "subject") + " — " + J.s(r, "topic")));
            row.addView(Form.weight(col, 1f));
            row.addView(Form.badge(this, J.s(r, "src", "manual"), "#475569"));
            row.setClickable(true);
            row.setOnClickListener(v -> openEdit(J.i(r, "id"), J.i(r, "period"), ""));
            b.addView(row);
            b.addView(Form.divider(this));
        }
        bottomButton("नवीन नोंद", true, v -> openEdit(0, 1, ""));
    }

    // ───────────────────────── helpers ─────────────────────────

    private static String monthName(List<JsonObject> months, int m) {
        for (JsonObject o : months) if (J.i(o, "month") == m) return J.s(o, "name");
        return String.valueOf(m);
    }

    private List<String> classLabels() {
        List<String> l = new ArrayList<>();
        for (JsonObject c : classes) l.add(J.s(c, "name") + " (" + J.s(c, "label") + ")");
        return l;
    }

    private int indexOfClass(int id) {
        for (int i = 0; i < classes.size(); i++) if (J.i(classes.get(i), "id") == id) return i;
        return -1;
    }

    private void go(String d) {
        if (d.isEmpty()) return;
        date = d;
        load();
    }

    private void openEdit(int id, int period, String subject) {
        Intent i = new Intent(this, TachanPlanEditActivity.class).putExtra(TachanPlanEditActivity.EXTRA_ID, id)
                .putExtra(TachanPlanEditActivity.EXTRA_DATE, date).putExtra(TachanPlanEditActivity.EXTRA_PERIOD, period)
                .putExtra(TachanPlanEditActivity.EXTRA_CLASS_ID, classId).putExtra(TachanPlanEditActivity.EXTRA_SUBJECT, subject);
        startActivityForResult(i, 11);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, @Nullable Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode == 11 && resultCode == RESULT_OK) load();
    }

    private void pickDate() {
        Calendar c = Calendar.getInstance();
        try {
            String[] p = date.split("-");
            c.set(Integer.parseInt(p[0]), Integer.parseInt(p[1]) - 1, Integer.parseInt(p[2]));
        } catch (RuntimeException ignored) {
        }
        new DatePickerDialog(this, (dp, y, m, dd) -> go(String.format(Locale.US, "%04d-%02d-%02d", y, m + 1, dd)),
                c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH)).show();
    }
}
