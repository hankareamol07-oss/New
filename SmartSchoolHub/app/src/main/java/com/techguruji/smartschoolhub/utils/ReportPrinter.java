package com.techguruji.smartschoolhub.utils;

import android.app.Activity;
import android.content.Context;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.annotation.NonNull;

import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.google.gson.JsonObject;
import com.techguruji.smartschoolhub.data.network.ApiClient;

import java.util.HashMap;
import java.util.Map;

import okhttp3.ResponseBody;
import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Prints / saves-as-PDF the *exact* report the PHP app prints (निकालपत्रक, नोंदवही, प्रगती पुस्तक,
 * HPC १९-पानी, टाचण …). The HTML is fetched with the bearer token from api/report.php and handed to
 * Android's print framework; the user never sees a web page — only the system print/PDF sheet.
 */
public final class ReportPrinter {

    private static final String BASE = "https://vijetaacademysangli.in/techguruji/";
    private static WebView renderer;

    private ReportPrinter() {}

    public static Map<String, String> params(JsonObject printSpec) {
        Map<String, String> m = new HashMap<>();
        if (printSpec == null) return m;
        m.put("page", J.s(printSpec, "page"));
        JsonObject p = J.o(printSpec, "params");
        for (String k : p.keySet()) m.put(k, J.s(p, k));
        return m;
    }

    public static void print(Activity a, String page, Map<String, String> params, String jobName) {
        Map<String, String> q = new HashMap<>(params);
        q.put("page", page);
        print(a, q, jobName);
    }

    public static void print(Activity a, JsonObject printSpec, String jobName) {
        print(a, params(printSpec), jobName);
    }

    public static void print(Activity a, Map<String, String> query, String jobName) {
        Toast.makeText(a, "अहवाल तयार होत आहे…", Toast.LENGTH_SHORT).show();
        ApiClient.getInstance().getNativeApi().report(query).enqueue(new Callback<ResponseBody>() {
            @Override
            public void onResponse(@NonNull Call<ResponseBody> call, @NonNull Response<ResponseBody> r) {
                try {
                    String body = r.body() != null ? r.body().string() : (r.errorBody() != null ? r.errorBody().string() : "");
                    if (!r.isSuccessful() || body.trim().startsWith("{")) {
                        String msg = "अहवाल लोड झाला नाही (" + r.code() + ")";
                        try {
                            JsonObject o = com.google.gson.JsonParser.parseString(body).getAsJsonObject();
                            msg = J.s(o, "message", msg);
                        } catch (Exception ignored) {
                        }
                        new MaterialAlertDialogBuilder(a).setTitle("अहवाल").setMessage(msg).setPositiveButton("ठीक", null).show();
                        return;
                    }
                    render(a, body, jobName);
                } catch (Exception e) {
                    Toast.makeText(a, "त्रुटी: " + e.getLocalizedMessage(), Toast.LENGTH_LONG).show();
                }
            }

            @Override
            public void onFailure(@NonNull Call<ResponseBody> call, @NonNull Throwable t) {
                Toast.makeText(a, "नेटवर्क त्रुटी: " + t.getLocalizedMessage(), Toast.LENGTH_LONG).show();
            }
        });
    }

    private static void render(Activity a, String html, String jobName) {
        a.runOnUiThread(() -> {
            WebView wv = new WebView(a);
            renderer = wv;
            wv.getSettings().setJavaScriptEnabled(true);
            wv.getSettings().setLoadWithOverviewMode(true);
            wv.getSettings().setUseWideViewPort(true);
            wv.setWebViewClient(new WebViewClient() {
                private boolean done;

                @Override
                public void onPageFinished(WebView view, String url) {
                    if (done) return;
                    done = true;
                    view.postDelayed(() -> {
                        PrintManager pm = (PrintManager) a.getSystemService(Context.PRINT_SERVICE);
                        if (pm == null) return;
                        PrintDocumentAdapter adapter = view.createPrintDocumentAdapter(jobName);
                        PrintAttributes attrs = new PrintAttributes.Builder()
                                .setMediaSize(PrintAttributes.MediaSize.ISO_A4)
                                .setColorMode(PrintAttributes.COLOR_MODE_COLOR)
                                .setMinMargins(PrintAttributes.Margins.NO_MARGINS)
                                .build();
                        pm.print(jobName, adapter, attrs);
                    }, 600);
                }
            });
            String css = "<style>@media print{.no-print,.noprint,.btn,button,nav,.navbar,.topbar,.toolbar{display:none!important}}</style>";
            String page = html.contains("</head>") ? html.replace("</head>", css + "</head>") : css + html;
            wv.loadDataWithBaseURL(BASE, page, "text/html", "UTF-8", null);
        });
    }
}
