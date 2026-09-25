package com.techguruji.smartschoolhub.ui.profile;

import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
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
import com.techguruji.smartschoolhub.BuildConfig;
import com.techguruji.smartschoolhub.ui.classes.ClassesActivity;
import com.techguruji.smartschoolhub.ui.subscription.PlansActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

/**
 * ProfileFragment — school profile, subscription status, settings, and logout.
 */
public class ProfileFragment extends Fragment {

    private FragmentProfileBinding binding;
    private SessionManager session;
    private static final String SUPPORT_PHONE = "9198220000";

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

        binding.rowSchoolSettings.setOnClickListener(v -> showSchoolInfo());
        binding.rowClassSettings.setOnClickListener(v ->
                startActivity(new Intent(requireContext(), ClassesActivity.class)));
        binding.rowChangePassword.setOnClickListener(v -> showChangePasswordInfo());
        binding.rowAbout.setOnClickListener(v -> showAbout());
        binding.rowHelp.setOnClickListener(v -> showHelp());

        // Logout
        binding.btnLogout.setOnClickListener(v -> showLogoutConfirm());
    }

    private void showSchoolInfo() {
        String info = "शाळा: " + session.getDisplaySchoolName()
                + "\nUDISE: " + UiUtils.orDash(session.getUdise())
                + "\nजिल्हा: " + UiUtils.orDash(session.getDistrict())
                + "\nईमेल: " + UiUtils.orDash(session.getEmail())
                + "\nफोन: " + UiUtils.orDash(session.getPhone())
                + "\nमुख्याध्यापक/शिक्षक: " + session.getTeacherName()
                + "\n\nयोजना: " + session.getPlanName()
                + (session.getSubscriptionEnd().isEmpty() ? "" : "\nवैधता: " + session.getSubscriptionEnd());
        new MaterialAlertDialogBuilder(requireContext())
                .setTitle("शाळा माहिती")
                .setMessage(info)
                .setPositiveButton(R.string.ok, null)
                .setNeutralButton("कॉपी", (d, w) -> {
                    ClipboardManager cm = (ClipboardManager) requireContext().getSystemService(Context.CLIPBOARD_SERVICE);
                    cm.setPrimaryClip(ClipData.newPlainText("school", info));
                    UiUtils.snack(binding.getRoot(), "कॉपी झाले");
                })
                .show();
    }

    private void showChangePasswordInfo() {
        new MaterialAlertDialogBuilder(requireContext())
                .setTitle("पासवर्ड बदला")
                .setMessage("सुरक्षेसाठी पासवर्ड बदल OTP पडताळणीसह ईमेलद्वारे केला जातो. "
                        + "नोंदणीकृत ईमेल: " + UiUtils.orDash(session.getEmail())
                        + "\n\n'लिंक पाठवा' दाबल्यावर पासवर्ड रीसेट लिंक ईमेलवर पाठवण्याची विनंती केली जाईल.")
                .setPositiveButton("लिंक पाठवा", (d, w) -> sendEmail(
                        "support@smartschoolhub.in",
                        "पासवर्ड रीसेट विनंती — " + session.getDisplaySchoolName(),
                        "UDISE: " + session.getUdise() + "\nईमेल: " + session.getEmail()))
                .setNegativeButton(R.string.cancel, null)
                .show();
    }

    private void showAbout() {
        new MaterialAlertDialogBuilder(requireContext())
                .setTitle("SmartSchoolHub")
                .setMessage("आवृत्ती " + BuildConfig.VERSION_NAME
                        + "\n\nमहाराष्ट्रातील शाळांसाठी संपूर्ण डिजिटल व्यवस्थापन — परिपाठ, HPC, CCE, टाचण, हजेरी, MDM, "
                        + "फी, प्रश्नपत्रिका, विद्यार्थी, वर्ग, बोनाफाईड व जनरल रजिस्टर.\n\n© TechGuruji")
                .setPositiveButton(R.string.ok, null)
                .show();
    }

    private void showHelp() {
        String[] items = {"📞 कॉल करा", "💬 WhatsApp", "✉️ ईमेल"};
        new MaterialAlertDialogBuilder(requireContext())
                .setTitle("मदत / सपोर्ट")
                .setItems(items, (d, which) -> {
                    if (which == 0) {
                        startActivity(new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + SUPPORT_PHONE)));
                    } else if (which == 1) {
                        startActivity(new Intent(Intent.ACTION_VIEW,
                                Uri.parse("https://wa.me/91" + SUPPORT_PHONE + "?text="
                                        + Uri.encode("नमस्कार, SmartSchoolHub मदत हवी आहे. UDISE: " + session.getUdise()))));
                    } else {
                        sendEmail("support@smartschoolhub.in", "SmartSchoolHub मदत — " + session.getDisplaySchoolName(), "");
                    }
                })
                .show();
    }

    private void sendEmail(String to, String subject, String body) {
        Intent i = new Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:" + to));
        i.putExtra(Intent.EXTRA_SUBJECT, subject);
        i.putExtra(Intent.EXTRA_TEXT, body);
        try {
            startActivity(i);
        } catch (ActivityNotFoundException e) {
            UiUtils.snack(binding.getRoot(), "ईमेल ॲप सापडले नाही");
        }
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
