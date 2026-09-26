package com.techguruji.smartschoolhub.ui;

import android.os.Bundle;

import androidx.appcompat.app.AppCompatActivity;
import androidx.navigation.NavController;
import androidx.navigation.fragment.NavHostFragment;
import androidx.navigation.ui.NavigationUI;

import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.databinding.ActivityMainBinding;

/**
 * MainActivity — container for all bottom-nav fragments.
 * Hosts: Dashboard, Modules, Paripath, Students, Profile
 */
public class MainActivity extends AppCompatActivity {

    private ActivityMainBinding binding;
    private NavController navController;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityMainBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setupNavigation();
    }

    private void setupNavigation() {
        NavHostFragment navHostFragment = (NavHostFragment) getSupportFragmentManager()
                .findFragmentById(R.id.nav_host_fragment);

        if (navHostFragment != null) {
            navController = navHostFragment.getNavController();
            NavigationUI.setupWithNavController(binding.bottomNavigation, navController);

            // Re-select to avoid duplicate back-stack
            binding.bottomNavigation.setOnItemReselectedListener(item -> {
                // Do nothing — stay on current screen
            });
        }
    }

    @Override
    public boolean onSupportNavigateUp() {
        return navController.navigateUp() || super.onSupportNavigateUp();
    }

    @Override
    public void onBackPressed() {
        // If we are not on home, go home first
        if (navController != null
                && navController.getCurrentDestination() != null
                && navController.getCurrentDestination().getId() != R.id.dashboardFragment) {
            navController.navigate(R.id.dashboardFragment);
        } else {
            super.onBackPressed();
        }
    }
}
