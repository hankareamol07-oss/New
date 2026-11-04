package com.techguruji.smartschoolhub.adapter;

import android.view.LayoutInflater;
import android.view.ViewGroup;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.DiffUtil;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.data.model.StudentListResponse;
import com.techguruji.smartschoolhub.databinding.ItemStudentBinding;

import java.util.ArrayList;
import java.util.List;

/**
 * StudentAdapter — RecyclerView adapter for the students list.
 */
public class StudentAdapter extends RecyclerView.Adapter<StudentAdapter.StudentViewHolder> {

    public interface OnStudentClickListener {
        void onStudentClick(StudentListResponse.Student student);
    }

    private List<StudentListResponse.Student> students;
    private final OnStudentClickListener listener;

    public StudentAdapter(List<StudentListResponse.Student> students,
                          OnStudentClickListener listener) {
        this.students = students;
        this.listener = listener;
    }

    public void updateList(List<StudentListResponse.Student> newList) {
        this.students = new ArrayList<>(newList);
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public StudentViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        ItemStudentBinding binding = ItemStudentBinding.inflate(
                LayoutInflater.from(parent.getContext()), parent, false);
        return new StudentViewHolder(binding);
    }

    @Override
    public void onBindViewHolder(@NonNull StudentViewHolder holder, int position) {
        holder.bind(students.get(position));
    }

    @Override
    public int getItemCount() { return students.size(); }

    class StudentViewHolder extends RecyclerView.ViewHolder {
        private final ItemStudentBinding b;

        StudentViewHolder(ItemStudentBinding binding) {
            super(binding.getRoot());
            this.b = binding;
        }

        void bind(StudentListResponse.Student s) {
            b.tvStudentName.setText(s.getDisplayName());
            b.tvGrade.setText(s.getGrade() + (s.getSection() != null ? " - " + s.getSection() : ""));
            b.tvRollNo.setText("क्र. " + (s.getRollNo() != null ? s.getRollNo() : "-"));
            b.tvGender.setText(s.getGender() != null ? s.getGender() : "");

            // Avatar initial letter
            String name = s.getDisplayName();
            b.tvInitial.setText(name.isEmpty() ? "?" :
                    String.valueOf(name.codePointAt(0) < 128
                            ? Character.toUpperCase(name.charAt(0))
                            : name.charAt(0)));

            b.getRoot().setOnClickListener(v -> listener.onStudentClick(s));
        }
    }
}
