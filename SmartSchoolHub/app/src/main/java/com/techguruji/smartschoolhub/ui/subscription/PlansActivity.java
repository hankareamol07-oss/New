package com.techguruji.smartschoolhub.ui.subscription;

import android.content.Intent;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.dialog.MaterialAlertDialogBuilder;
import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.PlanModel;
import com.techguruji.smartschoolhub.data.network.ApiClient;
import com.techguruji.smartschoolhub.databinding.ActivityPlansBinding;
import com.techguruji.smartschoolhub.databinding.ItemPlanBinding;
import com.techguruji.smartschoolhub.utils.SessionManager;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * PlansActivity — native subscription plan comparison. Payment itself is completed
 * through the school's Razorpay checkout page in the device browser.
 */
public class PlansActivity extends AppCompatActivity {

    private ActivityPlansBinding binding;
    private final List<PlanModel.Plan> plans = new ArrayList<>();
    private String checkoutUrl = "https://vijetaacademysangli.in/techguruji/subscription/checkout.php";
    private final PlanAdapter adapter = new PlanAdapter();

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        binding = ActivityPlansBinding.inflate(getLayoutInflater());
        setContentView(binding.getRoot());

        setSupportActionBar(binding.toolbar);
        binding.toolbar.setNavigationOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());

        SessionManager session = SessionManager.getInstance(this);
        binding.tvCurrentPlan.setText(session.getPlanName());
        String end = session.getSubscriptionEnd();
        binding.tvValidity.setText(end == null || end.isEmpty() ? "" : "वैधता: " + end);

        binding.rvPlans.setLayoutManager(new LinearLayoutManager(this));
        binding.rvPlans.setAdapter(adapter);

        binding.emptyState.tvEmptyIcon.setText("💳");
        binding.emptyState.tvEmptyTitle.setText("योजना लोड होऊ शकल्या नाहीत");
        binding.emptyState.tvEmptyHint.setText("खाली ओढून पुन्हा प्रयत्न करा.");

        binding.swipeRefresh.setColorSchemeResources(R.color.primary, R.color.secondary);
        binding.swipeRefresh.setOnRefreshListener(this::load);
        load();
    }

    private void load() {
        binding.swipeRefresh.setRefreshing(true);
        ApiClient.getInstance().getApiService().getPlans().enqueue(new Callback<PlanModel.PlansResponse>() {
            @Override
            public void onResponse(@NonNull Call<PlanModel.PlansResponse> call, @NonNull Response<PlanModel.PlansResponse> r) {
                binding.swipeRefresh.setRefreshing(false);
                plans.clear();
                if (r.isSuccessful() && r.body() != null && r.body().success && r.body().plans != null) {
                    plans.addAll(r.body().plans);
                    if (r.body().checkoutUrl != null && !r.body().checkoutUrl.isEmpty()) checkoutUrl = r.body().checkoutUrl;
                    for (PlanModel.Plan p : plans) {
                        if (p.isCurrent) binding.tvCurrentPlan.setText(UiUtils.safe(p.nameMr).isEmpty() ? p.name : p.nameMr);
                    }
                    if (r.body().subscriptionEnd != null && !r.body().subscriptionEnd.isEmpty()) {
                        binding.tvValidity.setText("वैधता: " + r.body().subscriptionEnd);
                    }
                } else {
                    UiUtils.snackRetry(binding.getRoot(), getString(R.string.error_generic), v -> load());
                }
                binding.emptyState.layoutEmpty.setVisibility(plans.isEmpty() ? View.VISIBLE : View.GONE);
                adapter.notifyDataSetChanged();
            }

            @Override
            public void onFailure(@NonNull Call<PlanModel.PlansResponse> call, @NonNull Throwable t) {
                binding.swipeRefresh.setRefreshing(false);
                binding.emptyState.layoutEmpty.setVisibility(plans.isEmpty() ? View.VISIBLE : View.GONE);
                UiUtils.snackRetry(binding.getRoot(), getString(R.string.network_error), v -> load());
            }
        });
    }

    private void choose(PlanModel.Plan p) {
        String title = UiUtils.safe(p.nameMr).isEmpty() ? p.name : p.nameMr;
        new MaterialAlertDialogBuilder(this)
                .setTitle(title)
                .setMessage("किंमत: " + (p.isFree() ? "मोफत" : UiUtils.rupees(p.price)) + " / " + p.durationMonths + " महिने\n"
                        + "विद्यार्थी मर्यादा: " + (p.isUnlimited() ? "अमर्यादित" : String.valueOf(p.maxStudents))
                        + "\n\nपेमेंट Razorpay (UPI / कार्ड / नेट बँकिंग) द्वारे सुरक्षित ब्राउझरमध्ये पूर्ण होईल. "
                        + "पेमेंट झाल्यावर ॲपमध्ये पुन्हा लॉगिन करा.")
                .setPositiveButton("पेमेंटकडे जा", (d, w) -> {
                    Uri uri = Uri.parse(checkoutUrl).buildUpon().appendQueryParameter("plan_id", String.valueOf(p.id)).build();
                    startActivity(new Intent(Intent.ACTION_VIEW, uri));
                })
                .setNegativeButton(R.string.cancel, null)
                .show();
    }

    private class PlanAdapter extends RecyclerView.Adapter<PlanAdapter.VH> {
        @NonNull
        @Override
        public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
            return new VH(ItemPlanBinding.inflate(LayoutInflater.from(parent.getContext()), parent, false));
        }

        @Override
        public void onBindViewHolder(@NonNull VH h, int pos) {
            PlanModel.Plan p = plans.get(pos);
            ItemPlanBinding b = h.b;
            b.tvName.setText(UiUtils.safe(p.nameMr).isEmpty() ? p.name : p.nameMr);
            b.tvNameEn.setText(UiUtils.safe(p.name));
            b.tvPrice.setText(p.isFree() ? "मोफत" : UiUtils.rupees(p.price));
            b.tvPeriod.setText(p.isFree() ? "" : "/ " + p.durationMonths + " महिने");
            b.tvLimit.setText("👥 " + (p.isUnlimited() ? "अमर्यादित विद्यार्थी" : p.maxStudents + " विद्यार्थ्यांपर्यंत"));

            b.tvBadge.setVisibility(p.isCurrent ? View.VISIBLE : View.GONE);
            b.tvBadge.setText("सध्याची योजना");
            b.card.setStrokeColor(ContextCompat.getColor(PlansActivity.this, p.isCurrent ? R.color.success : R.color.card_stroke));
            b.card.setStrokeWidth(p.isCurrent ? 3 : 1);

            b.layoutFeatures.removeAllViews();
            if (p.features != null) {
                for (String f : p.features) {
                    TextView tv = new TextView(PlansActivity.this);
                    tv.setText("✔  " + f);
                    tv.setTextSize(13);
                    tv.setPadding(0, 4, 0, 4);
                    tv.setTextColor(ContextCompat.getColor(PlansActivity.this, R.color.text_primary));
                    b.layoutFeatures.addView(tv);
                }
            }
            if (p.modules != null && !p.modules.isEmpty()) {
                TextView tv = new TextView(PlansActivity.this);
                tv.setText("मॉड्यूल्स: " + p.modules.size() + " समाविष्ट");
                tv.setTypeface(null, Typeface.BOLD);
                tv.setTextSize(12);
                tv.setPadding(0, 8, 0, 0);
                tv.setTextColor(ContextCompat.getColor(PlansActivity.this, R.color.text_secondary));
                b.layoutFeatures.addView(tv);
            }

            b.btnChoose.setEnabled(!p.isCurrent && !p.isFree());
            b.btnChoose.setText(p.isCurrent ? "सक्रिय" : p.isFree() ? "मोफत योजना" : "ही योजना निवडा");
            b.btnChoose.setOnClickListener(v -> choose(p));
        }

        @Override
        public int getItemCount() { return plans.size(); }

        class VH extends RecyclerView.ViewHolder {
            final ItemPlanBinding b;
            VH(ItemPlanBinding b) { super(b.getRoot()); this.b = b; }
        }
    }
}
