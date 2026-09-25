package com.techguruji.smartschoolhub.adapter;

import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.EditText;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.techguruji.smartschoolhub.R;
import com.techguruji.smartschoolhub.data.model.CceModel;

import java.util.ArrayList;
import java.util.List;

public class CceAdapter extends RecyclerView.Adapter<CceAdapter.CceViewHolder> {

    private List<CceModel.CceStudent> students = new ArrayList<>();

    public void setStudents(List<CceModel.CceStudent> newStudents) {
        this.students = (newStudents != null) ? newStudents : new ArrayList<>();
        notifyDataSetChanged();
    }

    public List<CceModel.CceStudent> getStudents() {
        return students;
    }

    @NonNull
    @Override
    public CceViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.item_cce_student, parent, false);
        return new CceViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull CceViewHolder holder, int position) {
        CceModel.CceStudent student = students.get(position);
        holder.bind(student);
    }

    @Override
    public int getItemCount() {
        return students.size();
    }

    static class CceViewHolder extends RecyclerView.ViewHolder {

        private final TextView tvRollNo;
        private final TextView tvStudentName;
        private final EditText etMarks;
        private final TextView tvGradeBadge;

        public CceViewHolder(@NonNull View itemView) {
            super(itemView);
            tvRollNo = itemView.findViewById(R.id.tvRollNo);
            tvStudentName = itemView.findViewById(R.id.tvStudentName);
            etMarks = itemView.findViewById(R.id.etMarks);
            tvGradeBadge = itemView.findViewById(R.id.tvGradeBadge);
        }

        public void bind(CceModel.CceStudent student) {
            tvRollNo.setText(student.rollNo != null ? student.rollNo : String.valueOf(student.id));
            tvStudentName.setText(student.getDisplayName());

            etMarks.setText(student.marks > 0 ? String.valueOf(student.marks) : "");
            tvGradeBadge.setText(calculateGrade(student.marks, student.maxMarks > 0 ? student.maxMarks : 20));

            etMarks.addTextChangedListener(new TextWatcher() {
                @Override
                public void beforeTextChanged(CharSequence s, int start, int count, int after) {}

                @Override
                public void onTextChanged(CharSequence s, int start, int before, int count) {
                    try {
                        double val = s.length() > 0 ? Double.parseDouble(s.toString()) : 0.0;
                        student.marks = val;
                        tvGradeBadge.setText(calculateGrade(val, student.maxMarks > 0 ? student.maxMarks : 20));
                    } catch (Exception e) {
                        student.marks = 0.0;
                    }
                }

                @Override
                public void afterTextChanged(Editable s) {}
            });
        }

        private String calculateGrade(double marks, double maxMarks) {
            if (marks <= 0) return "—";
            double pct = (marks / Math.max(1, maxMarks)) * 100.0;
            if (pct >= 91) return "A1";
            if (pct >= 81) return "A2";
            if (pct >= 71) return "B1";
            if (pct >= 61) return "B2";
            if (pct >= 51) return "C1";
            if (pct >= 41) return "C2";
            if (pct >= 33) return "D";
            return "E";
        }
    }
}
