package com.techguruji.smartschoolhub.ui.cce;

import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;
import com.techguruji.smartschoolhub.utils.ReportPrinter;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

/** Mirrors cce/index.php — the CCE dashboard: stats, इयत्ता/सत्र selection and the six section groups. */
public class CceHubActivity extends NativePageActivity {

    public static final String EXTRA_PAGE = "page";
    private static final String PREFS = "cce_prefs";

    private JsonObject data;
    private final List<Integer> stds = new ArrayList<>();
    private final List<String> stdLabels = new ArrayList<>();

    public static int std(Context c) {
        return c.getSharedPreferences(PREFS, MODE_PRIVATE).getInt("std", 1);
    }

    public static int semester(Context c) {
        return c.getSharedPreferences(PREFS, MODE_PRIVATE).getInt("sem", 1);
    }

    public static void setStd(Context c, int std) {
        c.getSharedPreferences(PREFS, MODE_PRIVATE).edit().putInt("std", std).apply();
    }

    public static void setSemester(Context c, int sem) {
        c.getSharedPreferences(PREFS, MODE_PRIVATE).edit().putInt("sem", sem).apply();
    }

    public static String stdLabel(Context c, int std) {
        SharedPreferences sp = c.getSharedPreferences(PREFS, MODE_PRIVATE);
        return sp.getString("label_" + std, "इयत्ता " + std);
    }

    @Override
    protected void onReady(@Nullable Bundle state) {
        setTitle("CCE मूल्यमापन", "सातत्यपूर्ण सर्वंकष मूल्यमापन");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.CCE, Native.P.of("dashboard"), new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                data = d;
                showLoading(false);
                render();
            }

            @Override
            public void fail(String message) {
                error(message);
            }
        });
    }

    private void render() {
        clearContent();
        stds.clear();
        stdLabels.clear();
        SharedPreferences.Editor ed = getSharedPreferences(PREFS, MODE_PRIVATE).edit();
        for (JsonObject s : J.list(data, "standards")) {
            stds.add(J.i(s, "std"));
            stdLabels.add(J.s(s, "label"));
            ed.putString("label_" + J.i(s, "std"), J.s(s, "label"));
        }
        ed.apply();
        if (!stds.contains(std(this)) && !stds.isEmpty()) setStd(this, stds.get(0));

        JsonObject school = J.o(data, "school");
        setTitle("CCE मूल्यमापन", J.s(school, "name_mr") + " · " + J.s(data, "academic_year"));

        JsonObject stats = J.o(data, "stats");
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.stat(this, J.s(stats, "students"), "विद्यार्थी", "#1D4ED8"));
        row.addView(Form.stat(this, J.s(stats, "subjects"), "विषय", "#059669"));
        row.addView(Form.stat(this, J.s(stats, "standards"), "इयत्ता", "#D97706"));
        content.addView(row);

        LinearLayout sel = Form.cardBody(content, "इयत्ता व सत्र निवडा");
        sel.addView(Form.dropdown(this, "इयत्ता", stdLabels, stds.indexOf(std(this)), i -> setStd(this, stds.get(i))));
        List<String> sems = new ArrayList<>();
        sems.add("प्रथम सत्र");
        sems.add("द्वितीय सत्र");
        sel.addView(Form.choiceChips(this, sems, semester(this) - 1, i -> setSemester(this, i + 1)));
        sel.addView(Form.muted(this, "निवडलेली इयत्ता व सत्र सर्व CCE पानांवर लागू होते."));

        for (JsonObject sec : J.list(data, "sections")) {
            content.addView(Form.sectionHeader(this, J.s(sec, "title"), "#BF360C"));
            for (JsonObject it : J.list(sec, "items")) {
                String key = J.s(it, "key");
                content.addView(Form.tile(this, J.s(it, "title"), J.s(it, "subtitle"), J.s(it, "color"), v -> open(key)));
            }
        }
    }

    private void open(String key) {
        int std = std(this);
        int sem = semester(this);
        if (key.startsWith("report:")) {
            String r = key.substring(7);
            Map<String, String> p = new HashMap<>();
            p.put("std", String.valueOf(std));
            String page;
            switch (r) {
                case "result":
                    page = "cce/result.php";
                    p.put("semester", String.valueOf(sem));
                    break;
                case "annual":
                    page = "cce/annual.php";
                    break;
                case "nondvahi":
                    page = "cce/nondvahi.php";
                    p.put("semester", String.valueOf(sem));
                    break;
                case "pragati_pustak":
                    page = "cce/pragati_pustak.php";
                    p.put("all", "1");
                    p.put("size", "a4");
                    break;
                default:
                    page = "cce/progress_card_term1.php";
                    p.put("all", "1");
            }
            ReportPrinter.print(this, page, p, "CCE " + stdLabel(this, std));
            return;
        }
        Class<?> target;
        switch (key) {
            case "marks_entry":
            case "coscholastic":
            case "extra":
                target = CceMarksActivity.class;
                break;
            case "outcomes":
            case "remarks_entry":
            case "bank":
                target = CceNotesActivity.class;
                break;
            case "reports":
                target = CceReportsActivity.class;
                break;
            default:
                target = CceManageActivity.class;
        }
        startActivity(new Intent(this, target).putExtra(EXTRA_PAGE, key));
    }
}
