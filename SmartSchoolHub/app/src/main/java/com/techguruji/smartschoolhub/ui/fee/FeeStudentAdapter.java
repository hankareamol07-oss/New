package com.techguruji.smartschoolhub.ui.fee;

import android.view.LayoutInflater;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.FeeModel;
import com.techguruji.smartschoolhub.databinding.ItemFeeStudentBinding;
import com.techguruji.smartschoolhub.utils.UiUtils;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public class FeeStudentAdapter extends RecyclerView.Adapter<FeeStudentAdapter.VH> {

    public interface OnCollect {
        void onCollect(FeeModel.FeeStudent student);
    }

    private final List<FeeModel.FeeStudent> all = new ArrayList<>();
    private final List<FeeModel.FeeStudent> shown = new ArrayList<>();
    private final OnCollect listener;
    private String query = "";

    public FeeStudentAdapter(OnCollect listener) {
        this.listener = listener;
    }

    public void submit(List<FeeModel.FeeStudent> students) {
        all.clear();
        if (students != null) all.addAll(students);
        filter(query);
    }

    public void filter(String q) {
        query = q == null ? "" : q.trim().toLowerCase(Locale.ROOT);
        shown.clear();
        for (FeeModel.FeeStudent s : all) {
            if (query.isEmpty()
                    || (s.name != null && s.name.toLowerCase(Locale.ROOT).contains(query))
                    || (s.nameMr != null && s.nameMr.contains(query))
                    || (s.rollNo != null && s.rollNo.equals(query))) {
                shown.add(s);
            }
        }
        notifyDataSetChanged();
    }

    public int shownCount() { return shown.size(); }

    @NonNull
    @Override
    public VH onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new VH(ItemFeeStudentBinding.inflate(LayoutInflater.from(parent.getContext()), parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull VH h, int position) {
        FeeModel.FeeStudent s = shown.get(position);
        String name = s.getDisplayName();
        h.b.tvName.setText(name);
        h.b.tvAvatar.setText(name.isEmpty() ? "?" : name.substring(0, 1).toUpperCase(Locale.ROOT));
        h.b.tvMeta.setText(UiUtils.gradeLabel(s.grade) + " · " + UiUtils.sectionLabel(s.section)
                + (s.rollNo == null || s.rollNo.isEmpty() ? "" : " · क्र. " + s.rollNo));
        h.b.tvPaid.setText(UiUtils.rupees(s.paidAmount) + " / " + UiUtils.rupees(s.totalFee));
        h.b.tvPending.setText(UiUtils.rupees(s.pendingAmount));
        int pct = s.totalFee > 0 ? (int) Math.round(100.0 * s.paidAmount / s.totalFee) : 0;
        h.b.progress.setProgressCompat(Math.min(100, pct), false);

        int color;
        String label;
        int badgeBg;
        if ("paid".equals(s.status) || (s.totalFee > 0 && s.pendingAmount <= 0)) {
            label = "पूर्ण";
            color = R.color.success;
            badgeBg = R.drawable.bg_badge_free;
        } else if ("partial".equals(s.status) || s.paidAmount > 0) {
            label = "अंशतः";
            color = R.color.warning;
            badgeBg = R.drawable.bg_badge_premium;
        } else {
            label = "बाकी";
            color = R.color.error;
            badgeBg = R.drawable.bg_badge_premium;
        }
        h.b.tvStatus.setText(label);
        h.b.tvStatus.setBackgroundResource(badgeBg);
        h.b.tvPending.setTextColor(h.b.getRoot().getContext().getColor(color));
        h.b.progress.setIndicatorColor(h.b.getRoot().getContext().getColor(color));

        h.b.getRoot().setOnClickListener(v -> listener.onCollect(s));
    }

    @Override
    public int getItemCount() { return shown.size(); }

    static class VH extends RecyclerView.ViewHolder {
        final ItemFeeStudentBinding b;
        VH(ItemFeeStudentBinding b) { super(b.getRoot()); this.b = b; }
    }
}
