package com.techguruji.smartschoolhub.ui.subscription;

import android.os.Bundle;

import androidx.appcompat.app.AppCompatActivity;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.databinding.ActivityPlansBinding;
import com.techguruji.smartschoolhub.ui.modules.ModuleWebActivity;
import android.content.Intent;

/**
 * PlansActivity — shows Premium subscription plans.
 * Opens the real plans page inside WebView.
 */
public class PlansActivity extends AppCompatActivity {

    private ActivityPlansBinding binding;
    private static final String PLANS_URL =
            "https://vijetaacademysangli.in/techguruji/subscription/plans.php";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPlansBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        if (getSupportActionBar() != null) {
            getSupportActionBar().setDisplayHomeAsUpEnabled(true);
            getSupportActionBar().setTitle("प्रीमियम योजना");
        }
        binding.toolbar.setNavigationOnClickListener(v -> onBackPressed());

        binding.btnViewPlans.setOnClickListener(v -> {
            Intent intent = new Intent(this, ModuleWebActivity.class);
            intent.putExtra(ModuleWebActivity.EXTRA_TITLE, "प्रीमियम योजना");
            intent.putExtra(ModuleWebActivity.EXTRA_URL, PLANS_URL);
            startActivity(intent);
        });
    }
}
