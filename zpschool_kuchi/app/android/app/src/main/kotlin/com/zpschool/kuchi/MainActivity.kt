package com.zpschool.kuchi

import android.content.Context
import android.os.Handler
import android.os.Looper
import android.print.PrintAttributes
import android.print.PrintManager
import android.webkit.WebView
import android.webkit.WebViewClient
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    // Kept alive until the system print job has finished reading the page
    private var printWebView: WebView? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "techguruji/print").setMethodCallHandler { call, result ->
            if (call.method != "printHtml") {
                result.notImplemented()
                return@setMethodCallHandler
            }
            val html = call.argument<String>("html") ?: ""
            val baseUrl = call.argument<String>("baseUrl")
            val name = call.argument<String>("name") ?: "zpschool Kuchi"
            val isLandscape = call.argument<Boolean>("landscape") ?: false
            val paperSize = (call.argument<String>("paperSize") ?: "A4").trim().uppercase()

            val wv = WebView(this)
            wv.settings.javaScriptEnabled = true
            wv.settings.domStorageEnabled = true
            wv.settings.loadsImagesAutomatically = true
            wv.settings.useWideViewPort = true
            wv.settings.loadWithOverviewMode = true

            // Set desktop/print viewport dimensions to prevent responsive mobile squishing and extra page overflow
            val (widthPx, heightPx) = when (paperSize) {
                "A5" -> if (isLandscape) Pair(842, 595) else Pair(595, 842)
                "A3" -> if (isLandscape) Pair(1684, 1190) else Pair(1190, 1684)
                else -> if (isLandscape) Pair(1200, 850) else Pair(850, 1200)
            }
            wv.layout(0, 0, widthPx, heightPx)

            var done = false
            wv.webViewClient = object : WebViewClient() {
                override fun onPageFinished(view: WebView, url: String?) {
                    if (done) return
                    done = true
                    Handler(Looper.getMainLooper()).postDelayed({
                        try {
                            val pm = getSystemService(Context.PRINT_SERVICE) as PrintManager
                            val baseMedia = when (paperSize) {
                                "A5" -> PrintAttributes.MediaSize.ISO_A5
                                "A3" -> PrintAttributes.MediaSize.ISO_A3
                                else -> PrintAttributes.MediaSize.ISO_A4
                            }
                            val mediaSize = if (isLandscape) baseMedia.asLandscape() else baseMedia.asPortrait()
                            val attrs = PrintAttributes.Builder()
                                .setMediaSize(mediaSize)
                                .setResolution(PrintAttributes.Resolution("pdf", "PDF", 300, 300))
                                .setMinMargins(PrintAttributes.Margins.NO_MARGINS)
                                .build()

                            pm.print(name, view.createPrintDocumentAdapter(name), attrs)
                            result.success(true)
                        } catch (t: Throwable) {
                            result.error("PRINT_ERR", t.message, null)
                        }
                    }, 800)
                }
            }
            printWebView = wv
            wv.loadDataWithBaseURL(baseUrl, html, "text/html", "UTF-8", null)
        }
    }
}
