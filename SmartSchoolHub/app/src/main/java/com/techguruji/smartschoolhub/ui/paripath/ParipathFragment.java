package com.techguruji.smartschoolhub.ui.paripath;

import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.ParipathData;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.FragmentParipathBinding;
import com.techguruji.smartschoolhub.utils.PdfPrintHelper;
import com.techguruji.smartschoolhub.utils.SessionManager;

import java.text.SimpleDateFormat;
import java.util.Calendar;
import java.util.Locale;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * ParipathFragment — Daily school assembly screen with PDF export/print support.
 * Shows: Panchang, Suvichar, Dinvishesh, Subhashit, Prayer, National Anthem, Pledge.
 * Has prev/next date navigation.
 */
public class ParipathFragment extends Fragment {

    private FragmentParipathBinding binding;
    private Calendar currentDate = Calendar.getInstance();
    private final SimpleDateFormat apiDateFmt = new SimpleDateFormat("yyyy-MM-dd", Locale.getDefault());
    private ParipathData currentParipathData;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentParipathBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);

        setupDateNavigation();
        loadParipath(apiDateFmt.format(currentDate.getTime()));
    }

    private void setupDateNavigation() {
        binding.btnPrevDay.setOnClickListener(v -> {
            currentDate.add(Calendar.DAY_OF_YEAR, -1);
            loadParipath(apiDateFmt.format(currentDate.getTime()));
        });
        binding.btnNextDay.setOnClickListener(v -> {
            currentDate.add(Calendar.DAY_OF_YEAR, 1);
            loadParipath(apiDateFmt.format(currentDate.getTime()));
        });
        binding.btnToday.setOnClickListener(v -> {
            currentDate = Calendar.getInstance();
            loadParipath(apiDateFmt.format(currentDate.getTime()));
        });

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(() ->
                loadParipath(apiDateFmt.format(currentDate.getTime())));
    }

    private void loadParipath(String date) {
        showLoading(true);

        ApiClient.getInstance().getApiService().getParipath(date)
                .enqueue(new Callback<ParipathData>() {
                    @Override
                    public void onResponse(@NonNull Call<ParipathData> call,
                                           @NonNull Response<ParipathData> response) {
                        showLoading(false);
                        if (response.isSuccessful() && response.body() != null
                                && response.body().isSuccess()) {
                            currentParipathData = response.body();
                            bindData(currentParipathData);
                        } else {
                            showError("माहिती मिळवणे अयशस्वी.");
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ParipathData> call,
                                         @NonNull Throwable t) {
                        showLoading(false);
                        showError(getString(R.string.no_internet));
                    }
                });
    }

    private void bindData(ParipathData data) {
        // Header
        binding.tvMarathiDate.setText(data.getMarathiDate() != null ? data.getMarathiDate() : "");
        binding.tvDayName.setText(data.getDayName() != null ? data.getDayName() : "");

        // Panchang card
        ParipathData.Panchang p = data.getPanchang();
        if (p != null) {
            binding.tvHinduMonth.setText(p.getHinduMonth());
            binding.tvTithi.setText(p.getTithi());
            binding.tvNakshatra.setText(p.getNakshatra());
            binding.tvRitu.setText(p.getRitu());
            binding.tvPaksha.setText(p.getPaksha());
            binding.tvSunrise.setText("🌅 " + p.getSunrise());
            binding.tvSunset.setText("🌇 " + p.getSunset());
        }

        // Suvichar card
        String suvichar = data.getSuvichar();
        if (suvichar != null && !suvichar.isEmpty()) {
            binding.tvSuvichar.setText("\" " + suvichar + " \"");
            binding.tvSuvicharSource.setText("— " + safe(data.getSuvicharSource()));
            if (data.getSuvicharMeaning() != null && !data.getSuvicharMeaning().isEmpty()) {
                binding.tvSuvicharMeaning.setVisibility(View.VISIBLE);
                binding.tvSuvicharMeaning.setText(data.getSuvicharMeaning());
            } else {
                binding.tvSuvicharMeaning.setVisibility(View.GONE);
            }
        }

        // Dinvishesh
        String dinvishesh = data.getDinvishesh();
        if (dinvishesh != null && !dinvishesh.isEmpty()) {
            binding.cardDinvishesh.setVisibility(View.VISIBLE);
            binding.tvDinvishesh.setText(dinvishesh);
        } else {
            binding.cardDinvishesh.setVisibility(View.GONE);
        }

        // Subhashit
        if (data.getSubhashitText() != null && !data.getSubhashitText().isEmpty()) {
            binding.tvSubhashit.setText(data.getSubhashitText());
            binding.tvSubhashitMeaning.setText(safe(data.getSubhashitMeaning()));
        }

        // Prayer / National Anthem / Pledge (expandable sections)
        if (data.getPrayerText() != null) {
            binding.tvPrayer.setText(data.getPrayerText());
        }
        if (data.getNationalAnthem() != null) {
            binding.tvNationalAnthem.setText(data.getNationalAnthem());
        }
        if (data.getPledge() != null) {
            binding.tvPledge.setText(data.getPledge());
        }

        // Toggle expand/collapse
        binding.headerPrayer.setOnClickListener(v -> toggleSection(binding.tvPrayer));
        binding.headerAnthem.setOnClickListener(v -> toggleSection(binding.tvNationalAnthem));
        binding.headerPledge.setOnClickListener(v -> toggleSection(binding.tvPledge));
    }

    public void printParipath() {
        if (currentParipathData != null) {
            SessionManager session = SessionManager.getInstance(requireContext());
            String dateStr = apiDateFmt.format(currentDate.getTime());
            String html = PdfPrintHelper.generateParipathHtml(session, currentParipathData, dateStr);
            PdfPrintHelper.printHtml(requireActivity(), html, "Paripath_" + dateStr);
        }
    }

    private void toggleSection(View content) {
        if (content.getVisibility() == View.VISIBLE) {
            content.setVisibility(View.GONE);
        } else {
            content.setVisibility(View.VISIBLE);
        }
    }

    private void showLoading(boolean loading) {
        binding.swipeRefresh.setRefreshing(false);
        binding.shimmerLayout.setVisibility(loading ? View.VISIBLE : View.GONE);
        binding.scrollContent.setVisibility(loading ? View.GONE : View.VISIBLE);
        if (loading) binding.shimmerLayout.startShimmer();
        else binding.shimmerLayout.stopShimmer();
    }

    private void showError(String msg) {
        Snackbar.make(binding.getRoot(), msg, Snackbar.LENGTH_LONG)
                .setAction(R.string.retry, v ->
                        loadParipath(apiDateFmt.format(currentDate.getTime())))
                .show();
    }

    private String safe(String s) { return s != null ? s : ""; }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
