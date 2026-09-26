package com.techguruji.smartschoolhub.ui.hpc;

import android.os.Bundle;
import android.widget.LinearLayout;

import androidx.annotation.Nullable;

import com.google.android.material.chip.ChipGroup;
import com.google.android.material.textfield.TextInputLayout;
import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
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
 * पूर्वतयारी / पूर्व-माध्यमिक (इ. ३–८) stage form, mirroring hpc/stage_form.php + save_stage.php:
 * subject tabs → सत्र १ / सत्र २ / वार्षिक सारांश; goals, competencies, activity, assessment questions,
 * rubric (3 abilities × levels, AI bank fill), teacher notes, self / peer reflection, strengths / hurdles, credits, status.
 */
public class HpcStageActivity extends NativePageActivity {

    private static final List<String> YN = Arrays.asList("—", "😊 हो", "😐 काही प्रमाणात", "☹ नाही", "❓ खात्री नाही");
    private static final List<String> YN_KEYS = Arrays.asList("", "yes", "some", "no", "unsure");

    private int studentId, subjIdx = 0, term = 1;
    private JsonObject data;

    @Override
    protected void onReady(@Nullable Bundle state) {
        studentId = intExtra("student_id", 0);
        setTitle(strExtra("name", "HPC"), "इ. ३–८ स्टेज फॉर्म");
    }

    @Override
    protected void load() {
        showLoading(true);
        Native.get(Native.HPC, Native.P.of("stage_get").put("student_id", studentId), new Native.Cb() {
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
        boolean mid = J.b(data, "is_middle");
        setTitle(J.s(s, "name_mr"), "इयत्ता " + J.s(data, "std") + " · " + (mid ? "पूर्व-माध्यमिक" : "पूर्वतयारी") + " · " + (J.s(card, "status").equals("completed") ? "पूर्ण" : "मसुदा"));
        List<JsonObject> subjects = J.list(data, "subjects");
        if (subjects.isEmpty()) {
            empty("या इयत्तेसाठी विषय अभ्यासक्रम उपलब्ध नाही.");
            return;
        }
        if (subjIdx >= subjects.size()) subjIdx = 0;
        List<String> names = new ArrayList<>();
        for (JsonObject sb : subjects) names.add(J.s(sb, "name_mr"));
        LinearLayout hb = Form.cardBody(content, null);
        hb.addView(Form.dropdown(this, "विषय", names, subjIdx, i -> {
            subjIdx = i;
            render();
        }));
        hb.addView(Form.choiceChips(this, Arrays.asList("सत्र १", "सत्र २", "वार्षिक सारांश (भाग क)"), term - 1, i -> {
            term = i + 1;
            render();
        }));
        JsonObject sub = subjects.get(subjIdx);
        if (term == 3) renderSummary(sub);
        else renderTerm(sub, term);

        int cardId = J.i(card, "id");
        Map<String, String> p = new HashMap<>();
        p.put("id", String.valueOf(cardId));
        bottomButton("🖨 कार्ड", false, v -> ReportPrinter.print(this, "cards/print_hpc_stage.php", p, "HPC कार्ड"));
        boolean done = J.s(card, "status").equals("completed");
        bottomButton(done ? "मसुदा" : "पूर्ण ✓", true, v -> {
            showLoading(true);
            Native.post(Native.HPC, Native.P.of("stage_save").put("student_id", studentId).put("stage_action", "status").put("status", done ? "draft" : "completed"), saved(true));
        });
    }

    private JsonObject termData(JsonObject sub, int t) {
        for (JsonObject o : J.list(sub, "terms")) if (J.i(o, "term") == t) return o;
        return new JsonObject();
    }

    private static List<String> strs(JsonElement e) {
        return J.strings(e);
    }

    // ───────────────────────── one सत्र of one विषय ─────────────────────────
    private void renderTerm(JsonObject sub, int t) {
        JsonObject d = termData(sub, t);
        boolean mid = J.b(data, "is_middle");
        String subjectKey = J.s(sub, "subject_key");
        List<JsonObject> levels = J.list(data, "levels");
        List<JsonObject> abilities = J.list(data, "abilities");
        JsonObject statements = J.o(data, "statements");
        final Native.P p = Native.P.of("stage_save").put("student_id", studentId).put("stage_action", "subject").put("subject_key", subjectKey).put("term", t);

        // ध्येये
        List<JsonObject> goals = J.list(sub, "goals");
        List<String> gl = new ArrayList<>();
        List<Integer> gsel = new ArrayList<>();
        List<String> curGoals = strs(d.get("goal_codes"));
        for (int i = 0; i < goals.size(); i++) {
            String txt = J.s(goals.get(i), "text");
            gl.add(J.s(goals.get(i), "code") + (txt.isEmpty() ? "" : " : " + (txt.length() > 60 ? txt.substring(0, 60) + "…" : txt)));
            if (curGoals.contains(J.s(goals.get(i), "code"))) gsel.add(i);
        }
        LinearLayout gb = Form.cardBody(content, "अभ्यासक्रम ध्येये (" + goals.size() + ")");
        ChipGroup gGrp = Form.multiChipsSel(this, gl, gsel);
        gb.addView(gGrp);

        // क्षमता
        List<JsonObject> comps = J.list(sub, "comps");
        List<String> cl = new ArrayList<>();
        List<Integer> csel = new ArrayList<>();
        List<String> curComps = strs(d.get("comp_codes"));
        for (int i = 0; i < comps.size(); i++) {
            String txt = J.s(comps.get(i), "text");
            cl.add(J.s(comps.get(i), "code") + (txt.isEmpty() ? "" : " : " + (txt.length() > 70 ? txt.substring(0, 70) + "…" : txt)));
            if (curComps.contains(J.s(comps.get(i), "code"))) csel.add(i);
        }
        LinearLayout cb = Form.cardBody(content, "क्षमता (" + comps.size() + ")");
        if (comps.isEmpty()) cb.addView(Form.muted(this, "या विषयासाठी क्षमता-मजकूर अजून लोड झालेला नाही."));
        ChipGroup cGrp = Form.multiChipsSel(this, cl, csel);
        cb.addView(cGrp);

        // कृती
        LinearLayout ab = Form.cardBody(content, "कृती / उपक्रम व मूल्यांकन");
        ChipGroup apprGrp = null;
        List<JsonObject> appr = J.list(statements, "approach");
        if (mid && !appr.isEmpty()) {
            ab.addView(Form.label(this, "कृतीचा दृष्टिकोन"));
            List<String> al = new ArrayList<>();
            List<Integer> asel = new ArrayList<>();
            List<String> cur = strs(d.get("activity_approach"));
            for (int i = 0; i < appr.size(); i++) {
                al.add(J.s(appr.get(i), "text"));
                if (cur.contains(J.s(appr.get(i), "value"))) asel.add(i);
            }
            apprGrp = Form.multiChipsSel(this, al, asel);
            ab.addView(apprGrp);
        }
        TextInputLayout activity = Form.input(this, "कृती / उपक्रम", J.s(d, "activity_mr"), true, false);
        TextInputLayout questions = Form.input(this, "मूल्यांकन प्रश्न (प्रति ओळ एक)", J.s(d, "assessment_questions_mr"), true, false);
        ab.addView(activity);
        ab.addView(questions);

        // रुब्रिक
        LinearLayout rb = Form.cardBody(content, "मूल्यांकन निकष (रुब्रिक) — कामगिरी स्तर");
        JsonObject rubric = J.o(d, "rubric_json");
        JsonObject bank = J.o(sub, "rubric_bank");
        List<String> lvNames = new ArrayList<>();
        for (JsonObject l : levels) lvNames.add(J.s(l, "name_mr"));
        Map<String, ChipGroup> lvPick = new HashMap<>();
        Map<String, TextInputLayout> rubText = new HashMap<>();
        Map<String, Map<String, TextInputLayout>> rubCells = new HashMap<>();
        List<String> bankComps = new ArrayList<>();
        for (String code : curComps) if (bank.has(code)) bankComps.add(code);
        final String[] src = {J.s(J.o(rubric, abilities.isEmpty() ? "" : J.s(abilities.get(0), "key")), "src")};
        if (!bankComps.isEmpty()) {
            rb.addView(Form.muted(this, "✨ AI सूचना (अधिकृत SCERT मजकूर नाही) — क्षमता निवडून 'भरा' दाबा; नंतर कोणताही कक्ष संपादित करू शकता."));
            List<String> opts = new ArrayList<>();
            opts.add("— क्षमता निवडा —");
            opts.addAll(bankComps);
            LinearLayout r = Form.horizontal(this);
            TextInputLayout sel = Form.weight(Form.dropdown(this, "AI सूचना", opts, Math.max(0, opts.indexOf(src[0])), i -> src[0] = i == 0 ? "" : opts.get(i)), 2);
            r.addView(sel);
            r.addView(Form.weight(Form.tonal(this, "भरा", v -> {
                if (src[0].isEmpty()) {
                    snack("प्रथम क्षमता निवडा");
                    return;
                }
                JsonObject bk = J.o(bank, src[0]);
                for (JsonObject a : abilities) {
                    String ak = J.s(a, "key");
                    JsonObject byLevel = J.o(bk, ak);
                    for (JsonObject l : levels) {
                        String lk = J.s(l, "key");
                        Form.setVal(rubCells.get(ak).get(lk), J.s(byLevel, lk));
                    }
                    int li = Form.checkedIndex(lvPick.get(ak));
                    if (li >= 0 && Form.val(rubText.get(ak)).trim().isEmpty()) Form.setVal(rubText.get(ak), J.s(byLevel, J.s(levels.get(li), "key")));
                }
            }), 1));
            rb.addView(r);
        }
        for (JsonObject a : abilities) {
            String ak = J.s(a, "key");
            JsonObject rj = J.o(rubric, ak);
            String lvl = J.s(rj, "level", J.s(d, "level_" + ak));
            int li = -1;
            for (int i = 0; i < levels.size(); i++) if (J.s(levels.get(i), "key").equals(lvl)) li = i;
            rb.addView(Form.sectionHeader(this, J.s(a, "name_mr"), J.s(a, "color", "#1D4ED8")));
            ChipGroup g = Form.choiceChips(this, lvNames, li, null);
            rb.addView(g);
            lvPick.put(ak, g);
            Map<String, TextInputLayout> cells = new HashMap<>();
            for (JsonObject l : levels) {
                String lk = J.s(l, "key");
                TextInputLayout c = Form.input(this, J.s(l, "name_mr") + " — निकष मजकूर", J.s(rj, lk), true, false);
                rb.addView(c);
                cells.put(lk, c);
            }
            rubCells.put(ak, cells);
            TextInputLayout tx = Form.input(this, J.s(a, "name_mr") + " — निरीक्षण / निकष मजकूर", J.s(rj, "text"), true, false);
            rb.addView(tx);
            rubText.put(ak, tx);
        }

        LinearLayout nb = Form.cardBody(content, "शिक्षक नोंदी");
        TextInputLayout challenges = Form.input(this, "आव्हाने", J.s(d, "challenges_mr"), true, false);
        TextInputLayout support = Form.input(this, "मात / मदत", J.s(d, "support_mr"), true, false);
        TextInputLayout notes = Form.input(this, "शिक्षकांच्या निरीक्षणात्मक नोंदी", J.s(d, "teacher_notes_mr"), true, false);
        nb.addView(challenges);
        nb.addView(support);
        nb.addView(notes);

        // आत्मचिंतन
        LinearLayout sb = Form.cardBody(content, "विद्यार्थ्याचे आत्मचिंतन");
        JsonObject selfRef = J.o(d, "self_reflection");
        List<JsonObject> selfSt = J.list(statements, "self");
        List<TextInputLayout> selfPick = new ArrayList<>();
        for (int i = 0; i < selfSt.size(); i++) {
            sb.addView(Form.muted(this, J.s(selfSt.get(i), "text")));
            TextInputLayout dd = Form.dropdown(this, "उत्तर", YN, Math.max(0, YN_KEYS.indexOf(J.s(selfRef, String.valueOf(i + 1)))), null);
            sb.addView(dd);
            selfPick.add(dd);
        }
        Map<String, TextInputLayout> spc = new HashMap<>();
        if (mid) {
            sb.addView(Form.label(this, "माझ्या प्रगतीचा आराखडा — गोल केलेल्या विधानांची संख्या (0–6)"));
            JsonObject cur = J.o(d, "self_progress_count");
            LinearLayout r = Form.horizontal(this);
            for (JsonObject a : abilities) {
                TextInputLayout n = Form.weight(Form.input(this, J.s(a, "name_mr"), cur.has(J.s(a, "key")) ? J.s(cur, J.s(a, "key")) : "", false, true), 1);
                r.addView(n);
                spc.put(J.s(a, "key"), n);
            }
            sb.addView(r);
        }
        TextInputLayout liked = Form.input(this, "सर्वात आवडले", J.s(d, "liked_most_mr"), false, false);
        TextInputLayout practice = Form.input(this, "अधिक सराव", J.s(d, "need_practice_mr"), false, false);
        TextInputLayout help = Form.input(this, "मदत हवी", J.s(d, "need_help_mr"), false, false);
        TextInputLayout learning = Form.input(this, "माझे शिकणे", J.s(d, "my_learning_mr"), true, false);
        sb.addView(liked);
        sb.addView(practice);
        sb.addView(help);
        sb.addView(learning);

        // सहाध्यायी
        TextInputLayout peerName = null, peerPractice = null, peerHelp = null;
        List<TextInputLayout> peerPick = new ArrayList<>();
        Map<String, TextInputLayout> ppc = new HashMap<>();
        if (mid) {
            LinearLayout pb = Form.cardBody(content, "सहाध्यायी अभिप्राय");
            peerName = Form.input(this, "सहाध्यायीचे नाव", J.s(d, "peer_name"), false, false);
            pb.addView(peerName);
            JsonObject peerRef = J.o(d, "peer_reflection");
            List<JsonObject> peerSt = J.list(statements, "peer");
            for (int i = 0; i < peerSt.size(); i++) {
                pb.addView(Form.muted(this, J.s(peerSt.get(i), "text")));
                TextInputLayout dd = Form.dropdown(this, "उत्तर", YN, Math.max(0, YN_KEYS.indexOf(J.s(peerRef, String.valueOf(i + 1)))), null);
                pb.addView(dd);
                peerPick.add(dd);
            }
            JsonObject cur = J.o(d, "peer_progress_count");
            LinearLayout r = Form.horizontal(this);
            for (JsonObject a : abilities) {
                TextInputLayout n = Form.weight(Form.input(this, J.s(a, "name_mr"), cur.has(J.s(a, "key")) ? J.s(cur, J.s(a, "key")) : "", false, true), 1);
                r.addView(n);
                ppc.put(J.s(a, "key"), n);
            }
            pb.addView(r);
            peerPractice = Form.input(this, "अधिक सराव (सहाध्यायीच्या मते)", J.s(d, "peer_need_practice_mr"), false, false);
            peerHelp = Form.input(this, "मदत हवी (सहाध्यायीच्या मते)", J.s(d, "peer_need_help_mr"), false, false);
            pb.addView(peerPractice);
            pb.addView(peerHelp);
        }

        // बलस्थान / अडथळे
        LinearLayout tb = Form.cardBody(content, "शिक्षक अभिप्राय — बलस्थान / अडथळे");
        tb.addView(Form.label(this, "बलस्थान क्षेत्र"));
        List<JsonObject> strSt = filterOther(J.list(statements, "strength"));
        List<String> curStr = strs(d.get("strengths"));
        ChipGroup strGrp = Form.multiChipsSel(this, texts(strSt), selectedIdx(strSt, curStr));
        tb.addView(strGrp);
        TextInputLayout strOther = Form.input(this, "इतर", other(curStr), false, false);
        tb.addView(strOther);
        tb.addView(Form.label(this, "यशस्वी होण्यातील अडथळे"));
        List<JsonObject> hurSt = filterOther(J.list(statements, "hurdle"));
        List<String> curHur = strs(d.get("hurdles"));
        ChipGroup hurGrp = Form.multiChipsSel(this, texts(hurSt), selectedIdx(hurSt, curHur));
        tb.addView(hurGrp);
        TextInputLayout hurOther = Form.input(this, "इतर", other(curHur), false, false);
        tb.addView(hurOther);
        TextInputLayout tHelp = Form.input(this, "विद्यार्थ्याच्या प्रगतीत वाढ होण्यासाठी मी मदत करू शकतो का?", J.s(d, "teacher_help_mr"), true, false);
        TextInputLayout tObs = Form.input(this, "शिक्षकांचे निरीक्षण आणि शिफारशी", J.s(d, "teacher_observation_mr"), true, false);
        tb.addView(tHelp);
        tb.addView(tObs);

        final ChipGroup apprFinal = apprGrp;
        final TextInputLayout pn = peerName, pp = peerPractice, ph = peerHelp;
        bottomButton("सत्र " + t + " जतन करा", true, v -> {
            JsonArray gc = new JsonArray(), cc = new JsonArray();
            for (int i : Form.checkedIndexes(gGrp)) gc.add(J.s(goals.get(i), "code"));
            for (int i : Form.checkedIndexes(cGrp)) cc.add(J.s(comps.get(i), "code"));
            p.put("goal_codes", gc).put("comp_codes", cc);
            if (apprFinal != null) {
                JsonArray aa = new JsonArray();
                for (int i : Form.checkedIndexes(apprFinal)) aa.add(J.s(appr.get(i), "value"));
                p.put("activity_approach", aa);
            }
            p.put("activity_mr", Form.val(activity)).put("assessment_questions_mr", Form.val(questions));
            JsonObject rj = new JsonObject();
            JsonObject wheel = new JsonObject();
            JsonObject spcO = new JsonObject(), ppcO = new JsonObject();
            for (Map.Entry<String, TextInputLayout> e : spc.entrySet()) if (!Form.val(e.getValue()).isEmpty()) spcO.addProperty(e.getKey(), J.toInt(Form.val(e.getValue())));
            for (Map.Entry<String, TextInputLayout> e : ppc.entrySet()) if (!Form.val(e.getValue()).isEmpty()) ppcO.addProperty(e.getKey(), J.toInt(Form.val(e.getValue())));
            for (JsonObject a : abilities) {
                String ak = J.s(a, "key");
                JsonObject o = new JsonObject();
                int li = Form.checkedIndex(lvPick.get(ak));
                String lvl = li >= 0 ? J.s(levels.get(li), "key") : null;
                o.addProperty("level", lvl);
                o.addProperty("text", Form.val(rubText.get(ak)));
                for (Map.Entry<String, TextInputLayout> c : rubCells.get(ak).entrySet()) if (!Form.val(c.getValue()).trim().isEmpty()) o.addProperty(c.getKey(), Form.val(c.getValue()).trim());
                if (!src[0].isEmpty()) o.addProperty("src", src[0]);
                rj.add(ak, o);
                JsonObject w = new JsonObject();
                w.addProperty("teacher", lvl);
                w.addProperty("student", lvlOf(spcO, ak));
                w.addProperty("peer", lvlOf(ppcO, ak));
                wheel.add(ak, w);
            }
            p.put("rubric_json", rj).put("wheel_json", wheel);
            p.put("challenges_mr", Form.val(challenges)).put("support_mr", Form.val(support)).put("teacher_notes_mr", Form.val(notes));
            JsonObject sr = new JsonObject();
            for (int i = 0; i < selfPick.size(); i++) {
                int k = YN.indexOf(Form.val(selfPick.get(i)));
                if (k > 0) sr.addProperty(String.valueOf(i + 1), YN_KEYS.get(k));
            }
            p.put("self_reflection", sr);
            if (mid) p.put("self_progress_count", spcO);
            p.put("liked_most_mr", Form.val(liked)).put("need_practice_mr", Form.val(practice)).put("need_help_mr", Form.val(help)).put("my_learning_mr", Form.val(learning));
            if (mid) {
                JsonObject pr = new JsonObject();
                for (int i = 0; i < peerPick.size(); i++) {
                    int k = YN.indexOf(Form.val(peerPick.get(i)));
                    if (k > 0) pr.addProperty(String.valueOf(i + 1), YN_KEYS.get(k));
                }
                p.put("peer_reflection", pr).put("peer_progress_count", ppcO).put("peer_name", Form.val(pn)).put("peer_need_practice_mr", Form.val(pp)).put("peer_need_help_mr", Form.val(ph));
            }
            p.put("strengths", listWithOther(strSt, strGrp, strOther)).put("hurdles", listWithOther(hurSt, hurGrp, hurOther));
            p.put("teacher_help_mr", Form.val(tHelp)).put("teacher_observation_mr", Form.val(tObs));
            showLoading(true);
            Native.post(Native.HPC, p, saved(true));
        });
    }

    private static String lvlOf(JsonObject counts, String ak) {
        if (!counts.has(ak)) return null;
        int n = J.i(counts, ak);
        return n <= 2 ? "prarambhik" : n <= 4 ? "praveen" : "pragat";
    }

    private static List<JsonObject> filterOther(List<JsonObject> in) {
        List<JsonObject> out = new ArrayList<>();
        for (JsonObject o : in) if (!J.s(o, "text").trim().equals("इतर")) out.add(o);
        return out;
    }

    private static List<String> texts(List<JsonObject> st) {
        List<String> out = new ArrayList<>();
        for (JsonObject o : st) out.add(J.s(o, "text"));
        return out;
    }

    private static List<Integer> selectedIdx(List<JsonObject> st, List<String> cur) {
        List<Integer> out = new ArrayList<>();
        for (int i = 0; i < st.size(); i++) if (cur.contains(J.s(st.get(i), "value"))) out.add(i);
        return out;
    }

    private static String other(List<String> cur) {
        for (String s : cur) if (s.startsWith("other:")) return s.substring(6);
        return "";
    }

    private static JsonArray listWithOther(List<JsonObject> st, ChipGroup g, TextInputLayout other) {
        JsonArray a = new JsonArray();
        for (int i : Form.checkedIndexes(g)) a.add(J.s(st.get(i), "value"));
        String o = Form.val(other).trim();
        if (!o.isEmpty()) a.add("other:" + o);
        return a;
    }

    // ───────────────────────── वार्षिक सारांश (भाग – क) + श्रेयांक ─────────────────────────
    private void renderSummary(JsonObject sub) {
        JsonObject sm = J.o(sub, "summary");
        List<JsonObject> levels = J.list(data, "levels");
        List<String> lvOpts = new ArrayList<>();
        lvOpts.add("—");
        for (JsonObject l : levels) lvOpts.add(J.s(l, "name_mr"));
        LinearLayout b = Form.cardBody(content, "📗 वार्षिक सारांश (भाग – क) — " + J.s(sub, "name_mr"));
        Map<String, TextInputLayout> picks = new HashMap<>();
        for (JsonObject a : J.list(data, "abilities")) {
            String ak = J.s(a, "key");
            int sel = 0;
            for (int i = 0; i < levels.size(); i++) if (J.s(levels.get(i), "key").equals(J.s(sm, "level_" + ak))) sel = i + 1;
            TextInputLayout dd = Form.dropdown(this, J.s(a, "name_mr") + " — अंतिम स्तर", lvOpts, sel, null);
            b.addView(dd);
            picks.put(ak, dd);
        }
        TextInputLayout credit = Form.input(this, "मिळवलेले श्रेयांक गुण", sm.get("credit_points_earned") == null || sm.get("credit_points_earned").isJsonNull() ? "" : J.s(sm, "credit_points_earned"), false, true);
        TextInputLayout remark = Form.input(this, "विषय शेरा", J.s(sm, "remark_mr"), true, false);
        b.addView(credit);
        b.addView(remark);
        JsonObject credits = J.o(data, "credits");
        if (credits.size() > 0) {
            LinearLayout cb = Form.cardBody(content, "NCrF श्रेयांक — इयत्ता " + J.s(data, "std"));
            for (String k : credits.keySet()) {
                if (k.equals("subjects")) continue;
                JsonElement v = credits.get(k);
                if (v.isJsonPrimitive()) cb.addView(Form.muted(this, k + ": " + v.getAsString()));
            }
            for (JsonObject cs : J.list(credits, "subjects")) {
                if (J.s(cs, "subject_key").equals(J.s(sub, "subject_key"))) cb.addView(Form.body(this, J.s(cs, "name_mr") + " — तास " + J.s(cs, "hours", J.s(cs, "annual_hours", "-")) + " · श्रेयांक " + J.s(cs, "credits", J.s(cs, "credit_points", "-"))));
            }
        }
        bottomButton("सारांश जतन करा", true, v -> {
            Native.P p = Native.P.of("stage_save").put("student_id", studentId).put("stage_action", "summary").put("subject_key", J.s(sub, "subject_key"))
                    .put("remark_mr", Form.val(remark)).put("credit_points_earned", Form.val(credit));
            for (Map.Entry<String, TextInputLayout> e : picks.entrySet()) {
                int i = lvOpts.indexOf(Form.val(e.getValue()));
                p.put("level_" + e.getKey(), i > 0 ? J.s(levels.get(i - 1), "key") : "");
            }
            showLoading(true);
            Native.post(Native.HPC, p, saved(true));
        });
    }
}
