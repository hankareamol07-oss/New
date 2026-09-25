package com.techguruji.smartschoolhub.ui.modules;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.Menu;
import android.view.MenuItem;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.databinding.ActivityModuleWebBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.util.HashMap;
import java.util.Map;

/**
 * ModuleWebActivity — opens any module URL in a native WebView.
 * Injects session cookies and auth headers so the user never has to log in again.
 * Includes professional PDF export and printing.
 */
public class ModuleWebActivity extends AppCompatActivity {

    public static final String EXTRA_TITLE = "module_title";
    public static final String EXTRA_URL   = "module_url";

    private static final String BASE_DOMAIN = "vijetaacademysangli.in";

    private ActivityModuleWebBinding binding;
    private String moduleTitle = "Document";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityModuleWebBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        String title = getIntent().getStringExtra(EXTRA_TITLE);
        String url   = getIntent().getStringExtra(EXTRA_URL);
        if (title != null && !title.isEmpty()) {
            moduleTitle = title;
        }

        // Toolbar
        setSupportActionBar(binding.toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle(moduleTitle);
        }

        setupWebView(url);
    }

    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        getMenuInflater().inflate(R.menu.menu_web, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        int itemId = item.getItemId();
        if (itemId == android.R.id.home) {
            onBackPressed();
            return true;
        } else if (itemId == R.id.btnPrint) {
            PdfPrintHelper.showPrintPdfDialog(this, binding.webView, moduleTitle);
            return true;
        } else if (itemId == R.id.btnShare) {
            shareCurrentUrl();
            return true;
        } else if (itemId == R.id.btnOpenBrowser) {
            openInBrowser(getIntent().getStringExtra(EXTRA_URL));
            return true;
        }
        return super.onOptionsItemSelected(item);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView(String url) {
        WebSettings settings = binding.webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setCacheMode(WebSettings.LOAD_DEFAULT);
        settings.setLoadWithOverviewMode(true);
        settings.setUseWideViewPort(true);
        settings.setSupportZoom(true);
        settings.setBuiltInZoomControls(true);
        settings.setDisplayZoomControls(false);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);

        // Pass session token as cookie so user does not need to login inside web modules
        SessionManager session = SessionManager.getInstance(this);
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            cookieManager.setAcceptThirdPartyCookies(binding.webView, true);
        }

        String targetUrl = "https://vijetaacademysangli.in/";
        String baseModuleUrl = "https://vijetaacademysangli.in/techguruji/";
        String token = session.getToken();
        int schoolId = session.getSchoolId();

        setCookie(cookieManager, targetUrl, "app_token", token);
        setCookie(cookieManager, targetUrl, "auth_token", token);
        setCookie(cookieManager, targetUrl, "token", token);
        setCookie(cookieManager, targetUrl, "school_id", String.valueOf(schoolId));
        setCookie(cookieManager, targetUrl, "user_id", String.valueOf(schoolId));
        setCookie(cookieManager, targetUrl, "is_logged_in", "1");

        setCookie(cookieManager, baseModuleUrl, "app_token", token);
        setCookie(cookieManager, baseModuleUrl, "auth_token", token);
        setCookie(cookieManager, baseModuleUrl, "token", token);
        setCookie(cookieManager, baseModuleUrl, "school_id", String.valueOf(schoolId));
        setCookie(cookieManager, baseModuleUrl, "user_id", String.valueOf(schoolId));
        setCookie(cookieManager, baseModuleUrl, "is_logged_in", "1");

        cookieManager.flush();

        Map<String, String> extraHeaders = new HashMap<>();
        if (token != null && !token.isEmpty()) {
            extraHeaders.put("Authorization", "Bearer " + token);
            extraHeaders.put("X-App-Token", token);
        }
        if (schoolId > 0) {
            extraHeaders.put("X-School-ID", String.valueOf(schoolId));
        }

        // Add Javascript Interface for seamless webpage print button support
        binding.webView.addJavascriptInterface(new WebPrintBridge(this, binding.webView, moduleTitle), "AndroidPrint");

        binding.webView.setDownloadListener((downloadUrl, userAgent, contentDisposition, mimetype, contentLength) -> {
            try {
                Intent i = new Intent(Intent.ACTION_VIEW);
                i.setData(Uri.parse(downloadUrl));
                startActivity(i);
            } catch (Exception e) {
                Snackbar.make(binding.getRoot(), "फाईल डाऊनलोड उघडता आली नाही.", Snackbar.LENGTH_SHORT).show();
            }
        });

        binding.webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String reqUrl = request.getUrl().toString();
                if (reqUrl.contains(BASE_DOMAIN)) {
                    return false;
                }
                openInBrowser(reqUrl);
                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                binding.progressBar.setVisibility(View.VISIBLE);
                binding.layoutError.setVisibility(View.GONE);
                injectPrintScript(view);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                binding.progressBar.setVisibility(View.GONE);
                if (view.getTitle() != null && !view.getTitle().isEmpty()) {
                    if (getSupportActionBar() != null) {
                        getSupportActionBar().setSubtitle(view.getTitle());
                    }
                }
                injectPrintScript(view);
            }

            @Override
            public void onReceivedError(WebView view, int errorCode,
                                        String description, String failingUrl) {
                binding.progressBar.setVisibility(View.GONE);
                binding.layoutError.setVisibility(View.VISIBLE);
                binding.btnRetry.setOnClickListener(v -> {
                    binding.layoutError.setVisibility(View.GONE);
                    if (url != null) {
                        binding.webView.loadUrl(url, extraHeaders);
                    } else {
                        binding.webView.reload();
                    }
                });
            }
        });

        binding.webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                binding.progressBar.setProgress(newProgress);
            }
        });

        if (url != null && !url.isEmpty()) {
            binding.webView.loadUrl(url, extraHeaders);
        }
    }

    private void setCookie(CookieManager manager, String url, String key, String value) {
        if (value != null && !value.isEmpty()) {
            manager.setCookie(url, key + "=" + value + "; Path=/; Domain=vijetaacademysangli.in; Secure; SameSite=Lax");
        }
    }

    private void shareCurrentUrl() {
        String currentUrl = binding.webView.getUrl();
        if (currentUrl != null) {
            Intent share = new Intent(Intent.ACTION_SEND);
            share.setType("text/plain");
            share.putExtra(Intent.EXTRA_TEXT, currentUrl);
            startActivity(Intent.createChooser(share, "शेअर करा"));
        }
    }

    private void openInBrowser(String url) {
        try {
            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
            startActivity(intent);
        } catch (Exception e) {
            Snackbar.make(binding.getRoot(), "ब्राउझर उघडता आला नाही.", Snackbar.LENGTH_SHORT).show();
        }
    }

    @Override
    public void onBackPressed() {
        if (binding.webView.canGoBack()) {
            binding.webView.goBack();
        } else {
            super.onBackPressed();
            overridePendingTransition(R.anim.slide_in_left, R.anim.slide_out_right);
        }
    }

    /**
     * Injects JavaScript to bridge webpage print buttons directly to Android's native print manager.
     */
    private void injectPrintScript(WebView view) {
        String js = "javascript:(function() {" +
                "  window.print = function() {" +
                "    if (window.AndroidPrint && window.AndroidPrint.print) {" +
                "      window.AndroidPrint.print();" +
                "    }" +
                "  };" +
                "  document.addEventListener('click', function(e) {" +
                "    var el = e.target;" +
                "    while (el && el !== document) {" +
                "      var text = (el.innerText || el.textContent || '').toLowerCase();" +
                "      var onclick = (el.getAttribute('onclick') || '').toLowerCase();" +
                "      var cls = (el.className || '').toString().toLowerCase();" +
                "      var id = (el.id || '').toString().toLowerCase();" +
                "      if (onclick.indexOf('print') !== -1 || id.indexOf('print') !== -1 || cls.indexOf('print') !== -1 || text.indexOf('print') !== -1 || text.indexOf('प्रिंट') !== -1) {" +
                "        if (window.AndroidPrint) {" +
                "          window.AndroidPrint.print();" +
                "          e.preventDefault();" +
                "          e.stopPropagation();" +
                "          return false;" +
                "        }" +
                "      }" +
                "      el = el.parentElement;" +
                "    }" +
                "  }, true);" +
                "})();";
        view.evaluateJavascript(js, null);
    }

    /**
     * JavaScript Bridge class exposed to web pages via `window.AndroidPrint`
     */
    public static class WebPrintBridge {
        private final AppCompatActivity activity;
        private final WebView webView;
        private final String defaultTitle;

        public WebPrintBridge(AppCompatActivity activity, WebView webView, String defaultTitle) {
            this.activity = activity;
            this.webView = webView;
            this.defaultTitle = defaultTitle;
        }

        @JavascriptInterface
        public void print() {
            activity.runOnUiThread(() -> {
                PdfPrintHelper.showPrintPdfDialog(activity, webView, defaultTitle);
            });
        }

        @JavascriptInterface
        public void printTitle(String customTitle) {
            activity.runOnUiThread(() -> {
                String title = (customTitle != null && !customTitle.isEmpty()) ? customTitle : defaultTitle;
                PdfPrintHelper.showPrintPdfDialog(activity, webView, title);
            });
        }
    }

    @Override
    protected void onDestroy() {
        if (binding.webView != null) {
            binding.webView.destroy();
        }
        super.onDestroy();
    }
}
