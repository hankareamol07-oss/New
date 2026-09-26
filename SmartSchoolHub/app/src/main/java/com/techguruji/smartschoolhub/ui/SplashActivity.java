package com.techguruji.smartschoolhub.ui;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;

import androidx.appcompat.app.AppCompatActivity;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.ui.auth.LoginActivity;
import com.techguruji.smartschoolhub.utils.SessionManager;

/**
 * SplashActivity — brand splash, then routes to Login or Main.
 */
@SuppressLint("CustomSplashScreen")
public class SplashActivity extends AppCompatActivity {

    private static final long SPLASH_DELAY = 2000L; // 2 seconds

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_splash);

        new Handler(Looper.getMainLooper()).postDelayed(() -> {
            SessionManager session = SessionManager.getInstance(this);
            Intent intent;
            if (session.isLoggedIn()) {
                intent = new Intent(SplashActivity.this, MainActivity.class);
            } else {
                intent = new Intent(SplashActivity.this, LoginActivity.class);
            }
            startActivity(intent);
            finish();
            overridePendingTransition(R.anim.fade_in, R.anim.fade_out);
        }, SPLASH_DELAY);
    }
}
