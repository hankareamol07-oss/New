package com.techguruji.smartschoolhub.ui.hpc;

import android.content.Intent;
import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;

/** Mirrors hpc/index.php + wizard.php landing: stage cards (पायाभूत / पूर्वतयारी / पूर्व-माध्यमिक) with इयत्ता entry points. */
public class HpcHubActivity extends NativePageActivity {

    @Override
    protected void onReady(@Nullable Bundle state) {
        setTitle("HPC — समग्र प्रगती पत्रक", "Holistic Progress Card");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.HPC, Native.P.of("dashboard"), new Native.Cb() {
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
        setTitle("HPC — समग्र प्रगती पत्रक", J.s(J.o(d, "school"), "name_mr") + " · " + J.s(d, "academic_year"));
        JsonObject stats = J.o(d, "stats");
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.stat(this, J.s(stats, "completed"), "पूर्ण कार्ड", "#059669"));
        row.addView(Form.stat(this, J.s(stats, "draft"), "मसुदा", "#D97706"));
        content.addView(row);

        for (JsonObject st : J.list(d, "stages")) {
            String color = J.s(st, "color", "#1D4ED8");
            content.addView(Form.sectionHeader(this, J.s(st, "name_mr") + " (" + J.s(st, "name_en") + ")", color));
            LinearLayout b = Form.cardBody(content, null);
            b.addView(Form.body(this, J.s(st, "summary_mr")));
            b.addView(Form.muted(this, J.s(st, "desc_mr")));
            LinearLayout tags = Form.horizontal(this);
            if (J.b(st, "has_wheel")) tags.addView(Form.badge(this, "प्रोग्रेस व्हील", "#10B981"));
            if (J.b(st, "has_ncrf")) tags.addView(Form.badge(this, "NCrF श्रेयांक", "#0284C7"));
            if (!J.b(st, "allowed")) tags.addView(Form.badge(this, "सशुल्क योजना", "#DC2626"));
            b.addView(tags);
            if (!J.b(st, "allowed") && !J.s(st, "access_message").isEmpty()) b.addView(Form.muted(this, J.s(st, "access_message")));
            for (String s : J.strings(st, "stds")) {
                int std = Integer.parseInt(s);
                String label = "इयत्ता " + std;
                for (JsonObject l : J.list(d, "standards")) if (J.i(l, "std") == std) label = J.s(l, "label");
                String finalLabel = label;
                b.addView(Form.tonal(this, finalLabel + " — विद्यार्थी यादी", v -> startActivity(new Intent(this, HpcStudentsActivity.class)
                        .putExtra("std", std).putExtra("label", finalLabel).putExtra("stage", J.s(st, "key")))));
            }
        }
    }
}
