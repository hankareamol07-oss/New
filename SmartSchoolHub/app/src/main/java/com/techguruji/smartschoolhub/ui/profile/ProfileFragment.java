package com.techguruji.smartschoolhub.ui.profile;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;

import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.databinding.FragmentProfileBinding;
import com.techguruji.smartschoolhub.ui.auth.LoginActivity;
import com.techguruji.smartschoolhub.ui.modules.ModuleWebActivity;
import com.techguruji.smartschoolhub.ui.subscription.PlansActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;

/**
 * ProfileFragment — school profile, subscription status, settings, and logout.
 */
public class ProfileFragment extends Fragment {

    private FragmentProfileBinding binding;
    private SessionManager session;
    private static final String BASE = "https://vijetaacademysangli.in/techguruji/";

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater,
                             @Nullable ViewGroup container,
                             @Nullable Bundle savedInstanceState) {
        binding = FragmentProfileBinding.inflate(inflater, container, false);
        return binding.getRoot();
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        super.onViewCreated(view, savedInstanceState);
        session = SessionManager.getInstance(requireContext());
        populateProfile();
        setupActions();
    }

    private void populateProfile() {
        String schoolName = session.getDisplaySchoolName();
        binding.tvSchoolName.setText(schoolName);
        binding.tvUdise.setText("UDISE: " + session.getUdise());
        binding.tvEmail.setText(session.getEmail());
        binding.tvPhone.setText(session.getPhone());
        binding.tvDistrict.setText("जिल्हा: " + session.getDistrict());

        // Avatar initial
        if (!schoolName.isEmpty()) {
            binding.tvAvatar.setText(String.valueOf(schoolName.charAt(0)));
        }

        // Subscription status
        if (session.isSubscribed()) {
            binding.chipPlan.setText("✅ " + session.getPlanName());
            binding.chipPlan.setChipBackgroundColorResource(R.color.success_container);
            binding.chipPlan.setTextColor(requireContext().getColor(R.color.success));
            binding.bannerUpgrade.setVisibility(View.GONE);
        } else {
            binding.chipPlan.setText("मोफत योजना (५ विद्यार्थी)");
            binding.chipPlan.setChipBackgroundColorResource(R.color.warning_container);
            binding.chipPlan.setTextColor(requireContext().getColor(R.color.warning));
            binding.bannerUpgrade.setVisibility(View.VISIBLE);
        }
    }

    private void setupActions() {
        // Upgrade to Pro
        binding.btnUpgradePro.setOnClickListener(v ->
                startActivity(new Intent(requireContext(), PlansActivity.class)));

        binding.bannerUpgrade.setOnClickListener(v ->
                startActivity(new Intent(requireContext(), PlansActivity.class)));

        // School settings
        binding.rowSchoolSettings.setOnClickListener(v -> openModule(
                "शाळा सेटिंग्ज", BASE + "school/profile.php"));

        // Class settings
        binding.rowClassSettings.setOnClickListener(v -> openModule(
                "वर्ग सेटिंग्ज", BASE + "modules/classes/index.php"));

        // Change password
        binding.rowChangePassword.setOnClickListener(v -> openModule(
                "पासवर्ड बदला", BASE + "auth/change_password.php"));

        // About
        binding.rowAbout.setOnClickListener(v -> openModule(
                "आमच्याबद्दल", BASE + "?about=1"));

        // Help
        binding.rowHelp.setOnClickListener(v -> openModule(
                "मदत / सपोर्ट", BASE + "?help=1"));

        // Logout
        binding.btnLogout.setOnClickListener(v -> showLogoutConfirm());
    }

    private void openModule(String title, String url) {
        Intent intent = new Intent(requireContext(), ModuleWebActivity.class);
        intent.putExtra(ModuleWebActivity.EXTRA_TITLE, title);
        intent.putExtra(ModuleWebActivity.EXTRA_URL, url);
        startActivity(intent);
    }

    private void showLogoutConfirm() {
        new MaterialAlertDialogBuilder(requireContext())
                .setTitle("लॉगआउट करायचे का?")
                .setMessage("तुम्ही लॉगआउट केल्यावर पुन्हा लॉगिन करावे लागेल.")
                .setPositiveButton("होय, लॉगआउट", (dialog, which) -> doLogout())
                .setNegativeButton("रद्द", null)
                .show();
    }

    private void doLogout() {
        session.clearSession();
        Intent intent = new Intent(requireContext(), LoginActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        requireActivity().overridePendingTransition(R.anim.fade_in, R.anim.fade_out);
        requireActivity().finish();
    }

    @Override
    public void onDestroyView() {
        super.onDestroyView();
        binding = null;
    }
}
