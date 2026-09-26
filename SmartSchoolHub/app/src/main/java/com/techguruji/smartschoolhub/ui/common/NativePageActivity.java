package com.techguruji.smartschoolhub.ui.common;

import android.content.Intent;
import android.os.Bundle;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.ProgressBar;

import androidx.annotation.Nullable;
import androidx.appcompat.app.AppCompatActivity;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.android.material.appbar.MaterialToolbar;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.android.material.snackbar.Snackbar;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.network.Native;
import com.techguruji.smartschoolhub.utils.Form;
import com.techguruji.smartschoolhub.utils.SessionManager;

/**
 * Base for every PHP-page-mirroring native screen: toolbar, optional filter bar,
 * scrolling content column, progress, bottom action bar, swipe-to-refresh.
 */
public abstract class NativePageActivity extends AppCompatActivity {

    protected MaterialToolbar toolbar;
    protected LinearLayout filterBar;
    protected LinearLayout content;
    protected LinearLayout bottomBar;
    protected ProgressBar progress;
    protected SwipeRefreshLayout swipe;
    protected SessionManager session;

    @Override
    protected void onCreate(@Nullable Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_native_base);
        session = SessionManager.getInstance(this);
        toolbar = findViewById(R.id.toolbar);
        filterBar = findViewById(R.id.filterBar);
        content = findViewById(R.id.content);
        bottomBar = findViewById(R.id.bottomBar);
        progress = findViewById(R.id.progress);
        swipe = findViewById(R.id.swipe);
        toolbar.setNavigationOnClickListener(v -> finish());
        swipe.setColorSchemeResources(R.color.primary);
        swipe.setOnRefreshListener(this::load);
        onReady(savedInstanceState);
        load();
    }

    protected abstract void onReady(@Nullable Bundle state);

    /** (Re)load data for the page. */
    protected abstract void load();

    protected void setTitle(String title, @Nullable String subtitle) {
        toolbar.setTitle(title);
        toolbar.setSubtitle(subtitle);
    }

    protected void showLoading(boolean on) {
        if (!on) swipe.setRefreshing(false);
        progress.setVisibility(on && !swipe.isRefreshing() ? View.VISIBLE : View.GONE);
    }

    protected void clearContent() {
        content.removeAllViews();
    }

    protected void snack(String m) {
        Snackbar.make(findViewById(R.id.root), m, Snackbar.LENGTH_LONG).show();
    }

    protected void error(String m) {
        showLoading(false);
        Snackbar.make(findViewById(R.id.root), m, Snackbar.LENGTH_INDEFINITE).setAction("पुन्हा", v -> load()).show();
    }

    protected void confirm(String title, String msg, Runnable yes) {
        new MaterialAlertDialogBuilder(this).setTitle(title).setMessage(msg)
                .setPositiveButton("होय", (d, w) -> yes.run()).setNegativeButton("रद्द", null).show();
    }

    protected void info(String title, String msg) {
        new MaterialAlertDialogBuilder(this).setTitle(title).setMessage(msg).setPositiveButton("ठीक", null).show();
    }

    protected MaterialButton bottomButton(String text, boolean filled, View.OnClickListener click) {
        bottomBar.setVisibility(View.VISIBLE);
        MaterialButton b = Form.button(this, text, filled, click);
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f);
        p.setMargins(Form.dp(this, 4), 0, Form.dp(this, 4), 0);
        b.setLayoutParams(p);
        bottomBar.addView(b);
        return b;
    }

    protected void clearBottom() {
        bottomBar.removeAllViews();
        bottomBar.setVisibility(View.GONE);
    }

    protected void filter(View v) {
        filterBar.setVisibility(View.VISIBLE);
        filterBar.addView(v);
    }

    protected void empty(String msg) {
        Form.cardBody(content, null).addView(Form.body(this, msg));
    }

    /** Standard save callback: toast the PHP message, then optionally reload. */
    protected Native.Cb saved(boolean reload) {
        return new Native.Cb() {
            @Override
            public void ok(JsonObject data) {
                showLoading(false);
                snack(Native.msg(data, "जतन झाले."));
                if (reload) load();
            }

            @Override
            public void fail(String message) {
                showLoading(false);
                snack(message);
            }
        };
    }

    protected int intExtra(String k, int def) {
        Intent i = getIntent();
        return i == null ? def : i.getIntExtra(k, def);
    }

    protected String strExtra(String k, String def) {
        Intent i = getIntent();
        String v = i == null ? null : i.getStringExtra(k);
        return v == null ? def : v;
    }
}
