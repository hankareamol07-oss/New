package com.techguruji.smartschoolhub.ui.exam;

import android.os.Bundle;
import android.view.View;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.chip.Chip;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.ExamModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.data.network.ExamApiService;
import com.techguruji.smartschoolhub.databinding.ActivityExamPaperBinding;
import com.techguruji.smartschoolhub.databinding.ItemExamQtypeBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.Calendar;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * ExamPaperActivity — native question-paper builder on top of the exam_paper question bank:
 * standard → subject → chapters → per-type question counts → random pick → printable A4 paper.
 */
public class ExamPaperActivity extends AppCompatActivity {

    private ActivityExamPaperBinding binding;
    private ExamApiService api;

    private List<ExamModel.Standard> standards = new ArrayList<>();
    private List<ExamModel.Subject> subjects = new ArrayList<>();
    private List<ExamModel.Chapter> chapters = new ArrayList<>();
    private final List<ExamModel.Section> sections = new ArrayList<>();
    private ExamModel.Standard standard;
    private ExamModel.Subject subject;
    private int pending;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityExamPaperBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());
        api = ApiClient.getInstance().getExamApiService();

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        binding.ddStandard.setOnItemClickListener((p, v, pos, id) -> {
            standard = standards.get(pos);
            subject = null;
            binding.ddSubject.setText("", false);
            clearChapters();
            loadSubjects();
        });
        binding.ddSubject.setOnItemClickListener((p, v, pos, id) -> {
            subject = subjects.get(pos);
            loadChapters();
        });
        binding.btnAllChapters.setOnClickListener(v -> {
            boolean allChecked = true;
            for (int i = 0; i < binding.chipChapters.getChildCount(); i++) {
                if (!((Chip) binding.chipChapters.getChildAt(i)).isChecked()) { allChecked = false; break; }
            }
            for (int i = 0; i < binding.chipChapters.getChildCount(); i++) {
                ((Chip) binding.chipChapters.getChildAt(i)).setChecked(!allChecked);
            }
            loadTypes();
        });
        binding.fabGenerate.setOnClickListener(v -> generate());

        loadStandards();
    }

    private void busy(boolean b) {
        binding.progress.setVisibility(b ? View.VISIBLE : View.GONE);
        binding.fabGenerate.setEnabled(!b);
    }

    private void loadStandards() {
        busy(true);
        api.getStandards().enqueue(new Callback<List<ExamModel.Standard>>() {
            @Override
            public void onResponse(@NonNull Call<List<ExamModel.Standard>> call, @NonNull Response<List<ExamModel.Standard>> r) {
                busy(false);
                standards = r.isSuccessful() && r.body() != null ? r.body() : new ArrayList<>();
                String[] labels = new String[standards.size()];
                for (int i = 0; i < labels.length; i++) labels[i] = standards.get(i).label();
                UiUtils.bindDropdown(binding.ddStandard, labels, -1);
                if (standards.isEmpty()) {
                    UiUtils.snackRetry(binding.getRoot(), "प्रश्नसंच उपलब्ध नाही", v -> loadStandards());
                }
            }

            @Override
            public void onFailure(@NonNull Call<List<ExamModel.Standard>> call, @NonNull Throwable t) {
                busy(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> loadStandards());
            }
        });
    }

    private void loadSubjects() {
        if (standard == null) return;
        busy(true);
        api.getSubjects(standard.id).enqueue(new Callback<List<ExamModel.Subject>>() {
            @Override
            public void onResponse(@NonNull Call<List<ExamModel.Subject>> call, @NonNull Response<List<ExamModel.Subject>> r) {
                busy(false);
                subjects = r.isSuccessful() && r.body() != null ? r.body() : new ArrayList<>();
                String[] labels = new String[subjects.size()];
                for (int i = 0; i < labels.length; i++) {
                    labels[i] = subjects.get(i).name + "  (" + subjects.get(i).questionCount + " प्रश्न)";
                }
                UiUtils.bindDropdown(binding.ddSubject, labels, -1);
            }

            @Override
            public void onFailure(@NonNull Call<List<ExamModel.Subject>> call, @NonNull Throwable t) {
                busy(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> loadSubjects());
            }
        });
    }

    private void clearChapters() {
        chapters = new ArrayList<>();
        binding.chipChapters.removeAllViews();
        binding.btnAllChapters.setVisibility(View.GONE);
        binding.tvChapterHint.setText("प्रथम विषय निवडा");
        clearTypes();
    }

    private void clearTypes() {
        sections.clear();
        binding.layoutTypes.removeAllViews();
        binding.tvTypeHint.setVisibility(View.VISIBLE);
        updateSummary();
    }

    private void loadChapters() {
        if (subject == null) return;
        busy(true);
        api.getChapters(subject.id).enqueue(new Callback<List<ExamModel.Chapter>>() {
            @Override
            public void onResponse(@NonNull Call<List<ExamModel.Chapter>> call, @NonNull Response<List<ExamModel.Chapter>> r) {
                busy(false);
                clearChapters();
                chapters = r.isSuccessful() && r.body() != null ? r.body() : new ArrayList<>();
                binding.tvChapterHint.setText(chapters.isEmpty() ? "या विषयासाठी धडे उपलब्ध नाहीत"
                        : "पेपरमध्ये समाविष्ट करायचे धडे निवडा (" + chapters.size() + ")");
                binding.btnAllChapters.setVisibility(chapters.isEmpty() ? View.GONE : View.VISIBLE);
                for (ExamModel.Chapter c : chapters) {
                    Chip chip = new Chip(ExamPaperActivity.this, null, com.google.android.material.R.attr.chipStyle);
                    chip.setText(c.name + " (" + c.questionCount + ")");
                    chip.setCheckable(true);
                    chip.setTag(c.id);
                    chip.setOnCheckedChangeListener((b, checked) -> loadTypes());
                    binding.chipChapters.addView(chip);
                }
            }

            @Override
            public void onFailure(@NonNull Call<List<ExamModel.Chapter>> call, @NonNull Throwable t) {
                busy(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> loadChapters());
            }
        });
    }

    private List<Integer> selectedChapterIds() {
        List<Integer> ids = new ArrayList<>();
        for (int i = 0; i < binding.chipChapters.getChildCount(); i++) {
            Chip chip = (Chip) binding.chipChapters.getChildAt(i);
            if (chip.isChecked()) ids.add((Integer) chip.getTag());
        }
        return ids;
    }

    private void loadTypes() {
        List<Integer> ids = selectedChapterIds();
        if (ids.isEmpty()) {
            clearTypes();
            return;
        }
        busy(true);
        api.getQuestionTypes(ids).enqueue(new Callback<List<ExamModel.QuestionType>>() {
            @Override
            public void onResponse(@NonNull Call<List<ExamModel.QuestionType>> call, @NonNull Response<List<ExamModel.QuestionType>> r) {
                busy(false);
                if (!ids.equals(selectedChapterIds())) return;
                List<ExamModel.QuestionType> types = r.isSuccessful() && r.body() != null ? r.body() : new ArrayList<>();
                sections.clear();
                binding.layoutTypes.removeAllViews();
                binding.tvTypeHint.setVisibility(types.isEmpty() ? View.VISIBLE : View.GONE);
                for (ExamModel.QuestionType t : types) {
                    ExamModel.Section s = new ExamModel.Section(t, 0);
                    sections.add(s);
                    ItemExamQtypeBinding row = ItemExamQtypeBinding.inflate(getLayoutInflater(), binding.layoutTypes, false);
                    row.tvType.setText("प्र. " + UiUtils.safe(t.qno) + "  ·  " + t.typeLabel());
                    row.tvMeta.setText("प्रत्येकी " + t.marks + " गुण · उपलब्ध " + t.total);
                    row.btnMinus.setOnClickListener(v -> {
                        if (s.count > 0) { s.count--; row.tvCount.setText(String.valueOf(s.count)); updateSummary(); }
                    });
                    row.btnPlus.setOnClickListener(v -> {
                        if (s.count < t.total) { s.count++; row.tvCount.setText(String.valueOf(s.count)); updateSummary(); }
                        else UiUtils.snack(binding.getRoot(), "फक्त " + t.total + " प्रश्न उपलब्ध");
                    });
                    binding.layoutTypes.addView(row.getRoot());
                }
                updateSummary();
            }

            @Override
            public void onFailure(@NonNull Call<List<ExamModel.QuestionType>> call, @NonNull Throwable t) {
                busy(false);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> loadTypes());
            }
        });
    }

    private void updateSummary() {
        int q = 0, m = 0;
        for (ExamModel.Section s : sections) { q += s.count; m += s.count * s.type.marks; }
        binding.tvTotalQ.setText(String.valueOf(q));
        binding.tvTotalMarks.setText(String.valueOf(m));
    }

    private void generate() {
        List<Integer> ids = selectedChapterIds();
        List<ExamModel.Section> chosen = new ArrayList<>();
        for (ExamModel.Section s : sections) if (s.count > 0) chosen.add(s);
        if (standard == null || subject == null) {
            UiUtils.snack(binding.getRoot(), "इयत्ता व विषय निवडा");
            return;
        }
        if (ids.isEmpty() || chosen.isEmpty()) {
            UiUtils.snack(binding.getRoot(), "धडे व किमान एक प्रश्न प्रकार निवडा");
            return;
        }
        busy(true);
        pending = chosen.size();
        List<Integer> exclude = new ArrayList<>();
        for (ExamModel.Section s : chosen) {
            s.questions = null;
            api.randomQuestions(new ExamModel.RandomRequest(ids, s.type.type, s.type.qno, s.count, exclude))
                    .enqueue(new Callback<List<ExamModel.Question>>() {
                        @Override
                        public void onResponse(@NonNull Call<List<ExamModel.Question>> call, @NonNull Response<List<ExamModel.Question>> r) {
                            s.questions = r.isSuccessful() && r.body() != null ? r.body() : new ArrayList<>();
                            done(chosen);
                        }

                        @Override
                        public void onFailure(@NonNull Call<List<ExamModel.Question>> call, @NonNull Throwable t) {
                            s.questions = new ArrayList<>();
                            done(chosen);
                        }
                    });
        }
    }

    private void done(List<ExamModel.Section> chosen) {
        if (--pending > 0) return;
        busy(false);
        int total = 0;
        for (ExamModel.Section s : chosen) total += s.questions.size();
        if (total == 0) {
            UiUtils.snackRetry(binding.getRoot(), "प्रश्न मिळाले नाहीत. पुन्हा प्रयत्न करा.", v -> generate());
            return;
        }
        PdfPrintHelper.printHtml(this, buildHtml(chosen), "Paper_" + subject.name.replaceAll("\\s+", "_"));
    }

    private String buildHtml(List<ExamModel.Section> chosen) {
        SessionManager session = SessionManager.getInstance(this);
        String examName = binding.etExamName.getText() == null ? "" : binding.etExamName.getText().toString().trim();
        String duration = binding.etDuration.getText() == null ? "" : binding.etDuration.getText().toString().trim();
        int totalMarks = 0;
        for (ExamModel.Section s : chosen) totalMarks += s.marks();

        StringBuilder sb = new StringBuilder();
        sb.append("<html><head><meta charset='utf-8'><style>").append(UiUtils.printCss("#4527A0"))
                .append(".q{margin:6px 0 10px 0;page-break-inside:avoid}.qn{font-weight:700;margin-right:6px}")
                .append(".sec{margin-top:16px;font-weight:700;border-bottom:1px solid #cbd5e1;padding-bottom:4px;display:flex;justify-content:space-between}")
                .append(".passage{background:#f8fafc;border-left:3px solid #4527A0;padding:8px;margin:6px 0;font-size:12px}")
                .append("img{max-width:100%;max-height:220px}.key{page-break-before:always}")
                .append("</style></head><body>")
                .append(UiUtils.printHeader(session, UiUtils.escapeHtml(examName)))
                .append("<div class='meta'><span>").append(UiUtils.escapeHtml(standard.label()))
                .append("</span><span>विषय: ").append(UiUtils.escapeHtml(subject.name))
                .append("</span><span>गुण: ").append(totalMarks)
                .append("</span><span>वेळ: ").append(UiUtils.escapeHtml(duration)).append(" मि.")
                .append("</span><span>दिनांक: ").append(UiUtils.displayDate(Calendar.getInstance())).append("</span></div>");

        int secNo = 1;
        for (ExamModel.Section s : chosen) {
            if (s.questions.isEmpty()) continue;
            sb.append("<div class='sec'><span>प्र. ").append(secNo++).append(") ").append(s.type.typeLabel())
                    .append("</span><span>(").append(s.questions.size()).append(" × ").append(s.type.marks)
                    .append(" = ").append(s.marks()).append(" गुण)</span></div>");
            int n = 1;
            for (ExamModel.Question q : s.questions) {
                sb.append("<div class='q'>");
                if (q.passage != null && !q.passage.isEmpty()) sb.append("<div class='passage'>").append(q.passage).append("</div>");
                sb.append("<span class='qn'>").append(n++).append(".</span>").append(UiUtils.safe(q.markup));
                if (q.imageUrl != null) sb.append("<br/><img src='").append(UiUtils.escapeHtml(q.imageUrl)).append("'/>");
                sb.append("</div>");
            }
        }

        if (binding.swAnswerKey.isChecked()) {
            sb.append("<div class='key'><h3 style='color:#4527A0'>उत्तरसूची (Answer Key)</h3>");
            secNo = 1;
            for (ExamModel.Section s : chosen) {
                if (s.questions.isEmpty()) continue;
                sb.append("<div class='sec'><span>प्र. ").append(secNo++).append(") ").append(s.type.typeLabel()).append("</span></div>");
                int n = 1;
                for (ExamModel.Question q : s.questions) {
                    sb.append("<div class='q'><span class='qn'>").append(n++).append(".</span>")
                            .append(q.answerMarkup == null || q.answerMarkup.isEmpty() ? "<span class='muted'>—</span>" : q.answerMarkup)
                            .append("</div>");
                }
            }
            sb.append("</div>");
        }
        sb.append("</body></html>");
        return sb.toString();
    }
}
