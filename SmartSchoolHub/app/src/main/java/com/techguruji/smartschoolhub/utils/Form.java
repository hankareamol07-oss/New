package com.techguruji.smartschoolhub.utils;

import android.content.Context;
import android.content.res.ColorStateList;
import android.graphics.Color;
import android.graphics.Typeface;
import android.text.InputType;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.AutoCompleteTextView;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.TableLayout;
import android.widget.TableRow;
import android.widget.TextView;

import androidx.core.content.ContextCompat;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;
import com.techguruji.smartschoolhub.R;

import java.util.List;

/**
 * Programmatic Material-3 form kit. The PHP module pages are large data-entry forms
 * (dozens of fields per page); building them from code keeps each native screen a
 * faithful mirror of its PHP page without hundreds of XML layouts.
 */
public final class Form {

    private Form() {}

    public interface OnPick {
        void pick(int index);
    }

    public static int dp(Context c, int dp) {
        return (int) TypedValue.applyDimension(TypedValue.COMPLEX_UNIT_DIP, dp, c.getResources().getDisplayMetrics());
    }

    public static LinearLayout.LayoutParams lp() {
        return new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
    }

    public static LinearLayout.LayoutParams lp(Context c, int top) {
        LinearLayout.LayoutParams p = lp();
        p.topMargin = dp(c, top);
        return p;
    }

    public static LinearLayout vertical(Context c) {
        LinearLayout l = new LinearLayout(c);
        l.setOrientation(LinearLayout.VERTICAL);
        l.setLayoutParams(lp());
        return l;
    }

    public static LinearLayout horizontal(Context c) {
        LinearLayout l = new LinearLayout(c);
        l.setOrientation(LinearLayout.HORIZONTAL);
        l.setGravity(Gravity.CENTER_VERTICAL);
        l.setLayoutParams(lp());
        return l;
    }

    /** Equal-weight cell inside a horizontal row. */
    public static <T extends View> T weight(T v, float w) {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, w);
        p.setMarginEnd(dp(v.getContext(), 6));
        v.setLayoutParams(p);
        return v;
    }

    // ───────────────────────── text ─────────────────────────

    public static TextView text(Context c, String s, int sp, boolean bold, int color) {
        TextView t = new TextView(c);
        t.setText(s);
        t.setTextSize(sp);
        t.setTextColor(color);
        if (bold) t.setTypeface(Typeface.DEFAULT_BOLD);
        t.setLayoutParams(lp());
        return t;
    }

    public static TextView title(Context c, String s) {
        return text(c, s, 17, true, ContextCompat.getColor(c, R.color.text_primary));
    }

    public static TextView label(Context c, String s) {
        TextView t = text(c, s, 13, true, ContextCompat.getColor(c, R.color.text_secondary));
        t.setLayoutParams(lp(c, 10));
        return t;
    }

    public static TextView body(Context c, String s) {
        TextView t = text(c, s, 14, false, ContextCompat.getColor(c, R.color.text_primary));
        t.setLayoutParams(lp(c, 4));
        return t;
    }

    public static TextView muted(Context c, String s) {
        TextView t = text(c, s, 12, false, ContextCompat.getColor(c, R.color.text_secondary));
        t.setLayoutParams(lp(c, 2));
        return t;
    }

    /** Coloured left-border section header like the PHP "section-title" blocks. */
    public static View sectionHeader(Context c, String s, String hex) {
        LinearLayout row = horizontal(c);
        row.setLayoutParams(lp(c, 18));
        View bar = new View(c);
        bar.setBackgroundColor(parse(hex, 0xFFBF360C));
        LinearLayout.LayoutParams bp = new LinearLayout.LayoutParams(dp(c, 4), dp(c, 22));
        bp.setMarginEnd(dp(c, 10));
        bar.setLayoutParams(bp);
        row.addView(bar);
        TextView t = text(c, s, 16, true, ContextCompat.getColor(c, R.color.text_primary));
        row.addView(t);
        return row;
    }

    public static int parse(String hex, int def) {
        try {
            return Color.parseColor(hex);
        } catch (Exception e) {
            return def;
        }
    }

    // ───────────────────────── cards ─────────────────────────

    public static MaterialCardView card(Context c) {
        MaterialCardView card = new MaterialCardView(c);
        card.setRadius(dp(c, 14));
        card.setCardElevation(dp(c, 1));
        card.setStrokeWidth(dp(c, 1));
        card.setStrokeColor(ContextCompat.getColor(c, R.color.card_stroke));
        card.setCardBackgroundColor(ContextCompat.getColor(c, R.color.card_bg));
        card.setLayoutParams(lp(c, 10));
        card.setUseCompatPadding(false);
        return card;
    }

    /** Card with a vertical padded body; returns the body to add children into. */
    public static LinearLayout cardBody(LinearLayout parent, String titleOrNull) {
        Context c = parent.getContext();
        MaterialCardView card = card(c);
        LinearLayout body = vertical(c);
        int p = dp(c, 14);
        body.setPadding(p, p, p, p);
        if (titleOrNull != null && !titleOrNull.isEmpty()) body.addView(title(c, titleOrNull));
        card.addView(body);
        parent.addView(card);
        return body;
    }

    /** Dashboard style tile: coloured icon square, title, subtitle, chevron. */
    public static MaterialCardView tile(Context c, String title, String subtitle, String hex, View.OnClickListener click) {
        MaterialCardView card = card(c);
        card.setClickable(true);
        card.setFocusable(true);
        card.setOnClickListener(click);
        LinearLayout row = horizontal(c);
        int p = dp(c, 14);
        row.setPadding(p, p, p, p);
        TextView badge = new TextView(c);
        badge.setText(title.isEmpty() ? "•" : title.substring(0, 1));
        badge.setTextSize(18);
        badge.setTypeface(Typeface.DEFAULT_BOLD);
        badge.setTextColor(Color.WHITE);
        badge.setGravity(Gravity.CENTER);
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(parse(hex, 0xFFBF360C));
        bg.setCornerRadius(dp(c, 12));
        badge.setBackground(bg);
        LinearLayout.LayoutParams bp = new LinearLayout.LayoutParams(dp(c, 46), dp(c, 46));
        bp.setMarginEnd(dp(c, 14));
        badge.setLayoutParams(bp);
        row.addView(badge);
        LinearLayout col = vertical(c);
        col.setLayoutParams(new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        col.addView(text(c, title, 15, true, ContextCompat.getColor(c, R.color.text_primary)));
        if (subtitle != null && !subtitle.isEmpty()) col.addView(muted(c, subtitle));
        row.addView(col);
        TextView chev = text(c, "›", 24, false, ContextCompat.getColor(c, R.color.text_hint));
        chev.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        row.addView(chev);
        card.addView(row);
        return card;
    }

    /** Small stat box (value over label) used in module dashboards. */
    public static View stat(Context c, String value, String label, String hex) {
        MaterialCardView card = card(c);
        card.setCardBackgroundColor(parse(hex, 0xFFF1F5F9));
        card.setStrokeWidth(0);
        LinearLayout col = vertical(c);
        int p = dp(c, 12);
        col.setPadding(p, p, p, p);
        col.setGravity(Gravity.CENTER);
        col.addView(text(c, value, 20, true, Color.WHITE));
        TextView l = text(c, label, 12, false, 0xEEFFFFFF);
        l.setGravity(Gravity.CENTER);
        col.addView(l);
        card.addView(col);
        LinearLayout.LayoutParams p2 = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        p2.setMargins(dp(c, 3), dp(c, 6), dp(c, 3), 0);
        card.setLayoutParams(p2);
        return card;
    }

    // ───────────────────────── inputs ─────────────────────────

    public static TextInputLayout input(Context c, String hint, String value, boolean multiline, boolean number) {
        TextInputLayout til = new TextInputLayout(c, null, com.google.android.material.R.attr.textInputOutlinedStyle);
        til.setHint(hint);
        til.setBoxCornerRadii(dp(c, 10), dp(c, 10), dp(c, 10), dp(c, 10));
        til.setLayoutParams(lp(c, 8));
        TextInputEditText et = new TextInputEditText(til.getContext());
        et.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        if (number) {
            et.setInputType(InputType.TYPE_CLASS_NUMBER | InputType.TYPE_NUMBER_FLAG_DECIMAL);
        } else if (multiline) {
            et.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES);
            et.setMinLines(2);
            et.setGravity(Gravity.TOP);
        } else {
            et.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES);
        }
        et.setTextSize(14);
        if (value != null) et.setText(value);
        til.addView(et);
        return til;
    }

    public static String val(TextInputLayout til) {
        return til.getEditText() == null || til.getEditText().getText() == null ? "" : til.getEditText().getText().toString().trim();
    }

    public static void setVal(TextInputLayout til, String v) {
        if (til.getEditText() != null) til.getEditText().setText(v == null ? "" : v);
    }

    /** Compact numeric field (marks, days) used in wide grids. */
    public static TextInputLayout numCell(Context c, String hint, String value, int widthDp) {
        TextInputLayout til = input(c, hint, value, false, true);
        til.setLayoutParams(new LinearLayout.LayoutParams(dp(c, widthDp), ViewGroup.LayoutParams.WRAP_CONTENT));
        if (til.getEditText() != null) {
            til.getEditText().setTextSize(13);
            til.getEditText().setPadding(dp(c, 8), dp(c, 12), dp(c, 8), dp(c, 12));
        }
        return til;
    }

    /** Exposed-dropdown; returns the layout, the chosen index is reported via callback. */
    public static TextInputLayout dropdown(Context c, String hint, List<String> options, int selected, OnPick pick) {
        TextInputLayout til = new TextInputLayout(c, null, com.google.android.material.R.attr.textInputOutlinedExposedDropdownMenuStyle);
        til.setHint(hint);
        til.setBoxCornerRadii(dp(c, 10), dp(c, 10), dp(c, 10), dp(c, 10));
        til.setLayoutParams(lp(c, 8));
        AutoCompleteTextView actv = new AutoCompleteTextView(til.getContext());
        actv.setLayoutParams(new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        actv.setInputType(InputType.TYPE_NULL);
        actv.setTextSize(14);
        actv.setAdapter(new ArrayAdapter<>(c, R.layout.item_dropdown, options));
        if (selected >= 0 && selected < options.size()) actv.setText(options.get(selected), false);
        actv.setOnItemClickListener((parent, view, position, id) -> {
            if (pick != null) pick.pick(position);
        });
        til.addView(actv);
        return til;
    }

    public static int dropdownIndex(TextInputLayout til, List<String> options) {
        String v = til.getEditText() == null || til.getEditText().getText() == null ? "" : til.getEditText().getText().toString();
        return options.indexOf(v);
    }

    public static void setDropdown(TextInputLayout til, String v) {
        if (til.getEditText() instanceof AutoCompleteTextView) ((AutoCompleteTextView) til.getEditText()).setText(v, false);
    }

    /** Single-choice chip row (semester, level, grade …). */
    public static ChipGroup choiceChips(Context c, List<String> labels, int selected, OnPick pick) {
        ChipGroup g = new ChipGroup(c);
        g.setSingleSelection(true);
        g.setSelectionRequired(true);
        g.setLayoutParams(lp(c, 6));
        for (int i = 0; i < labels.size(); i++) {
            Chip ch = new Chip(c, null, com.google.android.material.R.attr.chipStyle);
            ch.setText(labels.get(i));
            ch.setCheckable(true);
            ch.setId(View.generateViewId());
            ch.setTag(i);
            ch.setCheckedIconVisible(false);
            ch.setChipBackgroundColor(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.surface_variant)));
            ch.setChipStrokeWidth(dp(c, 1));
            ch.setChipStrokeColor(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.card_stroke)));
            if (i == selected) ch.setChecked(true);
            final int idx = i;
            ch.setOnCheckedChangeListener((btn, checked) -> {
                if (checked) {
                    ch.setChipBackgroundColor(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.primary_container)));
                    if (pick != null) pick.pick(idx);
                } else {
                    ch.setChipBackgroundColor(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.surface_variant)));
                }
            });
            if (i == selected) ch.setChipBackgroundColor(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.primary_container)));
            g.addView(ch);
        }
        return g;
    }

    public static int checkedIndex(ChipGroup g) {
        for (int i = 0; i < g.getChildCount(); i++) {
            View v = g.getChildAt(i);
            if (v instanceof Chip && ((Chip) v).isChecked()) return (int) v.getTag();
        }
        return -1;
    }

    /** Multi-select chips; tags carry the given values. */
    public static ChipGroup multiChips(Context c, List<String> labels, List<Boolean> checked) {
        ChipGroup g = new ChipGroup(c);
        g.setLayoutParams(lp(c, 6));
        for (int i = 0; i < labels.size(); i++) {
            Chip ch = new Chip(c, null, com.google.android.material.R.attr.chipStyle);
            ch.setText(labels.get(i));
            ch.setCheckable(true);
            ch.setTag(i);
            ch.setId(View.generateViewId());
            ch.setChecked(checked != null && i < checked.size() && checked.get(i));
            g.addView(ch);
        }
        return g;
    }

    /** Multi-select chips; {@code selectedIdx} lists the indexes that start checked. */
    public static ChipGroup multiChipsSel(Context c, List<String> labels, List<Integer> selectedIdx) {
        java.util.List<Boolean> b = new java.util.ArrayList<>();
        for (int i = 0; i < labels.size(); i++) b.add(selectedIdx != null && selectedIdx.contains(i));
        return multiChips(c, labels, b);
    }

    public static java.util.List<Integer> checkedIndexes(ChipGroup g) {
        java.util.List<Integer> out = new java.util.ArrayList<>();
        for (int i = 0; i < g.getChildCount(); i++) {
            View v = g.getChildAt(i);
            if (v instanceof Chip && ((Chip) v).isChecked()) out.add((Integer) v.getTag());
        }
        return out;
    }

    public static MaterialButton button(Context c, String text, boolean filled, View.OnClickListener click) {
        MaterialButton b = new MaterialButton(c, null, filled
                ? com.google.android.material.R.attr.materialButtonStyle
                : com.google.android.material.R.attr.materialButtonOutlinedStyle);
        b.setText(text);
        b.setAllCaps(false);
        b.setCornerRadius(dp(c, 12));
        b.setLayoutParams(lp(c, 10));
        b.setOnClickListener(click);
        return b;
    }

    public static MaterialButton tonal(Context c, String text, View.OnClickListener click) {
        MaterialButton b = new MaterialButton(c, null, com.google.android.material.R.attr.materialButtonOutlinedStyle);
        b.setBackgroundTintList(ColorStateList.valueOf(ContextCompat.getColor(c, R.color.secondary_container)));
        b.setStrokeWidth(0);
        b.setText(text);
        b.setAllCaps(false);
        b.setCornerRadius(dp(c, 12));
        b.setLayoutParams(lp(c, 10));
        b.setOnClickListener(click);
        return b;
    }

    // ───────────────────────── tables ─────────────────────────

    /** Horizontally scrollable bordered table with a bold header row. */
    public static HorizontalScrollView table(Context c, List<String> headers, List<List<String>> rows) {
        HorizontalScrollView sv = new HorizontalScrollView(c);
        sv.setLayoutParams(lp(c, 8));
        TableLayout t = new TableLayout(c);
        t.setBackgroundColor(ContextCompat.getColor(c, R.color.card_stroke));
        TableRow h = new TableRow(c);
        for (String s : headers) h.addView(cell(c, s, true));
        t.addView(h);
        for (List<String> r : rows) {
            TableRow tr = new TableRow(c);
            for (String s : r) tr.addView(cell(c, s, false));
            t.addView(tr);
        }
        sv.addView(t);
        return sv;
    }

    public static TextView cell(Context c, String s, boolean head) {
        TextView tv = new TextView(c);
        tv.setText(s == null ? "" : s);
        tv.setTextSize(12);
        tv.setTextColor(ContextCompat.getColor(c, R.color.text_primary));
        if (head) tv.setTypeface(Typeface.DEFAULT_BOLD);
        tv.setBackgroundColor(head ? ContextCompat.getColor(c, R.color.surface_variant) : Color.WHITE);
        tv.setPadding(dp(c, 8), dp(c, 6), dp(c, 8), dp(c, 6));
        TableRow.LayoutParams p = new TableRow.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.MATCH_PARENT);
        p.setMargins(dp(c, 1), dp(c, 1), 0, 0);
        tv.setLayoutParams(p);
        tv.setMaxWidth(dp(c, 220));
        return tv;
    }

    /** Pill badge with a background colour. */
    public static TextView badge(Context c, String s, String hex) {
        TextView tv = new TextView(c);
        tv.setText(s);
        tv.setTextSize(11);
        tv.setTypeface(Typeface.DEFAULT_BOLD);
        tv.setTextColor(Color.WHITE);
        tv.setPadding(dp(c, 8), dp(c, 3), dp(c, 8), dp(c, 3));
        android.graphics.drawable.GradientDrawable bg = new android.graphics.drawable.GradientDrawable();
        bg.setColor(parse(hex, 0xFF64748B));
        bg.setCornerRadius(dp(c, 20));
        tv.setBackground(bg);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
        p.setMarginEnd(dp(c, 6));
        tv.setLayoutParams(p);
        return tv;
    }

    public static View divider(Context c) {
        View v = new View(c);
        v.setBackgroundColor(ContextCompat.getColor(c, R.color.divider));
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(c, 1));
        p.topMargin = dp(c, 10);
        p.bottomMargin = dp(c, 4);
        v.setLayoutParams(p);
        return v;
    }
}
