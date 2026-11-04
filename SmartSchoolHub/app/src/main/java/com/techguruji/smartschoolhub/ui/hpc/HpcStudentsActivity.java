package com.techguruji.smartschoolhub.ui.hpc;

import android.content.Intent;
import android.os.Bundle;
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
import java.util.List;

/** Mirrors hpc/wizard.php student list for one इयत्ता: search, status filter, progress, open/print/delete card. */
public class HpcStudentsActivity extends NativePageActivity {

    private int std;
    private String search = "", status = "", division = "";

    @Override
    protected void onReady(@Nullable Bundle state) {
        std = intExtra("std", 1);
        setTitle(strExtra("label", "इयत्ता " + std), "HPC विद्यार्थी यादी");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.HPC, Native.P.of("students").put("std", std).put("search", search).put("status", status).put("division", division), new Native.Cb() {
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
        JsonObject stats = J.o(d, "stats");
        LinearLayout row = Form.horizontal(this);
        row.addView(Form.stat(this, J.s(stats, "total"), "एकूण", "#1D4ED8"));
        row.addView(Form.stat(this, J.s(stats, "completed"), "पूर्ण", "#059669"));
        row.addView(Form.stat(this, J.s(stats, "draft"), "मसुदा", "#D97706"));
        row.addView(Form.stat(this, J.s(stats, "pending"), "बाकी", "#64748B"));
        content.addView(row);
        boolean allowed = J.b(d, "allowed");
        if (!allowed) {
            LinearLayout b = Form.cardBody(content, "सशुल्क योजना आवश्यक");
            b.addView(Form.body(this, J.s(d, "access_message", "इयत्ता ३ ते ८ साठी HPC मॉड्यूल सक्रिय करा.")));
        }
        LinearLayout f = Form.cardBody(content, null);
        TextInputLayout q = Form.input(this, "नाव / हजेरी क्र. शोधा", search, false, false);
        f.addView(q);
        List<String> divs = J.strings(d, "divisions");
        if (divs.size() > 1) {
            List<String> opts = new ArrayList<>();
            opts.add("सर्व तुकड्या");
            for (String s : divs) opts.add(s.isEmpty() ? "(तुकडी नाही)" : "तुकडी " + s);
            f.addView(Form.dropdown(this, "तुकडी", opts, Math.max(0, divs.indexOf(division) + 1), i -> {
                division = i == 0 ? "" : divs.get(i - 1);
                load();
            }));
        }
        List<String> stKeys = Arrays.asList("", "completed", "draft", "pending");
        f.addView(Form.choiceChips(this, Arrays.asList("सर्व", "पूर्ण", "मसुदा", "बाकी"), stKeys.indexOf(status), i -> {
            status = stKeys.get(i);
            search = Form.val(q);
            load();
        }));
        f.addView(Form.tonal(this, "शोधा", v -> {
            search = Form.val(q);
            load();
        }));

        List<JsonObject> students = J.list(d, "students");
        if (students.isEmpty()) empty("विद्यार्थी सापडले नाहीत.");
        boolean stage = std >= 3;
        for (JsonObject s : students) {
            int sid = J.i(s, "id");
            int cardId = J.i(s, "card_id");
            String st = J.s(s, "status");
            LinearLayout b = Form.cardBody(content, null);
            LinearLayout head = Form.horizontal(this);
            head.addView(Form.weight(Form.title(this, J.s(s, "roll_no") + ". " + J.s(s, "name_mr")), 1));
            head.addView(Form.badge(this, st.equals("completed") ? "पूर्ण" : st.equals("draft") ? "मसुदा" : "बाकी",
                    st.equals("completed") ? "#059669" : st.equals("draft") ? "#D97706" : "#64748B"));
            b.addView(head);
            String meta = (J.s(s, "gender").isEmpty() ? "" : J.s(s, "gender") + " · ") + (J.s(s, "section").isEmpty() ? "" : "तुकडी " + J.s(s, "section") + " · ") + (J.s(s, "updated_at").isEmpty() ? "" : J.s(s, "updated_at"));
            if (!meta.isEmpty()) b.addView(Form.muted(this, meta));
            if (!stage) b.addView(Form.muted(this, "प्रगती: सत्र १ — " + J.s(s, "progress_sem1") + "% · सत्र २ — " + J.s(s, "progress_sem2") + "%"));
            LinearLayout act = Form.horizontal(this);
            act.addView(Form.weight(Form.button(this, cardId > 0 ? "कार्ड उघडा" : "कार्ड तयार करा", true, v -> {
                if (!allowed) {
                    info("सशुल्क योजना", J.s(d, "access_message", "इयत्ता ३ ते ८ साठी HPC मॉड्यूल सक्रिय करा."));
                    return;
                }
                Intent i = new Intent(this, stage ? HpcStageActivity.class : HpcFoundationalActivity.class).putExtra("student_id", sid).putExtra("name", J.s(s, "name_mr"));
                startActivity(i);
            }), 1));
            if (cardId > 0) {
                act.addView(Form.weight(Form.tonal(this, "प्रिंट", v -> Native.get(Native.HPC, Native.P.of("card_view").put("card_id", cardId), new Native.Cb() {
                    @Override
                    public void ok(JsonObject data) {
                        ReportPrinter.print(HpcStudentsActivity.this, J.o(data, "print"), "HPC " + J.s(s, "name_mr"));
                    }

                    @Override
                    public void fail(String message) {
                        snack(message);
                    }
                })), 1));
                act.addView(Form.weight(Form.tonal(this, "हटवा", v -> confirm("कार्ड हटवायचे?", J.s(s, "name_mr") + " — HPC कार्ड व सर्व नोंदी हटवल्या जातील.", () -> {
                    showLoading(true);
                    Native.post(Native.HPC, Native.P.of("card_delete").put("card_id", cardId), saved(true));
                })), 1));
            }
            b.addView(act);
        }
    }
}
