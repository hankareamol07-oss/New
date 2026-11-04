package com.techguruji.smartschoolhub.utils;

import android.app.Activity;
import android.content.Context;
import android.print.PrintAttributes;
import android.print.PrintDocumentAdapter;
import android.print.PrintManager;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.techguruji.smartschoolhub.data.model.ParipathData;
import com.techguruji.smartschoolhub.data.model.StudentListResponse;

import java.util.List;

/**
 * PdfPrintHelper — Professional PDF Generation and Printing utility.
 * Offers options to Save as PDF or Print via Android PrintManager.
 */
public class PdfPrintHelper {

    /**
     * Shows a popup dialog offering "Save as PDF" or "Print Document".
     */
    public static void showPrintPdfDialog(Activity activity, WebView webView, String jobName) {
        if (activity == null || webView == null) return;

        CharSequence[] options = new CharSequence[]{
                "📄 PDF म्हणून डाउनलोड / सेव्ह करा (Save as PDF)",
                "🖨️ प्रिंटरवरून प्रिंट करा (Print Document)"
        };

        new MaterialAlertDialogBuilder(activity)
                .setTitle("प्रिंट व PDF पर्याय")
                .setItems(options, (dialog, which) -> {
                    printWebView(activity, webView, jobName);
                })
                .setNegativeButton("रद्द करा", null)
                .show();
    }

    /**
     * Shows chooser dialog for raw HTML content.
     */
    public static void showPrintPdfDialogForHtml(Activity activity, String htmlContent, String jobName) {
        if (activity == null || htmlContent == null) return;

        activity.runOnUiThread(() -> {
            try {
                WebView printWebView = new WebView(activity);
                printWebView.getSettings().setJavaScriptEnabled(true);
                printWebView.getSettings().setDomStorageEnabled(true);
                printWebView.setWebViewClient(new WebViewClient() {
                    @Override
                    public void onPageFinished(WebView view, String url) {
                        showPrintPdfDialog(activity, view, jobName);
                    }
                });

                printWebView.loadDataWithBaseURL("https://vijetaacademysangli.in/", htmlContent, "text/html", "UTF-8", null);
            } catch (Exception e) {
                e.printStackTrace();
                Toast.makeText(activity, "त्रुटी: " + e.getLocalizedMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    /**
     * Prints the current WebView directly via Android PrintManager.
     */
    public static void printWebView(Activity activity, WebView webView, String jobName) {
        if (activity == null || webView == null) return;

        activity.runOnUiThread(() -> {
            try {
                // Inject professional print CSS for clean A4 PDF styling
                String printCss = "(function() {" +
                        "var style = document.createElement('style');" +
                        "style.type = 'text/css';" +
                        "style.innerHTML = '@media print {" +
                        "  @page { size: A4 portrait; margin: 10mm; }" +
                        "  body { font-family: sans-serif !important; color: #000 !important; background: #fff !important; }" +
                        "  .no-print, nav, header, footer, .btn, .button, .navbar, .toolbar { display: none !important; }" +
                        "  table { width: 100% !important; border-collapse: collapse !important; margin: 10px 0; }" +
                        "  th, td { border: 1px solid #444 !important; padding: 6px 8px !important; font-size: 11pt !important; }" +
                        "  th { background-color: #f0f0f0 !important; font-weight: bold !important; }" +
                        "}';" +
                        "document.head.appendChild(style);" +
                        "})()";
                webView.evaluateJavascript(printCss, null);

                PrintManager printManager = (PrintManager) activity.getSystemService(Context.PRINT_SERVICE);
                if (printManager != null) {
                    String name = (jobName != null && !jobName.isEmpty()) ? jobName : "SmartSchool_Document";
                    PrintDocumentAdapter printAdapter = webView.createPrintDocumentAdapter(name);

                    PrintAttributes.Builder builder = new PrintAttributes.Builder();
                    builder.setMediaSize(PrintAttributes.MediaSize.ISO_A4);
                    builder.setResolution(new PrintAttributes.Resolution("pdf", "PDF", 300, 300));
                    builder.setMinMargins(PrintAttributes.Margins.NO_MARGINS);

                    printManager.print(name, printAdapter, builder.build());
                } else {
                    Toast.makeText(activity, "प्रिंट सेवा उपलब्ध नाही.", Toast.LENGTH_SHORT).show();
                }
            } catch (Exception e) {
                e.printStackTrace();
                Toast.makeText(activity, "प्रिंट त्रुटी: " + e.getLocalizedMessage(), Toast.LENGTH_SHORT).show();
            }
        });
    }

    /**
     * Prints raw HTML string by rendering inside a background WebView.
     */
    public static void printHtml(Activity activity, String htmlContent, String jobName) {
        if (activity == null || htmlContent == null) return;

        showPrintPdfDialogForHtml(activity, htmlContent, jobName);
    }

    /**
     * Generates a professional HTML letterhead document for Paripath (Daily Assembly).
     */
    public static String generateParipathHtml(SessionManager session, ParipathData data, String dateStr) {
        StringBuilder html = new StringBuilder();
        html.append("<!DOCTYPE html><html><head><meta charset='UTF-8'>")
            .append("<style>")
            .append("body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #222; }")
            .append(".header { text-align: center; border-bottom: 3px double #BF360C; padding-bottom: 12px; margin-bottom: 20px; }")
            .append(".school-name { font-size: 22pt; font-weight: bold; color: #BF360C; margin: 0; }")
            .append(".school-sub { font-size: 11pt; color: #555; margin-top: 4px; }")
            .append(".doc-title { font-size: 16pt; font-weight: bold; background: #FFF3E0; padding: 8px; text-align: center; border-radius: 6px; border: 1px solid #FFE0B2; margin: 15px 0; color: #D84315; }")
            .append(".section-card { border: 1px solid #E0E0E0; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px; background: #FAFAFA; }")
            .append(".section-title { font-weight: bold; font-size: 12pt; color: #BF360C; border-bottom: 1px solid #E0E0E0; padding-bottom: 4px; margin-bottom: 8px; }")
            .append(".quote { font-style: italic; font-size: 13pt; text-align: center; color: #37474F; padding: 10px; background: #FFFFFF; border-left: 4px solid #BF360C; margin: 10px 0; }")
            .append(".grid { display: flex; flex-wrap: wrap; gap: 10px; }")
            .append(".grid-item { flex: 1; min-width: 45%; background: #FFF; padding: 8px; border: 1px solid #DDD; border-radius: 4px; font-size: 10pt; }")
            .append(".footer { margin-top: 40px; display: flex; justify-content: space-between; font-size: 11pt; font-weight: bold; }")
            .append("</style></head><body>");

        // Header / Letterhead
        html.append("<div class='header'>")
            .append("<h1 class='school-name'>").append(session.getDisplaySchoolName()).append("</h1>")
            .append("<div class='school-sub'>UDISE: ").append(session.getUdise()).append(" | जिल्हा: ").append(session.getDistrict()).append("</div>")
            .append("</div>");

        // Document Title
        html.append("<div class='doc-title'>दैनिक शालेय परिपाठ  •  दिनांक: ").append(dateStr != null ? dateStr : "").append("</div>");

        if (data != null) {
            // Panchang
            if (data.getPanchang() != null) {
                ParipathData.Panchang p = data.getPanchang();
                html.append("<div class='section-card'>")
                    .append("<div class='section-title'>📅 आजचे पंचांग & तिथी</div>")
                    .append("<div class='grid'>")
                    .append("<div class='grid-item'><b>वार:</b> ").append(safe(data.getDayName())).append("</div>")
                    .append("<div class='grid-item'><b>मराठी महिना:</b> ").append(safe(p.getHinduMonth())).append("</div>")
                    .append("<div class='grid-item'><b>तिथी:</b> ").append(safe(p.getTithi())).append("</div>")
                    .append("<div class='grid-item'><b>नक्षत्र:</b> ").append(safe(p.getNakshatra())).append("</div>")
                    .append("<div class='grid-item'><b>सूर्योदय:</b> ").append(safe(p.getSunrise())).append(" | <b>सूर्यास्त:</b> ").append(safe(p.getSunset())).append("</div>")
                    .append("</div></div>");
            }

            // Suvichar
            if (data.getSuvichar() != null && !data.getSuvichar().isEmpty()) {
                html.append("<div class='section-card'>")
                    .append("<div class='section-title'>💡 आजचा विचार (सुविचार)</div>")
                    .append("<div class='quote'>\" ").append(data.getSuvichar()).append(" \"</div>");
                if (data.getSuvicharSource() != null) {
                    html.append("<div style='text-align:right; font-size:10pt; color:#666;'>— ").append(data.getSuvicharSource()).append("</div>");
                }
                html.append("</div>");
            }

            // Dinvishesh
            if (data.getDinvishesh() != null && !data.getDinvishesh().isEmpty()) {
                html.append("<div class='section-card'>")
                    .append("<div class='section-title'>📜 दिनविशेष</div>")
                    .append("<p style='font-size:11pt; line-height:1.5;'>").append(data.getDinvishesh()).append("</p>")
                    .append("</div>");
            }

            // Subhashit
            if (data.getSubhashitText() != null && !data.getSubhashitText().isEmpty()) {
                html.append("<div class='section-card'>")
                    .append("<div class='section-title'>📖 सुभाषित</div>")
                    .append("<p style='font-size:11pt; line-height:1.5;'>").append(data.getSubhashitText()).append("</p>");
                if (data.getSubhashitMeaning() != null && !data.getSubhashitMeaning().isEmpty()) {
                    html.append("<p style='font-size:10pt; color:#555;'><b>अर्थ:</b> ").append(data.getSubhashitMeaning()).append("</p>");
                }
                html.append("</div>");
            }
        }

        // Signature block
        html.append("<div class='footer'>")
            .append("<div>वर्ग शिक्षक सही: _______________</div>")
            .append("<div>मुख्याध्यापक सही व शिक्का: _______________</div>")
            .append("</div>");

        html.append("</body></html>");
        return html.toString();
    }

    /**
     * Generates a professional HTML letterhead document for Student List.
     */
    public static String generateStudentListHtml(SessionManager session, List<StudentListResponse.Student> students) {
        StringBuilder html = new StringBuilder();
        html.append("<!DOCTYPE html><html><head><meta charset='UTF-8'>")
            .append("<style>")
            .append("body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 20px; color: #222; }")
            .append(".header { text-align: center; border-bottom: 3px double #BF360C; padding-bottom: 12px; margin-bottom: 20px; }")
            .append(".school-name { font-size: 22pt; font-weight: bold; color: #BF360C; margin: 0; }")
            .append(".school-sub { font-size: 11pt; color: #555; margin-top: 4px; }")
            .append(".doc-title { font-size: 15pt; font-weight: bold; background: #FFF3E0; padding: 8px; text-align: center; border-radius: 6px; border: 1px solid #FFE0B2; margin: 15px 0; color: #D84315; }")
            .append("table { width: 100%; border-collapse: collapse; margin-top: 15px; }")
            .append("th, td { border: 1px solid #333; padding: 8px 10px; text-align: left; font-size: 11pt; }")
            .append("th { background-color: #BF360C; color: #FFF; font-weight: bold; }")
            .append("tr:nth-child(even) { background-color: #F9F9F9; }")
            .append(".footer { margin-top: 50px; display: flex; justify-content: space-between; font-size: 11pt; font-weight: bold; }")
            .append("</style></head><body>");

        // Header / Letterhead
        html.append("<div class='header'>")
            .append("<h1 class='school-name'>").append(session.getDisplaySchoolName()).append("</h1>")
            .append("<div class='school-sub'>UDISE: ").append(session.getUdise()).append(" | जिल्हा: ").append(session.getDistrict()).append("</div>")
            .append("</div>");

        // Document Title
        html.append("<div class='doc-title'>विद्यार्थी नोंदणी पट (एकूण विद्यार्थी: ").append(students != null ? students.size() : 0).append(")</div>");

        // Table
        html.append("<table><thead><tr>")
            .append("<th style='width:8%; text-align:center;'>अ.क्र.</th>")
            .append("<th style='width:12%;'>हजेरी क्र.</th>")
            .append("<th style='width:40%;'>विद्यार्थ्याचे नाव</th>")
            .append("<th style='width:15%;'>इयत्ता / तुकडी</th>")
            .append("<th style='width:10%;'>लिंग</th>")
            .append("<th style='width:15%;'>मोबाईल</th>")
            .append("</tr></thead><tbody>");

        if (students != null && !students.isEmpty()) {
            int idx = 1;
            for (StudentListResponse.Student s : students) {
                html.append("<tr>")
                    .append("<td style='text-align:center;'>").append(idx++).append("</td>")
                    .append("<td>").append(s.getRollNo() != null ? s.getRollNo() : "-").append("</td>")
                    .append("<td><b>").append(safe(s.getDisplayName())).append("</b></td>")
                    .append("<td>").append(safe(s.getGrade())).append(" ").append(safe(s.getSection())).append("</td>")
                    .append("<td>").append(safe(s.getGender())).append("</td>")
                    .append("<td>").append(safe(s.getPhone())).append("</td>")
                    .append("</tr>");
            }
        } else {
            html.append("<tr><td colspan='6' style='text-align:center;'>कोणतीही विद्यार्थी माहिती उपलब्ध नाही.</td></tr>");
        }

        html.append("</tbody></table>");

        // Footer / Stamp
        html.append("<div class='footer'>")
            .append("<div>तपासले असणाऱ्या शिक्षकाची सही: _______________</div>")
            .append("<div>मुख्याध्यापक सही व शिफारस: _______________</div>")
            .append("</div>");

        html.append("</body></html>");
        return html.toString();
    }

    private static String safe(String val) {
        return (val != null) ? val : "";
    }
}
