package com.techguruji.smartschoolhub.utils;

import android.app.Activity;
import android.content.Context;
import android.view.View;
import android.view.inputmethod.InputMethodManager;
import android.widget.ArrayAdapter;
import android.widget.AutoCompleteTextView;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;

import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Locale;

/** Small UI helpers shared by the native module screens. */
public final class UiUtils {

    public static final String[] GRADES = {"1", "2", "3", "4", "5", "6", "7", "8"};
    public static final String[] GRADE_LABELS = {
            "इयत्ता १ ली", "इयत्ता २ री", "इयत्ता ३ री", "इयत्ता ४ थी",
            "इयत्ता ५ वी", "इयत्ता ६ वी", "इयत्ता ७ वी", "इयत्ता ८ वी"};
    public static final String[] SECTIONS = {"A", "B", "C", "D"};
    public static final String[] SECTION_LABELS = {"तुकडी अ", "तुकडी ब", "तुकडी क", "तुकडी ड"};

    private static final SimpleDateFormat API_DATE = new SimpleDateFormat("yyyy-MM-dd", Locale.US);
    private static final SimpleDateFormat DISPLAY_DATE = new SimpleDateFormat("dd/MM/yyyy", Locale.US);
    private static final SimpleDateFormat LONG_DATE =
            new SimpleDateFormat("EEEE, dd MMMM yyyy", new Locale("mr", "IN"));

    private UiUtils() {}

    public static String gradeLabel(String grade) {
        for (int i = 0; i < GRADES.length; i++) {
            if (GRADES[i].equals(grade)) return GRADE_LABELS[i];
        }
        return (grade == null || grade.isEmpty()) ? "—" : "इयत्ता " + grade;
    }

    public static String sectionLabel(String section) {
        for (int i = 0; i < SECTIONS.length; i++) {
            if (SECTIONS[i].equalsIgnoreCase(section)) return SECTION_LABELS[i];
        }
        return (section == null || section.isEmpty()) ? "—" : "तुकडी " + section;
    }

    public static String apiDate(Calendar cal) {
        return API_DATE.format(cal.getTime());
    }

    public static String displayDate(Calendar cal) {
        return DISPLAY_DATE.format(cal.getTime());
    }

    public static String longDate(Calendar cal) {
        return LONG_DATE.format(cal.getTime());
    }

    public static String rupees(double amount) {
        return String.format(Locale.US, "₹%,.0f", amount);
    }

    public static String safe(String s) {
        return s == null ? "" : s;
    }

    public static String orDash(String s) {
        return (s == null || s.trim().isEmpty()) ? "—" : s;
    }

    public static String escapeHtml(String s) {
        if (s == null) return "";
        return s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
                .replace("\"", "&quot;").replace("\n", "<br/>");
    }

    public static void bindDropdown(AutoCompleteTextView view, String[] labels, int selected) {
        view.setAdapter(new ArrayAdapter<>(view.getContext(),
                R.layout.item_dropdown, labels));
        if (selected >= 0 && selected < labels.length) {
            view.setText(labels[selected], false);
        }
    }

    public static void snack(View root, String message) {
        Snackbar.make(root, message, Snackbar.LENGTH_LONG).show();
    }

    public static void snackRetry(View root, String message, View.OnClickListener retry) {
        Snackbar.make(root, message, Snackbar.LENGTH_LONG)
                .setAction(R.string.retry, retry).show();
    }

    public static void hideKeyboard(Activity activity) {
        View focus = activity.getCurrentFocus();
        if (focus == null) return;
        InputMethodManager imm =
                (InputMethodManager) activity.getSystemService(Context.INPUT_METHOD_SERVICE);
        if (imm != null) imm.hideSoftInputFromWindow(focus.getWindowToken(), 0);
    }

    /** Standard A4 print stylesheet used by all native report generators. */
    public static String printCss(String accentHex) {
        return "@page{size:A4;margin:14mm}"
                + "body{font-family:'Noto Sans Devanagari','Noto Sans',sans-serif;font-size:12.5px;color:#1e293b;margin:0}"
                + ".hdr{text-align:center;border-bottom:2.5px solid " + accentHex + ";padding-bottom:8px;margin-bottom:14px}"
                + ".hdr .school{font-size:20px;font-weight:700;color:" + accentHex + "}"
                + ".hdr .sub{font-size:12px;color:#475569;margin-top:3px}"
                + ".title{font-size:16px;font-weight:700;text-align:center;margin:10px 0 12px;text-decoration:underline}"
                + "table{width:100%;border-collapse:collapse;margin-top:8px}"
                + "th,td{border:1px solid #cbd5e1;padding:6px 7px;text-align:left;vertical-align:top}"
                + "th{background:#f1f5f9;font-weight:700}"
                + ".meta{display:flex;justify-content:space-between;font-size:12px;margin:6px 0}"
                + ".sig{display:flex;justify-content:space-between;margin-top:48px;font-size:12px}"
                + ".sig div{text-align:center;width:40%;border-top:1px solid #64748b;padding-top:6px}"
                + ".right{text-align:right}.center{text-align:center}"
                + ".muted{color:#64748b;font-size:11px}";
    }

    public static String printHeader(SessionManager session, String subtitle) {
        return "<div class='hdr'><div class='school'>" + escapeHtml(session.getDisplaySchoolName())
                + "</div><div class='sub'>UDISE: " + escapeHtml(orDash(session.getUdise()))
                + (session.getDistrict().isEmpty() ? "" : " | जिल्हा: " + escapeHtml(session.getDistrict()))
                + "</div><div class='sub'>" + escapeHtml(subtitle) + "</div></div>";
    }
}
