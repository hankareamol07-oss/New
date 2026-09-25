package com.techguruji.smartschoolhub.ui.auth;

import android.content.Intent;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;

import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.LoginResponse;
import com.techguruji.smartschoolhub.data.model.RegisterRequest;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityRegisterBinding;
import com.techguruji.smartschoolhub.ui.MainActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * RegisterActivity — school free registration screen.
 */
public class RegisterActivity extends AppCompatActivity {

    private ActivityRegisterBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityRegisterBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        // Toolbar back button
        binding.toolbar.setNavigationOnClickListener(v -> onBackPressed());

        binding.btnRegister.setOnClickListener(v -> attemptRegister());
        binding.tvLoginLink.setOnClickListener(v -> {
            finish();
            overridePendingTransition(R.anim.slide_in_left, R.anim.slide_out_right);
        });
    }

    private void attemptRegister() {
        String name       = getText(binding.etName);
        String nameMr     = getText(binding.etNameMr);
        String email      = getText(binding.etEmail);
        String phone      = getText(binding.etPhone);
        String password   = getText(binding.etPassword);
        String confirm    = getText(binding.etConfirmPassword);
        String udise      = getText(binding.etUdise);
        String district   = getText(binding.etDistrict);
        String taluka     = getText(binding.etTaluka);
        String village    = getText(binding.etVillage);
        String pinCode    = getText(binding.etPinCode);

        // Validation
        boolean hasError = false;
        if (TextUtils.isEmpty(name)) {
            binding.tilName.setError("शाळेचे नाव आवश्यक आहे"); hasError = true;
        } else { binding.tilName.setError(null); }

        if (TextUtils.isEmpty(email) || !android.util.Patterns.EMAIL_ADDRESS.matcher(email).matches()) {
            binding.tilEmail.setError("वैध ईमेल आवश्यक आहे"); hasError = true;
        } else { binding.tilEmail.setError(null); }

        if (password.length() < 6) {
            binding.tilPassword.setError("पासवर्ड किमान ६ अक्षरे असावा"); hasError = true;
        } else { binding.tilPassword.setError(null); }

        if (!password.equals(confirm)) {
            binding.tilConfirmPassword.setError("पासवर्ड जुळत नाही"); hasError = true;
        } else { binding.tilConfirmPassword.setError(null); }

        if (hasError) return;

        setLoading(true);

        RegisterRequest request = new RegisterRequest(name, nameMr, email, phone,
                password, udise, district, taluka, village, pinCode);

        ApiClient.getInstance().getApiService().register(request)
                .enqueue(new Callback<LoginResponse>() {
                    @Override
                    public void onResponse(Call<LoginResponse> call, Response<LoginResponse> response) {
                        setLoading(false);
                        if (response.isSuccessful() && response.body() != null) {
                            LoginResponse body = response.body();
                            if (body.isSuccess() && body.getSchool() != null) {
                                LoginResponse.SchoolData school = body.getSchool();
                                SessionManager.getInstance(RegisterActivity.this).saveSession(
                                        body.getToken(), school.getId(), school.getName(),
                                        school.getNameMr(), school.getUdiseCode(), school.getEmail(),
                                        school.getPhone(), school.getPlanId(), school.getPlanName(),
                                        school.isSubscribed(), school.getSubscriptionEnd(),
                                        school.getName(), school.getDistrict()
                                );
                                goToDashboard();
                            } else {
                                showError(body.getMessage() != null ? body.getMessage()
                                        : "नोंदणी अयशस्वी. पुन्हा प्रयत्न करा.");
                            }
                        } else {
                            showError("सर्व्हर त्रुटी. पुन्हा प्रयत्न करा.");
                        }
                    }

                    @Override
                    public void onFailure(Call<LoginResponse> call, Throwable t) {
                        setLoading(false);
                        showError("इंटरनेट कनेक्शन नाही. कृपया तपासा.");
                    }
                });
    }

    private String getText(com.google.android.material.textfield.TextInputEditText et) {
        return et.getText() != null ? et.getText().toString().trim() : "";
    }

    private void goToDashboard() {
        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        overridePendingTransition(R.anim.fade_in, R.anim.fade_out);
        finish();
    }

    private void setLoading(boolean loading) {
        binding.btnRegister.setEnabled(!loading);
        binding.progressBar.setVisibility(loading ? View.VISIBLE : View.GONE);
        binding.btnRegister.setText(loading ? "" : getString(R.string.register_btn));
    }

    private void showError(String message) {
        Snackbar.make(binding.getRoot(), message, Snackbar.LENGTH_LONG)
                .setBackgroundTint(getColor(R.color.error))
                .setTextColor(getColor(android.R.color.white))
                .show();
    }
}
