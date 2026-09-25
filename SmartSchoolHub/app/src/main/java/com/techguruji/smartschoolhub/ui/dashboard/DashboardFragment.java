package com.techguruji.smartschoolhub.ui.dashboard;

import android.animation.ValueAnimator;
import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.view.animation.DecelerateInterpolator;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import androidx.navigation.Navigation;

import com.google.android.material.card.MaterialCardView;
import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.DashboardData;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.FragmentDashboardBinding;
import com.techguruji.smartschoolhub.ui.hajeri.HajeriActivity;
import com.techguruji.smartschoolhub.ui.modules.ModuleWebActivity;
import com.techguruji.smartschoolhub.ui.subscription.PlansActivity;
import com.techguruji.smartschoolhub.ui.tachan.TachanActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * DashboardFragment — Pro Material Design 3 Home screen showing:
 *  - Professional welcome banner with school name + suvichar
 *  - Animated Material 3 stat cards (students, HPC, CCE, teachers)
 *  - Interactive quick action cards (Paripath, HPC, CCE, Tachan, Hajeri, MDM, Fee, Exam)
 *  - Freemium upgrade banner
 */
public class DashboardFragment extends Fragment {

    private FragmentDashboardBinding binding;
    private SessionManager session;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentDashboardBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);
        session = SessionManager.getInstance(requireContext());

        populateFromSession();
        setupQuickActions();
        setupSwipeRefresh();
        loadDashboard();
    }

    /** Populate data instantly from saved session (no network wait) */
    private void populateFromSession() {
        binding.tvSchoolName.setText(session.getDisplaySchoolName());
        binding.tvUdise.setText("UDISE: " + session.getUdise());
        binding.tvTeacherName.setText("स्वागत, " + session.getTeacherName());

        // Freemium banner
        if (!session.isSubscribed()) {
            binding.bannerFreemium.setVisibility(View.VISIBLE);
            binding.btnUpgradePro.setOnClickListener(v ->
                    startActivity(new Intent(requireContext(), PlansActivity.class)));
        } else {
            binding.bannerFreemium.setVisibility(View.GONE);
        }
    }

    private void setupQuickActions() {
        String base = "https://vijetaacademysangli.in/techguruji/";

        setupCardClick(binding.cardParipath, "शालेय परिपाठ", base + "modules/paripath/index.php");
        setupCardClick(binding.cardHpc, "HPC प्रगती पत्रक", base + "hpc/index.php");
        setupCardClick(binding.cardCce, "CCE मूल्यमापन", base + "cce/index.php");
        setupCardClick(binding.cardTachan, "टाचण नियोजन", base + "modules/tachan/index.php");
        setupCardClick(binding.cardHajeri, "विद्यार्थी हजेरी", base + "modules/hajeri/index.php");
        setupCardClick(binding.cardMdm, "पोषण आहार (MDM)", base + "modules/mdm/dashboard.php");
        setupCardClick(binding.cardFee, "फी व्यवस्थापन", base + "modules/fee/index.php");
        setupCardClick(binding.cardExam, "प्रश्नपत्रिका जनरेटर", base + "modules/exam_paper/index.php");
    }

    private void setupCardClick(MaterialCardView card, String title, String url) {
        if (card == null) return;
        card.setOnClickListener(v -> {
            card.animate()
                    .scaleX(0.95f)
                    .scaleY(0.95f)
                    .setDuration(80)
                    .withEndAction(() -> {
                        card.animate()
                                .scaleX(1.0f)
                                .scaleY(1.0f)
                                .setDuration(120)
                                .withEndAction(() -> openModule(title, url))
                                .start();
                    })
                    .start();
        });
    }

    private void setupSwipeRefresh() {
        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::loadDashboard);
    }

    private void loadDashboard() {
        // Always show content immediately from saved session — no blank screen
        binding.shimmerLayout.setVisibility(View.GONE);
        binding.shimmerLayout.stopShimmer();
        binding.contentLayout.setVisibility(View.VISIBLE);
        binding.layoutError.setVisibility(View.GONE);

        // Fetch fresh stats from API
        ApiClient.getInstance().getApiService().getDashboard()
                .enqueue(new Callback<DashboardData>() {
                    @Override
                    public void onResponse(@NonNull Call<DashboardData> call,
                                           @NonNull Response<DashboardData> response) {
                        if (binding == null) return;
                        binding.swipeRefresh.setRefreshing(false);

                        if (response.isSuccessful() && response.body() != null
                                && response.body().isSuccess()) {
                            bindData(response.body());
                        } else {
                            showOfflineSnack("आकडेवारी लोड होऊ शकली नाही. स्वाइप करून पुन्हा प्रयत्न करा.");
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<DashboardData> call, @NonNull Throwable t) {
                        if (binding == null) return;
                        binding.swipeRefresh.setRefreshing(false);
                        showOfflineSnack(getString(R.string.no_internet));
                    }
                });
    }

    private void bindData(DashboardData data) {
        binding.tvSchoolName.setText(data.getDisplaySchoolName());
        binding.tvUdise.setText("UDISE: " + data.getUdiseCode());
        binding.tvAcademicYear.setText("शैक्षणिक वर्ष: " + data.getAcademicYear());

        // Today's suvichar
        if (data.getSuvichar() != null && !data.getSuvichar().isEmpty()) {
            binding.tvSuvichar.setText("\" " + data.getSuvichar() + " \"");
            binding.tvSuvicharSource.setText("— " + data.getSuvicharSource());
        }

        // Animated stat counters
        animateCounter(binding.tvStatStudents, 0, data.getStudentCount(), "");
        animateCounter(binding.tvStatHpc, 0, data.getHpcCompleted(), "/" + data.getHpcCount());
        animateCounter(binding.tvStatCce, 0, data.getCceEvaluatedCount(), "");
        animateCounter(binding.tvStatTeachers, 0, data.getTeacherCount(), "");

        // Panchang
        if (data.getTodayMarathi() != null) {
            binding.tvTodayDate.setText(data.getTodayMarathi() + " (" + data.getPanchangVar() + ")");
        }

        animateLayoutEntrance();
    }

    private void animateCounter(TextView textView, int startValue, int endValue, String suffix) {
        if (textView == null) return;
        if (endValue <= 0) {
            textView.setText("0" + (suffix != null ? suffix : ""));
            return;
        }
        ValueAnimator animator = ValueAnimator.ofInt(startValue, endValue);
        animator.setDuration(800);
        animator.setInterpolator(new DecelerateInterpolator());
        animator.addUpdateListener(animation -> {
            if (textView != null) {
                int value = (int) animation.getAnimatedValue();
                textView.setText(value + (suffix != null ? suffix : ""));
            }
        });
        animator.start();
    }

    private void animateLayoutEntrance() {
        if (binding == null) return;
        binding.contentLayout.setAlpha(0f);
        binding.contentLayout.setTranslationY(30f);
        binding.contentLayout.animate()
                .alpha(1f)
                .translationY(0f)
                .setDuration(400)
                .setInterpolator(new DecelerateInterpolator())
                .start();
    }

    private void showOfflineSnack(String message) {
        if (binding == null) return;
        Snackbar
                .make(binding.getRoot(), message, Snackbar.LENGTH_LONG)
                .setAction("↺ पुन्हा", v -> loadDashboard())
                .show();
    }

    private void openModule(String title, String url) {
        if (title != null && title.contains("हजेरी")) {
            Intent intent = new Intent(requireContext(), HajeriActivity.class);
            startActivity(intent);
            requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
            return;
        }
        if (title != null && title.contains("टाचण")) {
            Intent intent = new Intent(requireContext(), TachanActivity.class);
            startActivity(intent);
            requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
            return;
        }
        if (title != null && title.contains("HPC")) {
            try {
                Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                        .navigate(R.id.hpcFragment);
                return;
            } catch (Exception ignored) {}
        }
        if (title != null && title.contains("CCE")) {
            try {
                Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                        .navigate(R.id.cceFragment);
                return;
            } catch (Exception ignored) {}
        }
        if (title != null && title.contains("परिपाठ")) {
            try {
                Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                        .navigate(R.id.paripathFragment);
                return;
            } catch (Exception ignored) {}
        }
        if (title != null && title.contains("विद्यार्थी")) {
            try {
                Navigation.findNavController(requireActivity(), R.id.nav_host_fragment)
                        .navigate(R.id.studentsFragment);
                return;
            } catch (Exception ignored) {}
        }

        Intent intent = new Intent(requireContext(), ModuleWebActivity.class);
        intent.putExtra(ModuleWebActivity.EXTRA_TITLE, title);
        intent.putExtra(ModuleWebActivity.EXTRA_URL, url);
        startActivity(intent);
        requireActivity().overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
