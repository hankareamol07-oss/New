package com.techguruji.smartschoolhub.ui.auth;

import android.content.Intent;
import android.os.Bundle;
import android.text.TextUtils;
import android.view.View;

import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.snackbar.Snackbar;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.LoginRequest;
import com.techguruji.smartschoolhub.data.model.LoginResponse;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityLoginBinding;
import com.techguruji.smartschoolhub.ui.MainActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * LoginActivity — school login screen with email/phone + password.
 * Material Design 3 layout.
 */
public class LoginActivity extends AppCompatActivity {

    private ActivityLoginBinding binding;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityLoginBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupClickListeners();
    }

    private void setupClickListeners() {
        // Login button
        binding.btnLogin.setOnClickListener(v -> attemptLogin());

        // Register link
        binding.tvRegisterLink.setOnClickListener(v -> {
            startActivity(new Intent(this, RegisterActivity.class));
            overridePendingTransition(R.anim.slide_in_right, R.anim.slide_out_left);
        });

        // Toggle password visibility
        binding.tilPassword.setEndIconOnClickListener(v ->
                binding.tilPassword.setPasswordVisibilityToggleEnabled(true)
        );
    }

    private void attemptLogin() {
        String login = binding.etLogin.getText() != null
                ? binding.etLogin.getText().toString().trim() : "";
        String password = binding.etPassword.getText() != null
                ? binding.etPassword.getText().toString().trim() : "";

        // Validate inputs
        if (TextUtils.isEmpty(login)) {
            binding.tilLogin.setError("ईमेल किंवा मोबाईल क्रमांक आवश्यक आहे");
            return;
        }
        if (TextUtils.isEmpty(password)) {
            binding.tilPassword.setError("पासवर्ड आवश्यक आहे");
            return;
        }

        binding.tilLogin.setError(null);
        binding.tilPassword.setError(null);

        // Show loading
        setLoading(true);

        LoginRequest request = new LoginRequest(login, password);
        ApiClient.getInstance().getApiService().login(request)
                .enqueue(new Callback<LoginResponse>() {
                    @Override
                    public void onResponse(Call<LoginResponse> call, Response<LoginResponse> response) {
                        setLoading(false);
                        if (response.isSuccessful() && response.body() != null) {
                            LoginResponse body = response.body();
                            if (body.isSuccess() && body.getSchool() != null) {
                                // Save session
                                LoginResponse.SchoolData school = body.getSchool();
                                SessionManager.getInstance(LoginActivity.this).saveSession(
                                        body.getToken(),
                                        school.getId(),
                                        school.getName(),
                                        school.getNameMr(),
                                        school.getUdiseCode(),
                                        school.getEmail(),
                                        school.getPhone(),
                                        school.getPlanId(),
                                        school.getPlanName(),
                                        school.isSubscribed(),
                                        school.getSubscriptionEnd(),
                                        school.getName(),
                                        school.getDistrict()
                                );
                                goToDashboard();
                            } else {
                                showError(body.getMessage() != null
                                        ? body.getMessage()
                                        : "चुकीचा ईमेल/मोबाईल किंवा पासवर्ड");
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

    private void goToDashboard() {
        Intent intent = new Intent(this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        startActivity(intent);
        overridePendingTransition(R.anim.fade_in, R.anim.fade_out);
        finish();
    }

    private void setLoading(boolean loading) {
        binding.btnLogin.setEnabled(!loading);
        binding.progressBar.setVisibility(loading ? View.VISIBLE : View.GONE);
        binding.btnLogin.setText(loading ? "" : getString(R.string.login_btn));
    }

    private void showError(String message) {
        Snackbar.make(binding.getRoot(), message, Snackbar.LENGTH_LONG)
                .setBackgroundTint(getColor(R.color.error))
                .setTextColor(getColor(android.R.color.white))
                .show();
    }
}
