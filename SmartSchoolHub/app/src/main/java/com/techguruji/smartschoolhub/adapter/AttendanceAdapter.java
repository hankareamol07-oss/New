package com.techguruji.smartschoolhub.adapter;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.data.model.AttendanceModel.AttendanceStudent;
import com.techguruji.smartschoolhub.databinding.ItemAttendanceStudentBinding;

import java.util.ArrayList;
import java.util.List;

public class AttendanceAdapter extends RecyclerView.Adapter<AttendanceAdapter.ViewHolder> {

    public interface OnStatusChangeListener {
        void onStatusChanged();
    }

    private final List<AttendanceStudent> students = new ArrayList<>();
    private OnStatusChangeListener listener;

    public void setOnStatusChangeListener(OnStatusChangeListener listener) {
        this.listener = listener;
    }

    public void setStudents(List<AttendanceStudent> list) {
        students.clear();
        if (list != null) {
            students.addAll(list);
        }
        notifyDataSetChanged();
    }

    public List<AttendanceStudent> getStudents() {
        return students;
    }

    public void setAllStatus(String status) {
        for (AttendanceStudent s : students) {
            s.status = status;
        }
        notifyDataSetChanged();
        if (listener != null) listener.onStatusChanged();
    }

    @NonNull
    @Override
    public ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        ItemAttendanceStudentBinding binding = ItemAttendanceStudentBinding.inflate(
                LayoutInflater.from(parent.getContext()), parent, false);
        return new ViewHolder(binding);
    }

    @Override
    public void onBindViewHolder(@NonNull ViewHolder holder, int position) {
        holder.bind(students.get(position));
    }

    @Override
    public int getItemCount() {
        return students.size();
    }

    class ViewHolder extends RecyclerView.ViewHolder {
        private final ItemAttendanceStudentBinding binding;

        ViewHolder(ItemAttendanceStudentBinding binding) {
            super(binding.getRoot());
            this.binding = binding;
        }

        void bind(AttendanceStudent student) {
            binding.tvRollNo.setText(student.rollNo != null ? student.rollNo : String.valueOf(getAdapterPosition() + 1));
            binding.tvStudentName.setText(student.getDisplayName());
            binding.tvStudentDetails.setText("इयत्ता " + (student.grade != null ? student.grade : "१")
                    + " ली | तुकडी " + (student.section != null ? student.section : "अ"));

            // Clear checked state to prevent recycled view bugs
            binding.rgStatus.setOnCheckedChangeListener(null);

            if ("A".equalsIgnoreCase(student.status)) {
                binding.rbAbsent.setChecked(true);
            } else if ("L".equalsIgnoreCase(student.status)) {
                binding.rbLeave.setChecked(true);
            } else {
                binding.rbPresent.setChecked(true);
            }

            binding.rgStatus.setOnCheckedChangeListener((group, checkedId) -> {
                if (checkedId == binding.rbPresent.getId()) {
                    student.status = "P";
                } else if (checkedId == binding.rbAbsent.getId()) {
                    student.status = "A";
                } else if (checkedId == binding.rbLeave.getId()) {
                    student.status = "L";
                }
                if (listener != null) listener.onStatusChanged();
            });
        }
    }
}
