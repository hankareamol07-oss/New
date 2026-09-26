package com.techguruji.smartschoolhub.ui.tachan;

import android.content.Intent;
import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.ui.common.NativePageActivity;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.J;

/** Mirrors modules/tachan/index.php — the टाचण dashboard with its 8 cards. */
public class TachanActivity extends NativePageActivity {

    public static final String EXTRA_PAGE = "page";

    @Override
    protected void onReady(@Nullable Bundle state) {
        setTitle("टाचण (पाठ नियोजन)", "दैनिक · साप्ताहिक · वार्षिक · वेळापत्रक");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.TACHAN, Native.P.of("dashboard"), new Native.Cb() {
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
        setTitle("टाचण (पाठ नियोजन)", J.s(d, "school_name"));
        LinearLayout hb = Form.cardBody(content, J.s(d, "greeting") + ", " + J.s(d, "teacher_name", "शिक्षक"));
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.stat(this, J.s(d, "total_plans", "0"), "एकूण टाचण नोंदी", "#0284C7"));
        row.addView(Form.stat(this, String.valueOf(J.list(d, "classes").size()), "वर्ग", "#059669"));
        hb.addView(row);
        if (!J.b(d, "has_classes")) hb.addView(Form.muted(this, "आधी 'वर्ग व्यवस्थापन' मधून वर्ग व तुकड्या तयार करा."));
        for (JsonObject c : J.list(d, "cards")) {
            String key = J.s(c, "key");
            content.addView(Form.tile(this, J.s(c, "title"), J.s(c, "subtitle") + "  ·  " + J.s(c, "badge"), J.s(c, "color", "#0284C7"), v -> open(key)));
        }
    }

    private void open(String key) {
        Class<?> target;
        switch (key) {
            case "day":
            case "weekly":
            case "list":
                target = TachanPlannerActivity.class;
                break;
            case "add":
                startActivity(new Intent(this, TachanPlanEditActivity.class));
                return;
            default:
                target = TachanSetupActivity.class;
        }
        startActivity(new Intent(this, target).putExtra(EXTRA_PAGE, key));
    }
}
