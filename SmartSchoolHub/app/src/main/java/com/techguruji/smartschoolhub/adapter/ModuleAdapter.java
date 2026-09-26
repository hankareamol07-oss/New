package com.techguruji.smartschoolhub.adapter;

import android.content.res.ColorStateList;
import android.view.LayoutInflater;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.data.model.ModuleItem;
import com.techguruji.smartschoolhub.databinding.ItemModuleCardBinding;

import java.util.List;

/**
 * ModuleAdapter — RecyclerView adapter for the modules grid.
 */
public class ModuleAdapter extends RecyclerView.Adapter<ModuleAdapter.ModuleViewHolder> {

    public interface OnModuleClickListener {
        void onModuleClick(ModuleItem item);
    }

    private final List<ModuleItem> modules;
    private final OnModuleClickListener listener;

    public ModuleAdapter(List<ModuleItem> modules, OnModuleClickListener listener) {
        this.modules = modules;
        this.listener = listener;
    }

    @NonNull
    @Override
    public ModuleViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        ItemModuleCardBinding binding = ItemModuleCardBinding.inflate(
                LayoutInflater.from(parent.getContext()), parent, false);
        return new ModuleViewHolder(binding);
    }

    @Override
    public void onBindViewHolder(@NonNull ModuleViewHolder holder, int position) {
        holder.bind(modules.get(position));
    }

    @Override
    public int getItemCount() { return modules.size(); }

    class ModuleViewHolder extends RecyclerView.ViewHolder {

        private final ItemModuleCardBinding b;

        ModuleViewHolder(ItemModuleCardBinding binding) {
            super(binding.getRoot());
            this.b = binding;
        }

        void bind(ModuleItem item) {
            b.tvTitle.setText(item.getTitle());
            b.tvTitleEn.setText(item.getTitleEn());
            b.tvDescription.setText(item.getDescription());
            b.tvBadge.setText(item.getBadge());
            b.ivModuleIcon.setImageResource(item.getIconRes());

            // Icon container background color
            int bgColor = ContextCompat.getColor(b.getRoot().getContext(), item.getIconBgColorRes());
            b.iconContainer.setBackgroundTintList(ColorStateList.valueOf(bgColor));

            // Icon tint
            int iconColor = ContextCompat.getColor(b.getRoot().getContext(), item.getIconColorRes());
            b.ivModuleIcon.setColorFilter(iconColor);

            // Free badge color
            if (item.isFree()) {
                b.tvBadge.setBackgroundTintList(ColorStateList.valueOf(
                        ContextCompat.getColor(b.getRoot().getContext(),
                                com.techguruji.smartschoolhub.R.color.success)));
                b.tvBadge.setTextColor(ContextCompat.getColor(b.getRoot().getContext(),
                        android.R.color.white));
            }

            b.getRoot().setOnClickListener(v -> listener.onModuleClick(item));
        }
    }
}
