package com.techguruji.smartschoolhub.ui.cce;

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
import java.util.Arrays;
import java.util.List;

/** Mirrors cce/reports.php — the report center; every item prints the official PHP report via the system print/PDF sheet. */
public class CceReportsActivity extends NativePageActivity {

    private int std, semester;
    private final List<Integer> stds = new ArrayList<>();
    private final List<String> stdLabels = new ArrayList<>();

    @Override
    protected void onReady(@Nullable Bundle state) {
        std = CceHubActivity.std(this);
        semester = CceHubActivity.semester(this);
        setTitle("सर्व अहवाल केंद्र", "२०+ अहवाल व रजिस्टर — प्रिंट / PDF");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.CCE, Native.P.of("reports").put("std", std).put("semester", semester), new Native.Cb() {
            @Override
            public void ok(JsonObject d) {
                showLoading(false);
                clearContent();
                stds.clear();
                stdLabels.clear();
                for (JsonObject s : J.list(d, "standards")) {
                    stds.add(J.i(s, "std"));
                    stdLabels.add(J.s(s, "label"));
                }
                LinearLayout hb = Form.cardBody(content, null);
                hb.addView(Form.dropdown(CceReportsActivity.this, "इयत्ता", stdLabels, Math.max(0, stds.indexOf(std)), i -> {
                    std = stds.get(i);
                    CceHubActivity.setStd(CceReportsActivity.this, std);
                    load();
                }));
                hb.addView(Form.choiceChips(CceReportsActivity.this, Arrays.asList("प्रथम सत्र", "द्वितीय सत्र"), semester - 1, i -> {
                    semester = i + 1;
                    CceHubActivity.setSemester(CceReportsActivity.this, semester);
                    load();
                }));
                hb.addView(Form.muted(CceReportsActivity.this, "अहवालावर टॅप करा → Android प्रिंट/PDF शीट उघडेल (शासकीय PHP लेआउट तसाच)."));
                for (JsonObject g : J.list(d, "groups")) {
                    content.addView(Form.sectionHeader(CceReportsActivity.this, J.s(g, "title"), "#0F766E"));
                    for (JsonObject it : J.list(g, "items")) {
                        String title = J.s(it, "title");
                        content.addView(Form.tile(CceReportsActivity.this, title, J.s(it, "page").replace("cce/", "").replace(".php", ""), "#475569",
                                v -> ReportPrinter.print(CceReportsActivity.this, it, title)));
                    }
                }
            }

            @Override
            public void fail(String m) {
                error(m);
            }
        });
    }
}
