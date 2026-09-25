package com.techguruji.smartschoolhub.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.TachanModel;

import java.util.ArrayList;
import java.util.List;

/**
 * TachanAdapter — RecyclerView Adapter for displaying daily lesson plans.
 */
public class TachanAdapter extends RecyclerView.Adapter<TachanAdapter.TachanViewHolder> {

    private List<TachanModel.TachanItem> items = new ArrayList<>();

    public void setItems(List<TachanModel.TachanItem> newItems) {
        this.items = (newItems != null) ? newItems : new ArrayList<>();
        notifyDataSetChanged();
    }

    public List<TachanModel.TachanItem> getItems() {
        return items;
    }

    @NonNull
    @Override
    public TachanViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_tachan_card, parent, false);
        return new TachanViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull TachanViewHolder holder, int position) {
        TachanModel.TachanItem item = items.get(position);
        holder.bind(item);
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    static class TachanViewHolder extends RecyclerView.ViewHolder {

        private final TextView tvPeriod;
        private final TextView tvSubject;
        private final TextView tvGrade;
        private final TextView tvTopic;
        private final TextView tvLearningOutcome;
        private final TextView tvMaterials;
        private final TextView tvHomework;

        public TachanViewHolder(@NonNull View itemView) {
            super(itemView);
            tvPeriod = itemView.findViewById(R.id.tvPeriod);
            tvSubject = itemView.findViewById(R.id.tvSubject);
            tvGrade = itemView.findViewById(R.id.tvGrade);
            tvTopic = itemView.findViewById(R.id.tvTopic);
            tvLearningOutcome = itemView.findViewById(R.id.tvLearningOutcome);
            tvMaterials = itemView.findViewById(R.id.tvMaterials);
            tvHomework = itemView.findViewById(R.id.tvHomework);
        }

        public void bind(TachanModel.TachanItem item) {
            tvPeriod.setText("तासिका " + item.period);
            tvSubject.setText(item.subject != null ? item.subject : "मराठी");
            tvGrade.setText("इयत्ता " + (item.grade != null ? item.grade : "१ ली"));
            tvTopic.setText("घटक: " + (item.topic != null ? item.topic : "—"));

            if (item.learningOutcome != null && !item.learningOutcome.isEmpty()) {
                tvLearningOutcome.setVisibility(View.VISIBLE);
                tvLearningOutcome.setText("🎯 अध्ययन निष्पत्ती: " + item.learningOutcome);
            } else {
                tvLearningOutcome.setVisibility(View.GONE);
            }

            if (item.materials != null && !item.materials.isEmpty()) {
                tvMaterials.setVisibility(View.VISIBLE);
                tvMaterials.setText("📦 शैक्षणिक साधने: " + item.materials);
            } else {
                tvMaterials.setVisibility(View.GONE);
            }

            if (item.homework != null && !item.homework.isEmpty()) {
                tvHomework.setVisibility(View.VISIBLE);
                tvHomework.setText("✏️ गृहपाठ: " + item.homework);
            } else {
                tvHomework.setVisibility(View.GONE);
            }
        }
    }
}
